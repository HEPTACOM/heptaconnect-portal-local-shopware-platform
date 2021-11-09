<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Customer\CustomerGroup;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;

class CustomerGroupReceiver extends ReceiverContract
{
    private DalAccess $dal;

    private Translator $translator;

    public function __construct(DalAccess $dal, Translator $translator)
    {
        $this->dal = $dal;
        $this->translator = $translator;
    }

    public function supports(): string
    {
        return CustomerGroup::class;
    }

    /**
     * @param CustomerGroup $entity
     */
    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $id = $entity->getPrimaryKey();
        $repository = $this->dal->repository('customer_group');
        $swContext = $this->dal->getContext();

        $defaultName = $entity->getName();
        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $defaultName,
        ];

        if (!$this->dal->idExists('customer_group', $id)) {
            $id ??= PrimaryKeyGenerator::generatePrimaryKey($entity, 'b72df67c-426d-42e1-846c-10f7815c1761') ?? (string) Uuid::uuid5('7ead4bd5-1d7c-4a5e-be6b-6f5f709dfb3c', $entity->getCode())->getHex();

            $repository->create([[
                'id' => $id,
                'display_gross' => true,
                'translations' => $this->translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $swContext);

            $entity->setPrimaryKey($id);
        } else {
            $repository->update([[
                'id' => $id,
                'translations' => $this->translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $swContext);
        }
    }
}
