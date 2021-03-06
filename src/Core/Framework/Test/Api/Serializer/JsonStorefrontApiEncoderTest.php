<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Serializer;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\Api\Exception\UnsupportedEncoderInputException;
use Shopware\Core\Framework\Api\Serializer\JsonStorefrontApiEncoder;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\User\UserDefinition;

class JsonStorefrontApiEncoderTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @var JsonStorefrontApiEncoder
     */
    private $encoder;

    public function setUp()
    {
        $this->encoder = $this->getContainer()->get(JsonStorefrontApiEncoder::class);
    }

    public function emptyInputProvider(): array
    {
        return [
            [null],
            ['string'],
            [1],
            [false],
            [new \DateTime()],
            [1.1],
        ];
    }

    /**
     * @dataProvider emptyInputProvider
     *
     * @param mixed $input
     *
     * @throws UnsupportedEncoderInputException
     */
    public function testEncodeWithEmptyInput($input): void
    {
        $this->expectException(UnsupportedEncoderInputException::class);

        $this->encoder->encode(ProductDefinition::class, $input, '/storefront-api');
    }

    public function testEncodeStruct(): void
    {
        $struct = new MediaEntity();
        $struct->setId('1d23c1b0-15bf-43fb-97e8-9008cf42d6fe');
        $struct->setTitle('Manufacturer');
        $struct->setMimeType('image/png');
        $struct->setFileExtension('png');
        $struct->setFileSize(310818);

        $struct->setAlt('A media object description');

        $struct->setCreatedAt(date_create_from_format(\DateTime::ATOM, '2018-01-15T08:01:16+00:00'));

        $expected = [
            'data' => [
                'id' => '1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                'type' => 'media',
                'attributes' => [
                    'title' => 'Manufacturer',
                    'alt' => 'A media object description',
                    'mimeType' => 'image/png',
                    'fileExtension' => 'png',
                    'fileSize' => 310818,
                    'metaData' => null,
                    'createdAt' => '2018-01-15T08:01:16+00:00',
                    'updatedAt' => null,
                    'userId' => null,
                    'url' => '',
                    'hasFile' => false,
                    'fileName' => null,
                    'mediaType' => null,
                    'uploadedAt' => null,
                    'mediaFolderId' => null,
                ],
                'links' => [
                    'self' => '/api/media/1d23c1b0-15bf-43fb-97e8-9008cf42d6fe',
                ],
                'relationships' => [],
                'meta' => null,
            ],
            'included' => [],
        ];

        $actual = $this->encoder->encode(MediaDefinition::class, $struct, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToOneRelationship(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToOneRelationshipExpectation.php';
        $expected = $this->removeRelationships($expected);

        $actual = $this->encoder->encode(MediaDefinition::class, $struct, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeStructWithToManyRelationships(): void
    {
        $struct = include __DIR__ . '/fixtures/testBasicWithToManyRelationships.php';
        $expected = include __DIR__ . '/fixtures/testBasicWithToManyRelationshipsExpectation.php';
        $expected = $this->removeRelationships($expected);

        $actual = $this->encoder->encode(UserDefinition::class, $struct, '/api');

        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeCollectionWithToOneRelationship(): void
    {
        $collection = include __DIR__ . '/fixtures/testCollectionWithToOneRelationship.php';
        $expected = include __DIR__ . '/fixtures/testCollectionWithToOneRelationshipExpectation.php';
        $expected = $this->removeRelationships($expected);

        $actual = $this->encoder->encode(MediaDefinition::class, $collection, '/api');
        static::assertEquals($expected, json_decode($actual, true));
    }

    public function testEncodeMainResourceShouldNotBeInIncluded(): void
    {
        $struct = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncluded.php';
        $expected = include __DIR__ . '/fixtures/testMainResourceShouldNotBeInIncludedExpectation.php';
        $expected = $this->removeRelationships($expected);

        $actual = $this->encoder->encode(UserDefinition::class, $struct, '/api');

        static::assertEquals($expected, json_decode($actual, true));
    }

    private function removeRelationships(array $data): array
    {
        foreach ($data['included'] as $key => $value) {
            $value['relationships'] = [];
            $data['included'][$key] = $value;
        }

        if (isset($data['data']['attributes'])) {
            $data['data']['relationships'] = [];
        } else {
            foreach ($data['data'] as $key => $value) {
                $value['relationships'] = [];
                $data['data'][$key] = $value;
            }
        }

        return $data;
    }
}
