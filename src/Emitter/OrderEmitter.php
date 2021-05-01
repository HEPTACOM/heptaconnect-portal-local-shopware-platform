<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Base\ScalarCollection\StringCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Address;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\AddressCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Country;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\CountryState;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem\Product as LineItemProduct;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem\Shipping;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem\Text;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItem\Voucher;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\LineItemCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\Order;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\PaymentState;
use Heptacom\HeptaConnect\Dataset\Ecommerce\PaymentMethod\PaymentMethod;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer\CustomerPacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer\OrderStatePacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Currency\CurrencyEntity;

class OrderEmitter extends EmitterContract
{
    public function supports(): string
    {
        return Order::class;
    }

    protected function run(
        MappingInterface $mapping,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        /** @var CustomerPacker $customerPacker */
        $customerPacker = $container->get(CustomerPacker::class);

        $source = $dalAccess->getContext()->disableCache(function (Context $c) use ($mapping, $dalAccess): ?OrderEntity {
            return $dalAccess->read('order', [$mapping->getExternalId()], [
                'orderCustomer',
                'currency',
                'addresses.salutation',
                'addresses.country',
                'addresses.countryState',
                'deliveries',
                'transactions',
                'lineItems.product',
            ], $c)->first();
        });

        if (!$source instanceof OrderEntity) {
            throw new \Exception('Order was not found');
        }

        $sourceBillingAddress = $source->getAddresses()->get($source->getBillingAddressId());
        $sourceDelivery = $source->getDeliveries()->first();
        $sourceTransaction = $source->getTransactions()->first();
        $sourceCurrency = $source->getCurrency() ?? self::getDefaultCurrency();

        if ($sourceDelivery instanceof OrderDeliveryEntity) {
            $sourceShippingAddress = $source->getAddresses()->get($sourceDelivery->getShippingOrderAddressId());
        } else {
            $sourceShippingAddress = $sourceBillingAddress;
        }

        $targetCurrency = (new Currency())->setIso($sourceCurrency->getIsoCode());
        $targetCurrency->setPrimaryKey($sourceCurrency->getId());

        $targetBillingAddress = $this->getAddress($sourceBillingAddress);
        $targetShippingAddress = $this->getAddress($sourceShippingAddress);
        $targetCustomer = $customerPacker->pack($source->getOrderCustomer()->getCustomerId(), $context->getStorage());
        $targetCustomer->setAddresses(new AddressCollection([
            $targetBillingAddress,
            $targetShippingAddress,
        ]));
        $targetLineItems = $this->getLineItems($source);

        $target = new Order();
        $target->setPrimaryKey($source->getId());
        $target->setNumber($source->getOrderNumber());

        /** @var OrderStatePacker $orderStatePacker */
        $orderStatePacker = $container->get(OrderStatePacker::class);
        $target->setOrderState($orderStatePacker->pack($source->getStateMachineState()->getTechnicalName()));

        switch ($sourceTransaction->getStateMachineState()->getTechnicalName()) {
            case OrderTransactionStates::STATE_OPEN:
                $target->getPaymentState()->setState(PaymentState::STATE_OPEN);
                break;
            case OrderTransactionStates::STATE_PAID:
                $target->getPaymentState()->setState(PaymentState::STATE_PAID);
                break;
            case OrderTransactionStates::STATE_REFUNDED:
                $target->getPaymentState()->setState(PaymentState::STATE_REFUNDED);
                break;
            case OrderTransactionStates::STATE_CANCELLED:
                $target->getPaymentState()->setState(PaymentState::STATE_CANCELLED);
                break;
            default:
                $target->getPaymentState()->setState(PaymentState::STATE_UNKNOWN);
                break;
        }

        $target->setNumber($source->getOrderNumber())
            ->setOrderTime($source->getOrderDateTime())
            ->setAmountNet($source->getPrice()->getNetPrice())
            ->setAmountTotal($source->getPrice()->getTotalPrice())
            ->setLineItems($targetLineItems)
            ->setCustomer($targetCustomer)
            ->setCurrency($targetCurrency)
            ->setBillingAddress($targetBillingAddress)
            ->setShippingAddress($targetShippingAddress)
            ->setDeliveryTrackingCode($sourceDelivery->getTrackingCodes()[0] ?? null);

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setPrimaryKey($sourceTransaction->getPaymentMethodId());

        if ($sourceTransaction->getPaymentMethod() instanceof PaymentMethodEntity) {
            $paymentMethod->getName()->setTranslation(
                'default',
                $sourceTransaction->getPaymentMethod()->getShortName() ??
                    $sourceTransaction->getPaymentMethod()->getName() ??
                    $sourceTransaction->getPaymentMethod()->getDescription()
            );
        }

        $target->setPaymentMethod($paymentMethod);

        return $target;
    }

