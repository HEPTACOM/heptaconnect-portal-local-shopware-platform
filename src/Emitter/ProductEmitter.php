<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Emitter;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Emission\Contract\EmitterContract;
use Heptacom\HeptaConnect\Portal\Base\Mapping\Contract\MappingInterface;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationCollection;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\System\Language\LanguageEntity;
use Shopware\Core\System\Locale\LocaleEntity;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class ProductEmitter extends EmitterContract
{
    public function supports(): string
    {
        return Product::class;
    }

    protected function run(
        MappingInterface $mapping,
        EmitContextInterface $context
    ): ?DatasetEntityContract {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $salesChannelRepository = $dalAccess->repository('sales_channel');
        $request = $this->prepareRequest($salesChannelRepository, $dalAccess->getContext());
        /** @var RequestStack $requestStack */
        $requestStack = $container->get(RequestStack::class);

        try {
            if ($request instanceof Request) {
                $requestStack->push($request);
            }

            $source = $dalAccess->getContext()->disableCache(static function (Context $c) use ($mapping, $dalAccess): ?ProductEntity {
                return $dalAccess->read('product', [$mapping->getExternalId()], [
                    'translations.language.locale',
                    'cover',
                ], $c)->first();
            });
        } finally {
            if ($request instanceof Request) {
                $requestStack->pop();
            }
        }

        if (!$source instanceof ProductEntity) {
            throw new \Exception('Product was not found');
        }

        $target = (new Product())
            ->setActive($source->getActive())
            ->setNumber($source->getProductNumber())
            ->setInventory($source->getStock())
            ->setGtin($source->getEan() ?? '');

        $target->setPrimaryKey($source->getId());

        if (($translations = $source->getTranslations()) instanceof ProductTranslationCollection) {
            foreach ($translations->getElements() as $translation) {
                $language = $translation->getLanguage();

                if (!$language instanceof LanguageEntity) {
                    continue;
                }

                $locale = $language->getLocale();

                if (!$locale instanceof LocaleEntity) {
                    continue;
                }

                $name = $translation->getName();

                if (\is_null($name) || $name === '') {
                    continue;
                }

                if ($language->getId() === Defaults::LANGUAGE_SYSTEM) {
                    $target->getName()->setFallback($translation->getName());
                }

                $target->getName()->setTranslation($locale->getCode(), $name);
            }
        }

        return $target;
    }

    protected function prepareRequest(EntityRepositoryInterface $salesChannelRepository, Context $dalContext): ?Request
    {
        $criteria = new Criteria();
        $criteria->addAssociation('domains')->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsFilter('domains.url', null),
        ]));

        $salesChannel = $salesChannelRepository->search($criteria, $dalContext)->first();

        if (!$salesChannel instanceof SalesChannelEntity) {
            return null;
        }

        $domain = $salesChannel->getDomains()->first();

        if (!$domain instanceof SalesChannelDomainEntity) {
            return null;
        }

        return Request::create($domain->getUrl());
    }
}
