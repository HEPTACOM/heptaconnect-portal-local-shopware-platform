<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\Order;

class OrderExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return Order::class;
    }

    protected function getRepositoryName(): string
    {
        return 'order';
    }
}
