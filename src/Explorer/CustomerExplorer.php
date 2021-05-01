<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;

class CustomerExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Customer::class;
    }

    protected function getRepositoryName(): string
    {
        return 'customer';
    }
}
