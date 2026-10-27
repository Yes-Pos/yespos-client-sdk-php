<?php

declare(strict_types=1);

/**
 * Router untuk `php -S`, dipakai [TransportTest] agar CurlTransport benar-benar
 * berbicara lewat jaringan. State tidak bertahan antar-request, jadi hanya
 * endpoint tanpa state yang dilayani di sini.
 */

require __DIR__ . '/../vendor/autoload.php';

use Yespos\Client\Tests\MockApi;

$path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

// Endpoint khusus untuk menguji batas waktu.
if ($path === '/slow') {
    usleep(900_000);
    http_response_code(200);
    echo '{"data":[]}';

    return true;
}

$headers = [];
foreach ($_SERVER as $name => $value) {
    if (str_starts_with($name, 'HTTP_')) {
        $headers[str_replace('_', '-', strtolower(substr($name, 5)))] = (string) $value;
    }
}

parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_QUERY), $query);

$response = (new MockApi())->handle(
    $_SERVER['REQUEST_METHOD'] ?? 'GET',
    $path,
    (string) file_get_contents('php://input'),
    $headers,
    $query,
);

http_response_code($response->status);
header('Content-Type: application/json');
echo $response->body;

return true;
