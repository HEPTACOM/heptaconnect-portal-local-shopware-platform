<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Portal;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Defaults;

class UnitReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Unit::class;
    }

    /**
     * @param Unit $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $container = $context->getContainer($mapping);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $unitId = $entity->getPrimaryKey();
        /** @var Translator $translator */
        $translator = $container->get(Translator::class);
        $swContext = $dalAccess->getContext();
        $unitRepository = $dalAccess->repository('unit');

        $defaultName = $entity->getName()
            ->getTranslation($entity->getName()->getLocaleKeys()[0] ?? 'default');

        $translations = [];

        foreach ($entity->getName()->getLocaleKeys() as $localeKey) {
            $name = \trim($entity->getName()->getTranslation($localeKey));

            if ($name === '') {
                continue;
            }

            $translations[$localeKey] = [
                'name' => $name,
                'shortCode' => !empty($entity->getSymbol()) ? $entity->getSymbol() : $name,
            ];
        }

        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $defaultName ?? ('Unit '.$unitId),
            'shortCode' => !empty($entity->getSymbol()) ? $entity->getSymbol() : $name,
        ];

        if (!$dalAccess->idExists('unit', $unitId)) {
            $unitId ??= Uuid::uuid5('54f3002a-ccfa-4b72-a4d5-102a634824ae', $entity->getSymbol())->getHex();

            $unitRepository->create([[
                'id' => $unitId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $swContext);

            $entity->setPrimaryKey($unitId);
        } else {
            $unitRepository->update([[
                'id' => $unitId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $swContext);
        }

        $entity->setPrimaryKey($unitId);
    }
}
