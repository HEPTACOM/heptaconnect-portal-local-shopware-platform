<?php
declare(strict_types=1);

namespace Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Receiver;

use Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract;
use Heptacom\HeptaConnect\Dataset\Ecommerce\Media\Media;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiveContextInterface;
use Heptacom\HeptaConnect\Portal\Base\Reception\Contract\ReceiverContract;
use Heptacom\HeptaConnect\Portal\Base\Serialization\Contract\DenormalizerInterface;
use Heptacom\HeptaConnect\Portal\Base\Serialization\Contract\NormalizationRegistryContract;
use Heptacom\HeptaConnect\Portal\LocalShopwarePlatform\Support\PrimaryKeyGenerator;
use Psr\Http\Message\StreamInterface;
use Ramsey\Uuid\Uuid;

abstract class DumpReceiver extends ReceiverContract
{
    private NormalizationRegistryContract $normalizationRegistry;

    private string $supports;

    /**
     * @var class-string<\Heptacom\HeptaConnect\Dataset\Base\Contract\DatasetEntityContract>
     */
    public function __construct(NormalizationRegistryContract $normalizationRegistry, string $supports)
    {
        $this->supports = $supports;
        $this->normalizationRegistry = $normalizationRegistry;
    }

    public function supports(): string
    {
        return $this->supports;
    }

    protected function run(
        DatasetEntityContract $entity,
        ReceiveContextInterface $context
    ): void {
        $id = PrimaryKeyGenerator::generatePrimaryKey($entity, '0ff4e0c2-fc66-4c40-a572-66dee8195f09') ?? Uuid::uuid4()->getHex();

        $className = \basename(\str_replace('\\', '/', $this->supports()));
        $dumpDir = __DIR__.'/../../__dump/'.$className.'/';

        if (!\is_dir($dumpDir) && !@\mkdir($dumpDir, 0777, true)) {
            return;
        }

        $content = \json_encode($entity, \JSON_PRETTY_PRINT | \JSON_PARTIAL_OUTPUT_ON_ERROR | \JSON_UNESCAPED_SLASHES);
        \file_put_contents($dumpDir.$id.'.json', $content);

        foreach ($this->getMedias($entity) as $mediaIndex => $media) {
            $mediaStream = $this->getMediaStream($media);

            if ($mediaStream instanceof StreamInterface) {
                $mediaDir = $dumpDir.'media/';
                $ext = \explode('/', $media->getMimeType(), 2)[1] ?? 'bin';

                if (\is_dir($mediaDir) || @\mkdir($mediaDir, 0777, true)) {
                    \file_put_contents($mediaDir.$mediaIndex.'_'.$id.'.'.$ext, $mediaStream->getContents());
                }
            }
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
    }

    private function getMediaStream(Media $media): ?StreamInterface
    {
        $denormalizer = $this->normalizationRegistry->getDenormalizer('stream');

        if (!$denormalizer instanceof DenormalizerInterface) {
            return null;
        }

        $stream = $denormalizer->denormalize($media->getNormalizedStream(), 'stream');

        if (!$stream instanceof StreamInterface) {
            return null;
        }

        return $stream;
    }
}
