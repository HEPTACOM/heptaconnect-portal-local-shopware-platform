<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExploreContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExplorerContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

abstract class ShopwareExplorer extends ExplorerContract
{
    protected function run(ExploreContextInterface $context): iterable
    {
        $container = $context->getContainer();
        /** @var DalAccess $dalAccess */
        $dalAccess = $container->get(DalAccess::class);
        $repository = $dalAccess->repository($this->getRepositoryName());
        /** @var RepositoryIterator $iterator */
        $iterator = $dalAccess->getContext()->disableCache(function (Context $dalContext) use ($repository) {
            return new RepositoryIterator($repository, clone $dalContext);
        });

        while (!\is_null($entities = $iterator->fetch())) {
            /** @var Entity $element */
            foreach ($entities->getElements() as $element) {
                // TODO: improve this
                echo \sprintf('%s | %s', $element->getId(), $element->getTranslation('name') ?? '').\PHP_EOL;

                yield $element->getId();
            }
        }
    }

    abstract protected function getRepositoryName(): string;
}
