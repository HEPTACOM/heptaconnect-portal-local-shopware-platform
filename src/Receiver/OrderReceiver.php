<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem\Product as LineItemProduct;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\Order;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\OrderState;
use Heptacom\HeptaConnect\Portal\Base\Portal\Contract\PortalStorageInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\StateMachineTransitionWalker;
use Ramsey\Uuid\Uuid as RamseyUuid;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteTypeIntendException;
use Shopware\Core\Framework\Uuid\Uuid as ShopwareUuid;

class OrderReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private StateMachineTransitionWalker $stateMachineTransitionWalker;

    public function __construct(DalAccess $dal, StateMachineTransitionWalker $stateMachineTransitionWalker)
    {
        $this->dal = $dal;
        $this->stateMachineTransitionWalker = $stateMachineTransitionWalker;
    }

    public function supports(): string
    {
        return Order::class;
    }

    /**
     * @param Order $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $primaryKey = $entity->getPrimaryKey();

        if (\is_null($primaryKey)) {
            throw new \Exception('Unsupported order creation. Map first to an existing order to update'); // TODO: check types
        }

        $dalContext = $this->dal->getContext();

        $order = $this->dal->read('order', [$primaryKey], [
            'stateMachineState',
            'deliveries',
            'delivery.trackingCodes',
            'transactions',
            'lineItems.product',
        ])->first();

        if (!$order instanceof OrderEntity) {
            throw new \Exception('Order not found');
        }

        switch ($entity->getOrderState()->getState()) {
            case OrderState::STATE_OPEN:
                $orderState = OrderStates::STATE_OPEN;
                break;
            case OrderState::STATE_PROGRESS:
                $orderState = OrderStates::STATE_IN_PROGRESS;
                break;
            case OrderState::STATE_COMPLETE:
                $orderState = OrderStates::STATE_COMPLETED;
                break;
            case OrderState::STATE_CANCELLED:
            default:
                $orderState = OrderStates::STATE_CANCELLED;
                break;
        }

        $this->stateMachineTransitionWalker->walkPath('order', $primaryKey, 'stateId', $orderState, $dalContext);

        $sourceLineItems = \iterable_to_array($entity->getLineItems());
        $targetLineItems = $order->getLineItems()->getElements();

        $this->saveOrderState($context->getStorage(), $entity);

        $this->updateLineItems($sourceLineItems, $targetLineItems, $dalContext, $entity);

        $delivery = $order->getDeliveries()->first();

        if ($delivery instanceof OrderDeliveryEntity) {
            self::updateTrackingCodes($entity, $delivery, $this->dal->repository('order_delivery'), $dalContext);

            try {
                $this->updateShippingAddress($delivery, $entity, $order, $dalContext);
            } catch (\Exception $e) {
                throw new \Exception($e->getMessage(), $e->getCode());
            }
        }

        $this->updateOrderPrice($entity, $order, $dalContext);
    }

    protected function saveOrderState(PortalStorageInterface $portalStorage, Order $order): void
    {
        $portalStorage->set($this->getOrderHashKey($order), $this->getCurrentOrderHash($order));
    }

    private function getOrderHashKey(Order $order): string
    {
        $order->setPrimaryKey($order->getPrimaryKey() ?? (string) RamseyUuid::uuid5('3c8352ab-0fb8-4006-bff6-f8133676644e', $order->getNumber())->getHex());

        return 'OrderHashKey:'.$order->getPrimaryKey();
    }

    private function getCurrentOrderHash(Order $order): string
    {
        return \md5(\json_encode($order));
    }

    private static function getLineItemToUpdate(LineItemProduct $sourceLineItem, array $targetLineItems): ?array
    {
        /** @var OrderLineItemEntity $targetLineItem */
        foreach ($targetLineItems as $targetLineItem) {
            $targetProductId = $targetLineItem->getId();
            if ($sourceLineItem->getPrimarykey() === $targetProductId) {
                if ($sourceLineItem->getTotalPrice() !== $targetLineItem->getTotalPrice()) {
                    $price = self::getCalculatedPrice($sourceLineItem);

                    return [
                        'id' => $targetLineItem->getId(),
                        'orderId' => $targetLineItem->getOrderId(),
                        'quantity' => $sourceLineItem->getQuantity(),
                        'price' => $price,
                        'unitPrice' => $sourceLineItem->getUnitPrice(),
                        'totalPrice' => $sourceLineItem->getTotalPrice(),
                    ];
                }

                return null;
            }
        }

        return null;
    }

    private static function getLineItemToCreate(
        LineItemProduct $sourceLineItem,
        DalAccess $dalAccess,
        $orderId
    ): array {
        $price = self::getCalculatedPrice($sourceLineItem);

        if (!$dalAccess->idExists('product', $sourceLineItem->getProduct()->getPrimaryKey())) {
            throw new \Exception(\sprintf('Product with number: %s have no primary key. Please check the product mapping.', $sourceLineItem->getNumber()));
        }

        return [
            // TODO amend id generation
            'id' => ShopwareUuid::randomHex(),
            'orderId' => $orderId,
            'identifier' => $sourceLineItem->getProduct()->getPrimaryKey(),
            'quantity' => $sourceLineItem->getQuantity(),
            'unitPrice' => $sourceLineItem->getUnitPrice(),
            'totalPrice' => $sourceLineItem->getTotalPrice(),
            'label' => $sourceLineItem->getLabel()->getFallback(),
            'position' => $sourceLineItem->getPosition(),
            'price' => $price,
            'type' => 'product',
            'productId' => $sourceLineItem->getProduct()->getPrimaryKey(),
            'referencedId' => $sourceLineItem->getProduct()->getPrimaryKey(),
        ];
    }

    private static function getCalculatedPrice(LineItemProduct $sourceLineItem): CalculatedPrice
    {
        return new CalculatedPrice(
            $sourceLineItem->getUnitPrice(),
            $sourceLineItem->getTotalPrice(),
            new CalculatedTaxCollection([
                new CalculatedTax(
                    $sourceLineItem->getTotalTaxAmount(),
                    $sourceLineItem->getTaxRate(),
                    $sourceLineItem->getTotalPrice(),
                ),
            ]),
            new TaxRuleCollection([
                new TaxRule(
                    $sourceLineItem->getTaxRate(),
                    100
                ),
            ])
        );
    }

    private function updateLineItems(
        iterable $sourceLineItems,
        array $targetLineItems,
        Context $dalContext,
        DatasetEntityContract $entity
    ): void {
        $orderLineItemRepository = $this->dal->repository('order_line_item');

        /** @var LineItem $sourceLineItem */
        foreach ($sourceLineItems as $sourceLineItem) {
            if ($sourceLineItem instanceof LineItemProduct) {
                $updateLineItem = self::getLineItemToUpdate($sourceLineItem, $targetLineItems);

                if (!empty($updateLineItem)) {
                    $orderLineItemRepository->update([$updateLineItem], $dalContext);
                }

                if (empty($sourceLineItem->getPrimaryKey())) {
                    $targetLineItem = self::getLineItemToCreate($sourceLineItem, $this->dal, $entity->getPrimaryKey());
                    $orderLineItemRepository->create([$targetLineItem], $dalContext);
                    $sourceLineItem->setPrimaryKey($targetLineItem['id']);
                }
            }
        }
    }

    private function updateOrderPrice(
        DatasetEntityContract $entity,
        OrderEntity $order,
        Context $dalContext
    ): void {
        if ($entity->getAmountTotal() !== $order->getAmountTotal()) {
            switch ($entity->isGross()) {
                case true:
                    $taxStatus = CartPrice::TAX_STATE_GROSS;
                    break;
                default:
                    $taxStatus = CartPrice::TAX_STATE_NET;
            }

            $targetOrderPrice = [
                'id' => $entity->getPrimaryKey(),
                'price' => new CartPrice(
                    $entity->getAmountNet(),
                    $entity->getAmountTotal(),
                    $entity->getAmountNet(),
                    new CalculatedTaxCollection([
                        new CalculatedTax(
                            $entity->getTotalTax(),
                            19, // TODO
                            $entity->getAmountTotal(),
                        ),
                    ]),
                    new TaxRuleCollection([
                        new TaxRule(
                            19, // TODO,
                            100
                        ),
                    ]),
                    $taxStatus
                ),
            ];

            $this->dal->repository('order')->update([$targetOrderPrice], $dalContext);
        }
    }

    private static function updateTrackingCodes(
        DatasetEntityContract $entity,
        OrderDeliveryEntity $delivery,
        EntityRepositoryInterface $orderDeliveryRepository,
        Context $dalContext
    ): void {
        if (\is_string($entity->getDeliveryTrackingCode())) {
            $target = [
                'id' => $delivery->getId(),
                'orderId' => $entity->getPrimaryKey(),
                'trackingCodes' => [$entity->getDeliveryTrackingCode()],
            ];
            $orderDeliveryRepository->update([$target], $dalContext);
        }
    }

    private function getOrderAddressHashKey(array $orderAddress): string
    {
        $orderAddress = \array_filter($orderAddress);
        \ksort($orderAddress);

        return \md5(\json_encode($orderAddress));
    }

    private static function getSourceShippingAddress(OrderDeliveryEntity $delivery, OrderEntity $order): array
    {
        return [
            'countryId' => $delivery->getShippingOrderAddress()->getCountryId(),
            'countryStateId' => $delivery->getShippingOrderAddress()->getCountryStateId(),
            'orderId' => $order->getId(),
            'firstName' => $delivery->getShippingOrderAddress()->getFirstName(),
            'lastName' => $delivery->getShippingOrderAddress()->getLastName(),
            'street' => $delivery->getShippingOrderAddress()->getStreet(),
            'additionalAddressLine1' => $delivery->getShippingOrderAddress()->getAdditionalAddressLine1(),
            'zipcode' => $delivery->getShippingOrderAddress()->getZipcode(),
            'city' => $delivery->getShippingOrderAddress()->getCity(),
            'phoneNumber' => $delivery->getShippingOrderAddress()->getPhoneNumber() ?: '',
            'salutationId' => $delivery->getShippingOrderAddress()->getSalutationId(),
        ];
    }

    private function getTargetShippingAddress(DatasetEntityContract $entity, Context $context, OrderEntity $order): array
    {
        $countryCriteria = new Criteria();
        $countryCriteria->addFilter(new EqualsFilter('iso', $entity->getShippingAddress()->getCountry()->getIso()));
        $countryId = $this->dal->repository('country')->searchIds($countryCriteria, $context)->firstId();

        if (!$countryId) {
            throw new \Exception(\sprintf('countryId or countryStateId is not given.'));
        }

        return [
            'countryId' => $countryId,
            'orderId' => $entity->getPrimaryKey(),
            'firstName' => $entity->getShippingAddress()->getNames()->first(),
            'lastName' => $entity->getShippingAddress()->getNames()->last(),
            'street' => $entity->getShippingAddress()->getStreet(),
            'additionalAddressLine1' => $entity->getShippingAddress()->getAdditionalLines()->first(),
            'zipcode' => $entity->getShippingAddress()->getZipcode(),
            'city' => $entity->getShippingAddress()->getCity(),
            'phoneNumber' => $entity->getShippingAddress()->getPhoneNumber() ?: '',
            'salutationId' => $order->getOrderCustomer()->getSalutationId(),
        ];
    }

    private function updateShippingAddress(
        OrderDeliveryEntity $delivery,
        DatasetEntityContract $entity,
        OrderEntity $order,
        Context $dalContext
    ): void {
        $sourceShippingAddress = self::getSourceShippingAddress($delivery, $order);
        $sourceShippingAddressHash = self::getOrderAddressHashKey($sourceShippingAddress);

        $targetShippingAddress = $this->getTargetShippingAddress($entity, $dalContext, $order);
        $targetShippingAddressHash = self::getOrderAddressHashKey($targetShippingAddress);

        if ($targetShippingAddressHash !== $sourceShippingAddressHash) {
            $targetShippingAddress['id'] = $targetShippingAddressHash;
            try {
                $this->dal->repository('order_address')->create([$targetShippingAddress], $dalContext);

                $target = [
                    'id' => $delivery->getId(),
                    'orderId' => $entity->getPrimaryKey(),
                    'shippingOrderAddressId' => $targetShippingAddress['id'],
                ];
                $this->dal->repository('order_delivery')->update([$target], $dalContext);
            } catch (WriteTypeIntendException $exception) {
                throw new \Exception('new shipping address already exist.');
            }
        }
    }
}
