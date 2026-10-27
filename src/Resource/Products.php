<?php

declare(strict_types=1);

namespace Yespos\Client\Resource;

use Yespos\Client\Http\HttpClient;
use Yespos\Client\Model\Product;
use Yespos\Client\Model\ProductCategory;
use Yespos\Client\Model\ProductDetail;
use Yespos\Client\YesposException;

/** Produk yang dijual di sebuah outlet. */
final class Products
{
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Produk sebuah outlet, dikelompokkan per kategori.
     *
     * @return list<ProductCategory>
     */
    public function list(string $outletUuid): array
    {
        $rows = $this->http->get($this->path($outletUuid)) ?? [];

        return array_map(ProductCategory::fromArray(...), array_values(array_filter($rows, 'is_array')));
    }

    /** Satu produk sebuah outlet beserta nama kategorinya. */
    public function get(string $outletUuid, string $productUuid): ProductDetail
    {
        $data = $this->http->get($this->path($outletUuid) . '/' . rawurlencode($productUuid));
        if ($data === null) {
            throw new YesposException(404, 'Produk tidak ditemukan.');
        }

        return ProductDetail::fromArray($data);
    }

    /**
     * Seluruh produk sebuah outlet dengan kunci uuid. Satu produk bisa berada
     * di beberapa kategori; di sini ia hanya muncul sekali.
     *
     * @return array<string,Product>
     */
    public function map(string $outletUuid): array
    {
        $products = [];
        foreach ($this->list($outletUuid) as $category) {
            foreach ($category->products as $product) {
                $products[$product->uuid] = $product;
            }
        }

        return $products;
    }

    private function path(string $outletUuid): string
    {
        return '/outlets/' . rawurlencode($outletUuid) . '/products';
    }
}
