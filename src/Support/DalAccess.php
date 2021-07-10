<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support;

use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Portal;
use Psr\Container\ContainerInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;

class DalAccess
{
    public const EXTENSION_HEPTACONNECT_CONTEXT = 'heptaconnectContext';

    private ContainerInterface $container;

    private string $configDalIndexingMode;

    public function __construct(ContainerInterface $container, string $configDalIndexingMode)
    {
        $this->container = $container;
        $this->configDalIndexingMode = $configDalIndexingMode;
    }

    public function repository(string $name): EntityRepositoryInterface
    {
        /** @var EntityRepositoryInterface $result */
        $result = $this->container->get($name.'.repository');

        return $result;
    }

    public function idExists(string $reponame, ?string $primaryKey, ?Context $context = null): bool
    {
        if (!\is_string($primaryKey) || !Uuid::isValid($primaryKey)) {
            return false;
        }

        $repo = $this->repository($reponame);

        return $repo
                ->searchIds(new Criteria([$primaryKey]), $context ?? $this->getContext())
                ->getTotal() > 0;
    }

    /**
     * @param string[] $ids
     * @param string[] $associations
     */
    public function read(
        string $repository,
        array $ids,
        array $associations = [],
        ?Context $context = null
    ): EntityCollection {
        $ids = \array_filter($ids, [Uuid::class, 'isValid']);

        if ($ids === []) {
            return new EntityCollection();
        }

        $criteria = new Criteria($ids);
        $criteria->setLimit(\count($ids));
        $criteria->addAssociations($associations);

        return $this->repository($repository)
            ->search($criteria, $context ?? $this->getContext())
            ->getEntities();
    }

    public function getContext(): Context
    {
        $result = Context::createDefaultContext();

        $result->addExtension(self::EXTENSION_HEPTACONNECT_CONTEXT, new ArrayStruct());

        switch ($this->configDalIndexingMode) {
            case Portal::DAL_INDEX_MODE_NONE:
                $result->addExtension(EntityIndexerRegistry::DISABLE_INDEXING, new ArrayStruct());
                break;
            case Portal::DAL_INDEX_MODE_QUEUE:
                $result->addExtension(EntityIndexerRegistry::USE_INDEXING_QUEUE, new ArrayStruct());
                break;
        }

        if (\method_exists(Context::class, 'disableCache')) {
            return $result->disableCache(static fn (Context $context): Context => clone $context);
        }

        return $result;
    }
}
