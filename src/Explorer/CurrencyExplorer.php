<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;

class CurrencyExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Currency::class;
    }

    protected function getRepositoryName(): string
    {
        return 'currency';
    }
}
