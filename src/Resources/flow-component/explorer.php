<?php

declare(strict_types=1);

use Heptacom\HeptaConnect\Dataset\Ecommerce\Address\Salutation;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Currency\Currency;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Order\Order;
use Heptacom\HeptaConnect\Dataset\Ecommerce\PaymentMethod\PaymentMethod;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Dataset\Ecommerce\ShippingMethod\ShippingMethod;
use Heptacom\HeptaConnect\Portal\Base\Builder\FlowComponent;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;

FlowComponent::explorer(Category::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('category'));
FlowComponent::explorer(Currency::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('currency'));
FlowComponent::explorer(Customer::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('customer'));
FlowComponent::explorer(CustomerGroup::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('customer_group'));
FlowComponent::explorer(Order::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('order'));
FlowComponent::explorer(PaymentMethod::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('payment_method'));
FlowComponent::explorer(Product::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('product'));
FlowComponent::explorer(Salutation::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('salutation'));
FlowComponent::explorer(ShippingMethod::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('shipping_method'));
FlowComponent::explorer(Unit::class)->run(static fn (DalAccess $dal): iterable => $dal->ids('unit'));
