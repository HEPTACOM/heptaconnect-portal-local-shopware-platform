<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;

class SalutationExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Salutation::class;
    }

    protected function getRepositoryName(): string
    {
        return 'salutation';
    }
}
