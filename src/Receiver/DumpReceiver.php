<?php

declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Base\File\FileReferenceContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\MediaCollection;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Product\Product;
use Heptacom\HeptaConnect\Portal\Base\File\FileReferenceResolverContract;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Ramsey\Uuid\Uuid;

abstract class DumpReceiver extends ReceiverContract
{
    private FileReferenceResolverContract $fileReferenceResolver;

    private string $supports;

    /**
     * @var class-string<\Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract>
     */
    public function __construct(FileReferenceResolverContract $fileReferenceResolver, string $supports)
    {
        $this->supports = $supports;
        $this->fileReferenceResolver = $fileReferenceResolver;
    }

    public function supports(): string
    {
        return $this->supports;
    }

    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $id = PrimaryKeyGenerator::generatePrimaryKey($entity, '0ff4e0c2-fc66-4c40-a572-66dee8195f09') ?? (string) Uuid::uuid4()->getHex();
        $className = \basename(\str_replace('\\', '/', (string) $this->getSupportedEntityType()));
        $dumpDir = __DIR__ . '/../../__dump/' . $className . '/';

        if (!\is_dir($dumpDir) && !@\mkdir($dumpDir, 0777, true)) {
            return;
        }

        $content = \json_encode($entity, \JSON_PRETTY_PRINT | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        \file_put_contents($dumpDir . $id . '.json', $content);
        $mediaDir = $dumpDir . 'media/';

        if (!(\is_dir($mediaDir) || @\mkdir($mediaDir, 0777, true))) {
            return;
        }

        foreach ($this->getMedias($entity) as $mediaIndex => $media) {
            $fileReference = $media->getFile();

            if (!$fileReference instanceof FileReferenceContract) {
                continue;
            }

            $file = $this->fileReferenceResolver->resolve($fileReference);
            $ext = \explode('/', $media->getMimeType(), 2)[1] ?? 'bin';

            \file_put_contents($mediaDir . $mediaIndex . '_' . $id . '.' . $ext, $file->getContents());
        }

        $entity->setPrimaryKey($id);
    }

    /**
     * @return iterable<Media>|Media[]
     */
    private function getMedias(DatasetEntityContract $entity): iterable
    {
        if ($entity instanceof Media) {
            yield $entity;
        }

        if ($entity->hasAttached(Media::class)) {
            /** @var Media $media */
            $media = $entity->getAttachment(Media::class);
            yield from $this->getMedias($media);
        }

        if ($entity->hasAttached(MediaCollection::class)) {
            /** @var MediaCollection $medias */
            $medias = $entity->getAttachment(MediaCollection::class);

            yield from $medias;
        }

        if ($entity instanceof Product) {
            yield from $entity->getMedias();
        }
    }
}
