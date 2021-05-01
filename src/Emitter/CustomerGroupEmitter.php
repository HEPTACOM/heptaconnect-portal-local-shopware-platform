<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Context;

class CustomerGroupEmitter extends EmitterContract
{
    public function supports(): string
    {
        return CustomerGroup::class;
    }

    protected function run(
        MappingInterface $mapping,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $externalId = $mapping->getExternalId();
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $source = $dalAccess->getContext()->disableCache(function (Context $context) use ($mapping, $dalAccess): ?CustomerGroupEntity {
            return $dalAccess->read('customer_group', [$mapping->getExternalId()], [], $context)->first();
        });

        if (!$source instanceof CustomerGroupEntity) {
            throw new \Exception(\sprintf('Customer group with id: %s not found.', $externalId));
        }

        $result = new CustomerGroup();
        $result->setPrimaryKey($source->getId());
        $result->setCode($source->getId());
        $result->setName($source->getTranslation('name') ?? $source->getName() ?? $source->getId());

        return $result;
    }
}
