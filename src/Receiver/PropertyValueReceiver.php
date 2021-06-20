<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Property\PropertyValue;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\PropertyValueUnpacker;

class PropertyValueReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private PropertyValueUnpacker $propertyValueUnpacker;

    public function __construct(DalAccess $dal, PropertyValueUnpacker $propertyValueUnpacker)
    {
        $this->dal = $dal;
        $this->propertyValueUnpacker = $propertyValueUnpacker;
    }

    public function supports(): string
    {
        return PropertyValue::class;
    }

    /**
     * @param PropertyValue $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $this->dal->repository('property_group_option')->upsert([$this->propertyValueUnpacker->unpack($entity)], $this->dal->getContext());
    }
}
