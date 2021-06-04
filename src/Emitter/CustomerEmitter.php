<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\Customer;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Packer\CustomerPacker;

class CustomerEmitter extends EmitterContract
{
    public function supports(): string
    {
        return Customer::class;
    }

    protected function run(
        string $externalId,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $container = $context->getContainer();
        /** @var CustomerPacker $customerPacker */
        $customerPacker = $container->get(CustomerPacker::class);

        return $customerPacker->pack($externalId, $context->getStorage());
    }
}
