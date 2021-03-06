<?php declare(strict_types=1);

namespace Shopware\Core\Content\Catalog;

use Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation\CatalogTranslationDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCatalog\SalesChannelCatalogDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CatalogDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'catalog';
    }

    public static function getCollectionClass(): string
    {
        return CatalogCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CatalogEntity::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CatalogTranslationDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new TranslatedField('name'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('categories', CategoryDefinition::class, 'catalog_id', false, 'id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(CategoryTranslationDefinition::class, 'category_id', 'categoryTranslations'),
            (new OneToManyAssociationField('products', ProductDefinition::class, 'catalog_id', false, 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturers', ProductManufacturerDefinition::class, 'catalog_id', false, 'id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(ProductManufacturerTranslationDefinition::class, 'product_manufacturer_id', 'productManufacturerTranslations'),
            (new OneToManyAssociationField('productMedia', ProductMediaDefinition::class, 'catalog_id', false, 'id'))->addFlags(new CascadeDelete()),
            new TranslationsAssociationField(ProductTranslationDefinition::class, 'product_id', 'productTranslations'),
            (new TranslationsAssociationField(CatalogTranslationDefinition::class, 'catalog_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCatalogDefinition::class, false, 'catalog_id', 'sales_channel_id'),
        ]);
    }
}
