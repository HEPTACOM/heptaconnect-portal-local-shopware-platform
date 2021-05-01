<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\PaymentMethod\PaymentMethod;

class PaymentMethodExplorer extends ShopwareExplorer
{
    public function supports(): string
    {
        return PaymentMethod::class;
    }

    protected function getRepositoryName(): string
    {
        return 'payment_method';
    }
}
