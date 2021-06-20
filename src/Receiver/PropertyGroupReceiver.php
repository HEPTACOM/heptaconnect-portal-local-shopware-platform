<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyGroup;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyGroupUnpacker;

class PropertyGroupReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private PropertyGroupUnpacker $propertyGroupUnpacker;

    public function __construct(DalAccess $dal, PropertyGroupUnpacker $propertyGroupUnpacker)
    {
        $this->dal = $dal;
        $this->propertyGroupUnpacker = $propertyGroupUnpacker;
    }

    public function supports(): string
    {
        return PropertyGroup::class;
    }

    /**
     * @param PropertyGroup $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $this->dal->repository('property_group')->upsert([$this->propertyGroupUnpacker->unpack($entity)], $this->dal->getContext());
    }
}
