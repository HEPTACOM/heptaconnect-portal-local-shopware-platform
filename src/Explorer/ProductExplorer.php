<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;

class ProductExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Product::class;
    }

    protected function getRepositoryName(): string
    {
        return 'product';
    }
}
