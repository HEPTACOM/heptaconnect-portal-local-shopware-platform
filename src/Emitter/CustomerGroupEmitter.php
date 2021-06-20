<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;

class CustomerGroupEmitter extends EmitterContract
{
    private DalAccess $dal;

    public function __construct(DalAccess $dal)
    {
        $this->dal = $dal;
    }

    public function supports(): string
    {
        return CustomerGroup::class;
    }

    protected function run(
        string $externalId,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $source = $this->dal->read('customer_group', [$externalId])->first();

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
