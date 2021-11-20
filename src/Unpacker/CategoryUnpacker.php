<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Uuid\Uuid;

class CategoryUnpacker
{
    private TranslatableUnpacker $translatableUnpacker;

    private DalAccess $dal;

    public function __construct(TranslatableUnpacker $translatableUnpacker, DalAccess $dal)
    {
        $this->translatableUnpacker = $translatableUnpacker;
        $this->dal = $dal;
    }

    public function unpack(Category $category): array
    {
        $alreadyExists = $this->dal->idExists('category', $category->getPrimaryKey());
        $parent = $category->getParent();
        $parentId = null;

        if ($parent instanceof Category) {
            $parentId = $parent->getPrimaryKey();

            if (!$this->dal->idExists('category', $parentId)) {
                $parentId = null;
            }
        }

        $result = [
            'id' => $this->unpackId($category, $alreadyExists),
            'parentId' => $parentId,
            'translations' => $this->unpackTranslations($category),
        ];

        if (!$alreadyExists) {
            $result['type'] = $parentId ? CategoryDefinition::TYPE_PAGE : CategoryDefinition::TYPE_FOLDER;
        }

        return $result;
    }

    protected function unpackId(Category $category, bool $alreadyExists): ?string
    {
        if (!$alreadyExists) {
            return PrimaryKeyGenerator::generatePrimaryKey($category, 'b3acb4c2-a8e2-44a8-9eb1-a6c56e628ec5') ?? Uuid::randomHex();
        }

        return $category->getPrimaryKey();
    }

    protected function unpackTranslations(Category $category): array
    {
        return $this->translatableUnpacker->unpack($category->getName(), 'name');
    }
}
