<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaCollection extends EntityCollection
{
    public function getUserIds(): array
    {
        return $this->fmap(function (MediaEntity $media) {
            return $media->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (MediaEntity $media) use ($id) {
            return $media->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaEntity::class;
    }
}
