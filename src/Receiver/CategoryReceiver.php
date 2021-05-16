<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\Translator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryReceiver extends ReceiverContract
{
    public function supports(): string
    {
        return Category::class;
    }

    /**
     * @param Category $entity
     */
    protected function run(
        MappingInterface $mapping,
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $categoryId = $mapping->getExternalId();
        $container = $context->getContainer($mapping);
        /** @var Translator $translator */
        $translator = $container->get(Translator::class);
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $swContext = $dalAccess->getContext();
        $categoryRepository = $dalAccess->repository('category');

        $categoryParent = $entity->getParent();
        $translations = [];

        foreach ($entity->getName()->getLocaleKeys() as $localeKey) {
            $name = \trim($entity->getName()->getTranslation($localeKey, false) ?? '');

            if ($name === '') {
                continue;
            }

            $translations[$localeKey] = ['name' => $name];
        }

        $translations[Defaults::LANGUAGE_SYSTEM] = [
            'name' => $entity->getName()->getFallback() ?? ('Category '.$categoryId),
        ];

        $parentId = $categoryParent ? $categoryParent->getPrimaryKey() : null;

        if (!$dalAccess->idExists('category', $parentId)) {
            $parentId = null;
        }

        if (!$dalAccess->idExists('category', $categoryId)) {
            $categoryId ??= PrimaryKeyGenerator::generatePrimaryKey($entity, 'b3acb4c2-a8e2-44a8-9eb1-a6c56e628ec5') ?? Uuid::randomHex();

            $categoryRepository->create([[
                'id' => $categoryId,
                'type' => $parentId ? CategoryDefinition::TYPE_PAGE : CategoryDefinition::TYPE_FOLDER,
                'parentId' => $parentId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ]], $swContext);
            $entity->setPrimaryKey($categoryId);
        } else {
            $existingCategory = $dalAccess->read('category', [$categoryId])->first();

            if ($existingCategory instanceof CategoryEntity) {
                $parentId ??= $existingCategory->getParentId();
            }

            $payload = [
                'id' => $categoryId,
                'parentId' => $parentId,
                'translations' => $translator->exchangeLocaleKeysToLanguageKeys($translations),
            ];

            $categoryRepository->update([$payload], $swContext);
        }
    }
}
