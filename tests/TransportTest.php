<?php

declare(strict_types=1);

namespace Yespos\Client\Tests;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Yespos\Client\Client;
use Yespos\Client\Http\ClientOptions;
use Yespos\Client\Http\CurlTransport;
use Yespos\Client\Http\Psr18Transport;
use Yespos\Client\YesposException;

/**
 * Menguji transport sungguhan: CurlTransport terhadap server HTTP hidup, dan
 * Psr18Transport terhadap client PSR-18.
 */
final class TransportTest extends TestCase
{
    /** @var resource|null */
    private static $server;
    private static string $baseUrl = '';

    public static function setUpBeforeClass(): void
    {
        $port = self::freePort();
        self::$baseUrl = "http://127.0.0.1:{$port}";

        $command = sprintf(
            'exec php -S 127.0.0.1:%d %s',
            $port,
            escapeshellarg(__DIR__ . '/server.php'),
        );
        $pipes = [];
        $process = proc_open($command, [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
        if (!is_resource($process)) {
            self::markTestSkipped('tidak dapat menjalankan php -S');
        }
        self::$server = $process;

        // Tunggu sampai server siap menerima koneksi.
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port, $code, $message, 0.1);
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50_000);
        }
        self::markTestSkipped('php -S tidak kunjung siap');
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$server)) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    private static function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $code, $message);
        if ($socket === false) {
            self::markTestSkipped('tidak dapat membuka port');
        }
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    #[Test]
    public function curl_transport_talks_to_a_real_server(): void
    {
        $client = new Client(new ClientOptions(self::$baseUrl, MockApi::KEY, MockApi::SECRET));
        $outlets = $client->outlets->list();

        self::assertCount(2, $outlets);
        self::assertSame('Cabang Sudirman', $outlets[0]->name);
    }

    #[Test]
    public function curl_transport_is_rejected_with_the_wrong_secret(): void
    {
        $client = new Client(new ClientOptions(self::$baseUrl, MockApi::KEY, 'nope'));

        try {
            $client->outlets->list();
            self::fail('seharusnya ditolak');
        } catch (YesposException $failure) {
            self::assertSame(401, $failure->status);
        }
    }

    #[Test]
    public function curl_transport_reports_an_unreachable_server(): void
    {
        $client = new Client(new ClientOptions('http://127.0.0.1:1', MockApi::KEY, MockApi::SECRET));

        try {
            $client->outlets->list();
            self::fail('seharusnya gagal');
        } catch (YesposException $failure) {
            self::assertTrue($failure->isNetworkError());
        }
    }

    #[Test]
    public function curl_transport_times_out_a_slow_request(): void
    {
        $transport = new CurlTransport();

        try {
            $transport->send('GET', self::$baseUrl . '/slow', [], null, 0.1);
            self::fail('seharusnya kehabisan waktu');
        } catch (YesposException $failure) {
            self::assertSame('Permintaan ke YesPOS melebihi batas waktu.', $failure->getMessage());
            self::assertTrue($failure->isNetworkError());
        }
    }

    #[Test]
    public function psr18_transport_uses_the_given_client(): void
    {
        $factory = new Psr17Factory();
        $api = new MockApi();
        $psrClient = new class($api) implements ClientInterface {
            public function __construct(private readonly MockApi $api)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $headers = [];
                foreach ($request->getHeaders() as $name => $values) {
                    $headers[$name] = implode(', ', $values);
                }
                $answer = $this->api->handle(
                    $request->getMethod(),
                    $request->getUri()->getPath(),
                    (string) $request->getBody(),
                    $headers,
                );

                return new Response($answer->status, ['Content-Type' => 'application/json'], $answer->body);
            }
        };

        $client = new Client(new ClientOptions(
            'https://api.test',
            MockApi::KEY,
            MockApi::SECRET,
            transport: new Psr18Transport($psrClient, $factory, $factory),
        ));

        self::assertCount(2, $client->outlets->list());
        self::assertSame('GET', $api->lastRequest()['method']);
    }
}
