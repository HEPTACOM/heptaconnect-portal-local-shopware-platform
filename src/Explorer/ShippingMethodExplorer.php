<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\ShippingMethod\ShippingMethod;

class ShippingMethodExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return ShippingMethod::class;
    }

    protected function getRepositoryName(): string
    {
        return 'shipping_method';
    }
}