    protected static function getDefaultCurrency(): CurrencyEntity
    {
        $currency = new CurrencyEntity();
        $currency->setId(Defaults::CURRENCY);
        $currency->setName('Euro');
        $currency->setIsoCode('EUR');
        $currency->setFactor(1);
        $currency->setSymbol('€');
        $currency->setPosition(1);
        $currency->setDecimalPrecision(2);

        return $currency;
    }

    protected function getAddress(OrderAddressEntity $sourceAddress): Address
    {
        $targetAddress = new Address();
        $sourceCountry = $sourceAddress->getCountry();

        if (!$sourceCountry instanceof CountryEntity) {
            throw new \Exception('Address has no country.');
        }

        $targetCountry = (new Country())
            ->setActive($sourceCountry->getActive())
            ->setIso($sourceCountry->getIso() ?? '')
            ->setIso3($sourceCountry->getIso3() ?? '')
            ->setTaxFree($sourceCountry->getTaxFree());

        $targetCountry->setPrimaryKey($sourceCountry->getId());
        $targetAddress->setCountry($targetCountry);

        $sourceCountryState = $sourceAddress->getCountryState();

        if ($sourceCountryState instanceof CountryStateEntity) {
            $targetCountryState = (new CountryState())
                ->setName($sourceCountryState->getName())
                ->setShortCode($sourceCountryState->getShortCode())
                ->setActive($sourceCountryState->getActive())
                ->setCountry($targetCountry);

            $targetCountryState->setPrimaryKey($sourceCountryState->getId());
            $targetAddress->setCountryState($targetCountryState);
        }

        $targetSalutation = (new Salutation())->setSlug($sourceAddress->getSalutation()->getSalutationKey());

        $targetSalutation->setPrimaryKey($sourceAddress->getSalutation()->getId());
        $targetAddress->setSalutation($targetSalutation);

        $targetAddress->setCompany($sourceAddress->getCompany() ?? '');
        $targetAddress->setDepartment($sourceAddress->getDepartment() ?? '');
        $targetAddress->setTitle($sourceAddress->getTitle() ?? '');
        $targetAddress->setNames(new StringCollection([
            $sourceAddress->getFirstName(),
            $sourceAddress->getLastName(),
        ]));
        $targetAddress->setStreet($sourceAddress->getStreet());
        $targetAddress->setZipcode($sourceAddress->getZipcode());
        $targetAddress->setCity($sourceAddress->getCity());
        $targetAddress->setVatId($sourceAddress->getVatId() ?? '');
        $targetAddress->setPhoneNumber($sourceAddress->getPhoneNumber() ?? '');
        $targetAddress->setAdditionalLines(new StringCollection(\array_filter([
            $sourceAddress->getAdditionalAddressLine1(),
            $sourceAddress->getAdditionalAddressLine2(),
        ])));

        $targetAddress->setPrimaryKey($sourceAddress->getId());

        return $targetAddress;
    }

    protected function getCustomer(
        OrderCustomerEntity $sourceCustomer,
        Address $targetBillingAddress,
        Address $targetShippingAddress
    ): Customer {
        $targetCustomer = new Customer();

        $targetSalutation = (new Salutation())->setSlug($sourceCustomer->getSalutation()->getSalutationKey());

        $targetSalutation->setPrimaryKey($sourceCustomer->getSalutation()->getId());
        $targetCustomer->setSalutation($targetSalutation);

        $targetCustomer->setNumber($sourceCustomer->getCustomerNumber() ?? '');
        $targetCustomer->setEmail($sourceCustomer->getEmail());
        $targetCustomer->setCompany($sourceCustomer->getCompany() ?? '');
        $targetCustomer->setTitle($sourceCustomer->getTitle() ?? '');
        $targetCustomer->setNames(new StringCollection([
            $sourceCustomer->getFirstName(),
            $sourceCustomer->getLastName(),
        ]));

        $targetCustomer->setAddresses(new AddressCollection([
            $targetBillingAddress,
            $targetShippingAddress,
        ]));

        $realCustomer = $sourceCustomer->getCustomer();

        if ($realCustomer instanceof CustomerEntity) {
            $targetCustomer->setBirthday($realCustomer->getBirthday());
        }

        $targetCustomer->setPrimaryKey($sourceCustomer->getCustomerId());

        return $targetCustomer;
    }

