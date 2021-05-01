<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;

class UnitExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Unit::class;
    }

    protected function getRepositoryName(): string
    {
        return 'unit';
    }
}
