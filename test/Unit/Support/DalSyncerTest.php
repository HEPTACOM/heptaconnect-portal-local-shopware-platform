<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Support;

use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Api\Sync\SyncResult;
use Shopware\Core\Framework\Api\Sync\SyncServiceInterface;
use Shopware\Core\Framework\Context;

/**
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\DalSyncer
 * @covers \Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Test\Unit\Support\DalSyncerTest::testThrowExceptionWhenKeysAreDuplicateOnUpsert
 */
class DalSyncerTest extends TestCase
{
    public function testThrowExceptionWhenKeysAreDuplicateOnUpsert(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        static::expectExceptionMessage('Sync operation key "cbbb15ab5efe4209b79137b13e973b4a" is already in use and cannot be added');

        $dalSyncer = DalSyncer::make($sync, $context, $logger);

        $dalSyncer->upsert('foobar', [['id' => '062ae7d2d4ed4172ae78e3bdc21ad81c']], 'cbbb15ab5efe4209b79137b13e973b4a');
        $dalSyncer->upsert('foobar', [['id' => '339820e3c38b4f629c3b1b774ecb310e']], 'cbbb15ab5efe4209b79137b13e973b4a');
    }

    public function testThrowExceptionWhenKeysAreDuplicateOnDelete(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        static::expectExceptionMessage('Sync operation key "cbbb15ab5efe4209b79137b13e973b4a" is already in use and cannot be added');

        $dalSyncer = DalSyncer::make($sync, $context, $logger);

        $dalSyncer->delete('foobar', [['id' => '062ae7d2d4ed4172ae78e3bdc21ad81c']], 'cbbb15ab5efe4209b79137b13e973b4a');
        $dalSyncer->delete('foobar', [['id' => '339820e3c38b4f629c3b1b774ecb310e']], 'cbbb15ab5efe4209b79137b13e973b4a');
    }

    public function testThrowExceptionWhenKeysAreDuplicateOnUpsertAndDelete(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        static::expectExceptionMessage('Sync operation key "cbbb15ab5efe4209b79137b13e973b4a" is already in use and cannot be added');

        $dalSyncer = DalSyncer::make($sync, $context, $logger);

        $dalSyncer->upsert('foobar', [['id' => '062ae7d2d4ed4172ae78e3bdc21ad81c']], 'cbbb15ab5efe4209b79137b13e973b4a');
        $dalSyncer->delete('foobar', [['id' => '339820e3c38b4f629c3b1b774ecb310e']], 'cbbb15ab5efe4209b79137b13e973b4a');
    }

    public function testDontThrowExceptionWhenKeysAreDuplicateOnUpsertAndDeleteButPayloadIsEmpty(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $context = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        $dalSyncer = DalSyncer::make($sync, $context, $logger);

        $dalSyncer->upsert('foobar', [], 'cbbb15ab5efe4209b79137b13e973b4a');
        $dalSyncer->delete('foobar', [], 'cbbb15ab5efe4209b79137b13e973b4a');

        static::assertCount(0, $dalSyncer->getOperations());
    }

    public function testContextReplacement(): void
    {
        $sync = $this->createMock(SyncServiceInterface::class);
        $context = $this->createMock(Context::class);
        $newContext = $this->createMock(Context::class);
        $logger = $this->createMock(LoggerInterface::class);

        $dalSyncer = DalSyncer::make($sync, $context, $logger);
        $success = false;
        $sync->method('sync')->willReturnCallback(function ($_, $b) use ($newContext, &$success): SyncResult {
            $success = $b === $newContext;

            return new SyncResult([], true);
        });

        $dalSyncer->flush($newContext);

        static::assertTrue($success);
    }
}
