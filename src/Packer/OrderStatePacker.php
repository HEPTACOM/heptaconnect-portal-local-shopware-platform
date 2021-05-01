<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\OrderState;
use Shopware\Core\Checkout\Order\OrderStates;

class OrderStatePacker
{
    public function pack(string $state): OrderState
    {
        $result = new OrderState();

        switch ($state) {
            case OrderStates::STATE_OPEN:
                $result->setState(OrderState::STATE_OPEN);
                break;
            case OrderStates::STATE_COMPLETED:
                $result->setState(OrderState::STATE_COMPLETE);
                break;
            case OrderStates::STATE_IN_PROGRESS:
                $result->setState(OrderState::STATE_PROGRESS);
                break;
            case OrderStates::STATE_CANCELLED:
                $result->setState(OrderState::STATE_CANCELLED);
                break;
            default:
                $result->setState(OrderState::STATE_UNKNOWN);
                break;
        }

        return $result;
    }
}
