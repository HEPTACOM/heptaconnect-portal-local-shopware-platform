<?php

declare(strict_types=1);

use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Portal\Base\Builder\FlowComponent;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer\CustomerPacker;

FlowComponent::emitter(Customer::class)->run(static fn (CustomerPacker $packer, string $id) => $packer->pack($id));