    protected function getLineItems(OrderEntity $source): LineItemCollection
    {
        $targetLineItems = new LineItemCollection();

        /** @var OrderLineItemEntity $sourceLineItem */
        foreach ($source->getLineItems() as $sourceLineItem) {
            if (($sourceProduct = $sourceLineItem->getProduct()) instanceof ProductEntity) {
                $configurations = \array_filter(\array_map(function (array $optionAssignment) {
                    $group = \trim($optionAssignment['group'] ?? '');
                    $option = \trim($optionAssignment['option'] ?? '');

                    return $group === '' || $option === '' ? null : $group.': '.$option;
                }, $sourceLineItem->getPayload()['options'] ?? []));

                $targetLineItem = new LineItemProduct();

                $targetLineItem->getDescription()->setTranslation('default', \implode(', ', $configurations));
                $targetLineItem->setNumber($sourceProduct->getProductNumber());

                $product = new Product();
                $product->setPrimaryKey($sourceProduct->getId());
                $product->setNumber($sourceProduct->getProductNumber());

                $targetLineItem->setProduct($product);
            } elseif ($sourceLineItem->getType() === PromotionProcessor::LINE_ITEM_TYPE) {
                $targetLineItem = new Voucher();
            } else {
                $targetLineItem = new Text();
            }

            $targetLineItem->setPrimaryKey($sourceLineItem->getId());
            $targetLineItem->getLabel()->setTranslation('default', $sourceLineItem->getLabel());
            $targetLineItem->setQuantity($sourceLineItem->getQuantity());

            $sourcePrice = $sourceLineItem->getPrice();

            if ($sourcePrice instanceof CalculatedPrice) {
                $targetLineItem->setUnitPrice($sourcePrice->getUnitPrice());
                $targetLineItem->setUnitPriceNet($sourcePrice->getUnitPrice() - self::getCumulativeTaxes($sourcePrice) / \max($sourcePrice->getQuantity(), 1));
                $targetLineItem->setTotalPrice($sourcePrice->getTotalPrice());
                $targetLineItem->setTotalPriceNet($sourcePrice->getTotalPrice() - self::getCumulativeTaxes($sourcePrice));

                $calculatedTax = $sourcePrice->getCalculatedTaxes()->first();

                if ($calculatedTax instanceof CalculatedTax) {
                    $targetLineItem->setTaxRate($calculatedTax->getTaxRate());
                }
            }

            $targetLineItems->push([$targetLineItem]);
        }

        if (($sourceDelivery = $source->getDeliveries()->first()) instanceof OrderDeliveryEntity) {
            $sourcePrice = $sourceDelivery->getShippingCosts();

            $targetLineItem = new Shipping();
            $targetLineItem->getLabel()->setTranslation('default', 'shipping');
            $targetLineItem->setQuantity(1);

            $targetLineItem->setUnitPrice($sourcePrice->getUnitPrice());
            $targetLineItem->setUnitPriceNet($sourcePrice->getUnitPrice() - self::getCumulativeTaxes($sourcePrice) / \max($sourcePrice->getQuantity(), 1));
            $targetLineItem->setTotalPrice($sourcePrice->getTotalPrice());
            $targetLineItem->setTotalPriceNet($sourcePrice->getTotalPrice() - self::getCumulativeTaxes($sourcePrice));

            $calculatedTax = $sourcePrice->getCalculatedTaxes()->first();

            if ($calculatedTax instanceof CalculatedTax) {
                $targetLineItem->setTaxRate($calculatedTax->getTaxRate());
            }

            $targetLineItems->push([$targetLineItem]);
        }

        $customerComment = $source->getCustomerComment();

        if (\is_string($customerComment)) {
            $targetLineItem = new Text();

            $targetLineItem->getLabel()->setTranslation('default', 'Kommentar');
            $targetLineItem->getDescription()->setTranslation('default', $customerComment);

            $targetLineItems->push([$targetLineItem]);
        }

        return $targetLineItems;
    }

    protected static function getCumulativeTaxes(CalculatedPrice $sourcePrice): float
    {
        return (float) \array_sum($sourcePrice->getCalculatedTaxes()->map(fn (CalculatedTax $tax): float => $tax->getTax()));
    }
}