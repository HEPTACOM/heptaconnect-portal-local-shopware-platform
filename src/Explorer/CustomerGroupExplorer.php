<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;

class CustomerGroupExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return CustomerGroup::class;
    }

    protected function getRepositoryName(): string
    {
        return 'customer_group';
    }
}
