<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Serializer;

use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Response\Type\Api\JsonType;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AssociationInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Internal;
use Shopware\Core\Framework\Struct\Collection;
use Shopware\Core\Framework\Struct\Struct;
use Symfony\Component\Serializer\Serializer;

class JsonApiEncoder
{
    /**
     * @var Serializer
     */
    private $viewDataSerializer;

    public function __construct(Serializer $viewDataSerializer)
    {
        $this->viewDataSerializer = $viewDataSerializer;
    }

    /**
     * @param EntityCollection|Entity|null $data
     *
     * @throws UnsupportedEncoderInputException
     */
    public function encode(string $definition, $data, string $baseUrl, array $metaData = []): string
    {
        $entities = new SerializedCollection();

        if (!$data instanceof EntityCollection && !$data instanceof Entity) {
            throw new UnsupportedEncoderInputException();
        }

        $this->encodeData($definition, $data, $entities, $baseUrl);

        $entities->setSingle($data instanceof Entity);

        $data = array_merge($entities->jsonSerialize(), $metaData);

        return json_encode($data, JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param EntityCollection|Entity|null $data
     */
    protected function encodeData(string $definition, $data, SerializedCollection $entities, string $baseUrl): void
    {
        if ($data === null) {
            return;
        }

        if ($data instanceof EntityCollection) {
            foreach ($data as $entity) {
                $serialized = $this->serializeEntity($entities, $definition, $entity, $baseUrl);

                $entities->addData($serialized);
            }

            return;
        }

        $serialized = $this->serializeEntity($entities, $definition, $data, $baseUrl);
        $entities->addData($serialized);
    }

    protected function serializeEntity(SerializedCollection $entities, string $definition, Entity $entity, string $baseUrl): SerializedEntity
    {
        /** @var string|EntityDefinition $definition */
        $included = $entities->contains($entity->getUniqueIdentifier(), $definition::getEntityName());

        if ($included) {
            return $entities->get($entity->getUniqueIdentifier(), $definition::getEntityName());
        }

        /** @var Field[] $fields */
        $fields = $definition::getFields();

        $serialized = new SerializedEntity($entity->getUniqueIdentifier(), $definition::getEntityName());

        $self = $baseUrl . '/' . $this->camelCaseToSnailCase($definition::getEntityName()) . '/' . $entity->getUniqueIdentifier();
        $serialized->addLink('self', $self);

        /** @var string|int $propertyName */
        foreach ($fields as $propertyName => $field) {
            if ($propertyName === 'id' || $field->is(Internal::class)) {
                continue;
            }

            $isExtension = $field->is(Extension::class);

            if ($isExtension) {
                $value = $entity->getExtension($propertyName);
            } else {
                try {
                    $value = $entity->get($propertyName);
                } catch (\Throwable $e) {
                    continue;
                }
            }

            if (!$field instanceof AssociationInterface) {
                $value = $this->formatAttributeValue($value);

                if ($isExtension) {
                    $serialized->addExtension($propertyName, $value);
                } else {
                    $serialized->addAttribute($propertyName, $value);
                }

                continue;
            }

            $this->addRelationships($serialized, $entities, $baseUrl, $self, $field, $value, $propertyName);
        }
        if ($entity->getViewData() !== null) {
            $normalized = $this->viewDataSerializer->normalize($entity->getViewData());
            $data = JsonType::format($normalized);
            $serialized->addMeta('viewData', $data);
        }

        return $serialized;
    }

    /**
     * @param Entity|EntityCollection|null $value
     */
    protected function addRelationships(
        SerializedEntity $serialized,
        SerializedCollection $entities,
        string $baseUrl,
        string $self,
        Field $field,
        $value,
        string $propertyName
    ): void {
        if ($field instanceof TranslationsAssociationField) {
            $this->addTranslationRelationship($serialized, $entities, $baseUrl, $self, $field, $value, $propertyName);

            return;
        }

        if ($field instanceof ManyToOneAssociationField) {
            $this->addManyToOneRelationship($serialized, $entities, $baseUrl, $self, $field, $value, $propertyName);

            return;
        }

        if ($field instanceof OneToManyAssociationField) {
            $this->addOneToManyRelationship($serialized, $entities, $baseUrl, $self, $field, $value, $propertyName);

            return;
        }

        if ($field instanceof ManyToManyAssociationField) {
            $this->addManyToManyRelationship($serialized, $entities, $baseUrl, $self, $field, $value, $propertyName);

            return;
        }
    }

    protected function camelCaseToSnailCase(string $input): string
    {
        $input = str_replace('_', '-', $input);

        return \ltrim(\strtolower(\preg_replace('/[A-Z]/', '-$0', $input)), '-');
    }

    private function formatAttributeValue($value)
    {
        if ($value instanceof \DateTime) {
            return $value->format(\DateTime::ATOM);
        }

        if ($value instanceof Collection) {
            $formatted = [];

            foreach ($value as $key => $nested) {
                $data = [];

                $keys = json_decode(json_encode($nested), true);
                unset($keys['_class']);

                foreach ($keys as $property => $tmp) {
                    $data[$property] = $this->formatAttributeValue($nested->get($property));
                }
                $formatted[$key] = $data;
            }

            return $formatted;
        }
        if ($value instanceof Struct) {
            $value = \json_decode(\json_encode($value), true);
            unset($value['_class'], $value['extensions']);
        }

        return $value;
    }

    private function addManyToManyRelationship(SerializedEntity $serialized, SerializedCollection $entities, string $baseUrl, string $self, ManyToManyAssociationField $field, $value, string $propertyName): void
    {
        $foreignKey = [];

        if ($value instanceof EntityCollection) {
            $reference = $field->getReferenceDefinition();
            foreach ($value as $nestedEntity) {
                $nested = $this->serializeEntity($entities, $reference, $nestedEntity, $baseUrl);

                $entities->addIncluded($nested);

                $foreignKey[] = [
                    'id' => $nestedEntity->getId(),
                    'type' => $reference::getEntityName(),
                ];
            }
        }

        $serialized->addRelationship(
            $propertyName,
            [
                'data' => $foreignKey,
                'links' => [
                    'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                ],
            ]
        );
    }

    private function addOneToManyRelationship(SerializedEntity $serialized, SerializedCollection $entities, string $baseUrl, string $self, OneToManyAssociationField $field, $value, string $propertyName): void
    {
        $foreignKey = [];

        if ($value instanceof EntityCollection) {
            $reference = $field->getReferenceClass();
            foreach ($value as $nestedEntity) {
                $nested = $this->serializeEntity($entities, $reference, $nestedEntity, $baseUrl);

                $entities->addIncluded($nested);

                $foreignKey[] = [
                    'id' => $nested->getId(),
                    'type' => $reference::getEntityName(),
                ];
            }
        }

        $serialized->addRelationship(
            $propertyName,
            [
                'data' => $foreignKey,
                'links' => [
                    'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                ],
            ]
        );
    }

    private function addManyToOneRelationship(SerializedEntity $serialized, SerializedCollection $entities, string $baseUrl, string $self, ManyToOneAssociationField $field, $value, string $propertyName): void
    {
        $foreignKey = null;

        if ($value instanceof Entity) {
            $nested = $this->serializeEntity($entities, $field->getReferenceClass(), $value, $baseUrl);
            $entities->addIncluded($nested);

            $foreignKey = [
                'id' => $nested->getId(),
                'type' => $field->getReferenceClass()::getEntityName(),
            ];
        }

        $serialized->addRelationship(
            $propertyName,
            [
                'data' => $foreignKey,
                'links' => [
                    'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                ],
            ]
        );
    }

    private function addTranslationRelationship(SerializedEntity $serialized, SerializedCollection $entities, string $baseUrl, string $self, TranslationsAssociationField $field, $value, string $propertyName): void
    {
        $foreignKey = null;
        $reference = $field->getReferenceClass();

        if ($value instanceof EntityCollection) {
            foreach ($value as $nestedEntity) {
                $nested = $this->serializeEntity($entities, $reference, $nestedEntity, $baseUrl);

                $entities->addIncluded($nested);

                $foreignKey[] = [
                    'id' => $nested->getId(),
                    'type' => $reference::getEntityName(),
                ];
            }
        }

        $serialized->addRelationship(
            $propertyName,
            [
                'data' => $foreignKey,
                'links' => [
                    'related' => $self . '/' . $this->camelCaseToSnailCase($propertyName),
                ],
            ]
        );
    }
}
