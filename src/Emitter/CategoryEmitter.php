<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;

class CategoryEmitter extends EmitterContract
{
    public function supports(): string
    {
        return Category::class;
    }

    protected function run(
        MappingInterface $mapping,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $source = $dalAccess->read('category', [$mapping->getExternalId()], ['translations.language.locale'])->first();

        if (!$source instanceof CategoryEntity) {
            return null;
        }

        $target = new Category();
        $target->setPrimaryKey($source->getId());

        $translations = $source->getTranslations();
        if ($translations instanceof CategoryTranslationCollection) {
            foreach ($translations->getElements() as $translation) {
                $language = $translation->getLanguage();

                if (!$language instanceof LanguageEntity) {
                    continue;
                }

                $locale = $language->getLocale();

                if (!$locale instanceof LocaleEntity) {
                    continue;
                }

                $target->getName()->setTranslation($locale->getCode(), $translation->getName() ?? '');
            }
        }

        if (\is_string($source->getParentId())) {
            $parent = new Category();
            $parent->setPrimaryKey($source->getParentId());
            $target->setParent($parent);
        }

        return $target;
    }
}
