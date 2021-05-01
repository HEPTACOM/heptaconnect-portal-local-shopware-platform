<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;

class CategoryExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Category::class;
    }

    protected function getRepositoryName(): string
    {
        return 'category';
    }
}
