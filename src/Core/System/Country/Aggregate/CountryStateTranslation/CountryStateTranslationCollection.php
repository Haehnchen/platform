<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class CountryStateTranslationCollection extends EntityCollection
{
    public function getCountryStateIds(): array
    {
        return $this->fmap(function (CountryStateTranslationEntity $countryStateTranslation) {
            return $countryStateTranslation->getCountryStateId();
        });
    }

    public function filterByCountryStateId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationEntity $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getCountryStateId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (CountryStateTranslationEntity $countryStateTranslation) {
            return $countryStateTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (CountryStateTranslationEntity $countryStateTranslation) use ($id) {
            return $countryStateTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return CountryStateTranslationEntity::class;
    }
}
