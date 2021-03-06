<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Unit;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Defaults;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationCollection;
use Shopware\Core\System\Unit\UnitEntity;

class UnitEmitter extends EmitterContract
{
    private DalAccess $dal;

    public function __construct(DalAccess $dal)
    {
        $this->dal = $dal;
    }

    public function supports(): string
    {
        return Unit::class;
    }

    protected function run(
        string $externalId,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $source = $this->dal->read('unit', [$externalId], ['translations.language.locale'])->first();

        if (!$source instanceof UnitEntity) {
            return null;
        }

        $target = new Unit();
        $target->setPrimaryKey($source->getId());
        $target->setSymbol($source->getShortCode());

        $translations = $source->getTranslations();
        if ($translations instanceof UnitTranslationCollection) {
            foreach ($translations->getElements() as $translation) {
                $language = $translation->getLanguage();

                if (!$language instanceof LanguageEntity) {
                    continue;
                }

                $locale = $language->getLocale();

                if (!$locale instanceof LocaleEntity) {
                    continue;
                }

                if ($language->getId() === Defaults::LANGUAGE_SYSTEM) {
                    $target->getName()->setFallback($translation->getName());
                }

                $target->getName()->setTranslation($locale->getCode(), $translation->getName() ?? '');
            }
        }

        return $target;
    }
}
