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
    private CustomerPacker $customerPacker;

    public function __construct(CustomerPacker $customerPacker)
    {
        $this->customerPacker = $customerPacker;
    }

    public function supports(): string
    {
        return Customer::class;
    }

    protected function run(
        string $externalId,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        return $this->customerPacker->pack($externalId, $context->getStorage());
    }
}
