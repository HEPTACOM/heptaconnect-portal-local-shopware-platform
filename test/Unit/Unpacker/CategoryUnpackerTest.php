<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Unpacker;

use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Category;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalAccess;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\TranslationLocaleCache;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CategoryUnpacker;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Category\CategoryDefinition;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\CategoryUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Unpacker\TranslatableUnpacker
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\LocaleMatcher
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator
 */
class CategoryUnpackerTest extends TestCase
{
    public function testUpdatingWithParent(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $parentId = '134c489be0e24734828243244d51d90b';
        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')
            ->willReturnCallback(
                static fn (string $r, ?string $pk): bool => [
                    'category' => [
                        $id => true,
                        $parentId => true,
                    ],
                ][$r][$pk ?? ''] ?? false
        );

        $unpacker = new CategoryUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $parent = new Category();
        $parent->setPrimaryKey($parentId);

        $category = new Category();
        $category->setPrimaryKey($id);
        $category->setParent($parent);
        $category->getName()->setFallback('foobar');

        self::assertEquals([
            'id' => $id,
            'parentId' => $parentId,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'foobar',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($category));
    }

    public function testUpdatingWithoutParent(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')
            ->willReturnCallback(
                static fn (string $r, ?string $pk): bool => [
                    'category' => [
                        $id => true,
                    ],
                ][$r][$pk ?? ''] ?? false
            );

        $unpacker = new CategoryUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $category = new Category();
        $category->setPrimaryKey($id);
        $category->getName()->setFallback('foobar');

        self::assertEquals([
            'id' => $id,
            'parentId' => null,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'foobar',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($category));
    }

    public function testCreatingWithParent(): void
    {
        $cache = $this->createMock(TranslationLocaleCache::class);
        $cache->method('getLocales')->willReturn([
            '7faafd19715542acb601356550f3af9a' => 'de-DE',
            'e40e9fdb1dc547978d88cc629bc04442' => 'nl-NL',
            '4bfcafc124d349239369ef4f8749f48b' => 'en-GB',
        ]);

        $parentId = '134c489be0e24734828243244d51d90b';
        $id = 'f952a562cfc348cd9c83f29b0ca53e20';
        $dal = $this->createMock(DalAccess::class);
        $dal->method('idExists')->willReturn(false);

        $unpacker = new CategoryUnpacker(new TranslatableUnpacker($cache, new LocaleMatcher(new NullLogger()), new NullLogger()), $dal);

        $parent = new Category();
        $parent->setPrimaryKey($parentId);

        $category = new Category();
        $category->setPrimaryKey($id);
        $category->setParent($parent);
        $category->getName()->setFallback('foobar');

        self::assertEquals([
            'id' => $id,
            'parentId' => null,
            'type' => CategoryDefinition::TYPE_FOLDER,
            'translations' => [
                'de-DE' =>  [
                    'name' => 'foobar',
                ],
                'nl-NL' =>  [
                    'name' => 'foobar',
                ],
                'en-GB' =>  [
                    'name' => 'foobar',
                ],
            ],
        ], $unpacker->unpack($category));
    }
}
