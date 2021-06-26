<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Strategy;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Shopware\Core\Defaults;

class CustomerSalesChannelStrategyContract
{
    public function getCustomerSalesChannelId(
        Customer $customer,
        ReceiveContextInterface $context
    ): string {
        return Defaults::SALES_CHANNEL;
    }
}
