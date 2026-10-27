<?php

declare(strict_types=1);

namespace Yespos\Client\Resource;

use Yespos\Client\Http\HttpClient;
use Yespos\Client\Model\Outlet;

/** Outlet milik pemilik client. */
final class Outlets
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Seluruh outlet milik pemilik client.
     *
     * @return list<Outlet>
     */
    public function list(): array
    {
        $rows = $this->http->get('/outlets') ?? [];

        return array_map(Outlet::fromArray(...), array_values(array_filter($rows, 'is_array')));
    }
}
