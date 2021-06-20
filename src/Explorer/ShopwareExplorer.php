<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Explorer;

use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExploreContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Exploration\Contract\ExplorerContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\RepositoryIterator;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

abstract class ShopwareExplorer extends ExplorerContract
{
    private DalAccess $dal;

    public function __construct(DalAccess $dal)
    {
        $this->dal = $dal;
    }

    protected function run(ExploreContextInterface $context): iterable
    {
        $repository = $this->dal->repository($this->getRepositoryName());
        /** @var RepositoryIterator $iterator */
        $iterator = new RepositoryIterator($repository, clone $this->dal->getContext());

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
