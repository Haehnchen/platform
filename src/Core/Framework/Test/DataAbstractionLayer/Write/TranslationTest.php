<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Write;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Write\FieldException\WriteStackException;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Exception\MissingTranslationLanguageException;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\Tax\TaxDefinition;

class TranslationTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $currencyRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var Context
     */
    private $context;

    protected function setUp()
    {
        $this->productRepository = $this->getContainer()->get('product.repository');
        $this->currencyRepository = $this->getContainer()->get('currency.repository');
        $this->languageRepository = $this->getContainer()->get('language.repository');
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
    }

    public function testCurrencyWithTranslationViaLocale(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_GB' => [
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        static::assertArraySubset(['name' => $name], $payload);
        static::assertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationViaLanguageIdSimpleNotation(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        static::assertArraySubset(['name' => $name], $payload);
        static::assertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationMergeViaLocaleAndLanguageId(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_GB' => [
                    'name' => $name,
                ],

                Defaults::LANGUAGE_SYSTEM => [
                    'shortName' => $shortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        static::assertArraySubset(['name' => $name], $payload);
        static::assertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationMergeOverwriteViaLocaleAndLanguageId(): void
    {
        $name = 'US Dollar';
        $shortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_GB' => [
                    'name' => $name,
                    'shortName' => 'should be overwritten',
                ],

                Defaults::LANGUAGE_SYSTEM => [
                    'shortName' => $shortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        static::assertArraySubset(['name' => $name], $payload);
        static::assertArraySubset(['shortName' => $shortName], $payload);
    }

    public function testCurrencyWithTranslationViaLocaleAndLanguageId(): void
    {
        $germanLanguageId = Uuid::uuid4()->getHex();
        $germanName = 'Amerikanischer Dollar';
        $germanShortName = 'US Dollar Deutsch';
        $englishName = 'US Dollar';
        $englishShortName = 'USD';

        $this->languageRepository->create(
            [[
                'id' => $germanLanguageId,
                'name' => 'de_DE',
                'locale' => [
                    'id' => Uuid::uuid4()->getHex(),
                    'code' => 'x-tst_DE2',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
                'translationCode' => [
                    'id' => Uuid::uuid4()->getHex(),
                    'code' => 'x-tst_DE3',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ]],
            $this->context
        );

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_GB' => [
                    'name' => $englishName,
                    'shortName' => $englishShortName,
                ],

                $germanLanguageId => [
                    'name' => $germanName,
                    'shortName' => $germanShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains($germanLanguageId, $languageIds);
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload1 = $translations->getPayloads()[0];
        $payload2 = $translations->getPayloads()[1];

        static::assertArraySubset(
            [
                'shortName' => $germanShortName,
                'name' => $germanName,
            ],
            $payload1
        );

        static::assertArraySubset(
            [
                'shortName' => $englishShortName,
                'name' => $englishName,
            ],
            $payload2
        );
    }

    public function testCurrencyTranslationWithCachingAndInvalidation(): void
    {
        $englishName = 'US Dollar';
        $englishShortName = 'USD';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_GB' => [
                    'name' => $englishName,
                    'shortName' => $englishShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $payload = $translations->getPayloads()[0];
        static::assertArraySubset(['name' => $englishName], $payload);
        static::assertArraySubset(['shortName' => $englishShortName], $payload);

        $germanLanguageId = Uuid::uuid4()->getHex();
        $data = [
            'id' => $germanLanguageId,
            'translationCode' => [
                'name' => 'Niederländisch',
                'code' => 'x-nl_NL',
                'territory' => 'Niederlande',
            ],
            'localeId' => Defaults::LOCALE_SYSTEM,
            'name' => 'nl_NL',
        ];

        $this->languageRepository->create([$data], $this->context);

        $nlName = 'Amerikaans Dollar';
        $nlShortName = 'US Dollar German';

        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => [
                    'name' => 'default',
                    'shortName' => 'def',
                ],
                'x-nl_NL' => [
                    'name' => $nlName,
                    'shortName' => $nlShortName,
                ],
            ],
        ];

        $result = $this->currencyRepository->create([$data], $this->context);

        $currencies = $result->getEventByDefinition(CurrencyDefinition::class);
        static::assertCount(1, $currencies->getIds());

        $translations = $result->getEventByDefinition(CurrencyTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains($germanLanguageId, $languageIds);

        $payload = $translations->getPayloads();

        static::assertArraySubset(['name' => 'default'], $payload[0]);
        static::assertArraySubset(['shortName' => 'def'], $payload[0]);

        static::assertArraySubset(['name' => $nlName], $payload[1]);
        static::assertArraySubset(['shortName' => $nlShortName], $payload[1]);
    }

    public function testCurrencyTranslationWithInvalidLocaleCode(): void
    {
        $data = [
            'factor' => 1,
            'symbol' => '$',
            'translations' => [
                'en_UK' => [
                    'name' => 'US Dollar',
                    'shortName' => 'USD',
                ],
            ],
        ];

        $this->expectException(LanguageNotFoundException::class);
        $this->currencyRepository->create([$data], $this->context);
    }

    public function testProductWithDifferentTranslations(): void
    {
        $germanLanguageId = Uuid::uuid4()->getHex();

        $result = $this->languageRepository->create(
            [[
                'id' => $germanLanguageId,
                'name' => 'de_DE',
                'localeId' => Defaults::LOCALE_SYSTEM_DE,
                'translationCode' => [
                    'id' => Uuid::uuid4()->getHex(),
                    'code' => 'x-de_DE2',
                    'name' => 'test name',
                    'territory' => 'test territory',
                ],
            ]],
            $this->context
        );

        $languages = $result->getEventByDefinition(LanguageDefinition::class);
        static::assertCount(1, array_unique($languages->getIds()));
        static::assertContains($germanLanguageId, $languages->getIds());

        $data = [
            [
                'id' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                'manufacturer' => [
                    'id' => 'e4e8988334a34bb48d397b41a611084f',
                    'name' => 'Das blaue Haus',
                    'link' => 'http://www.blaueshaus-shop.de',
                ],
                'tax' => [
                    'id' => 'fe4eb0fd92a7417ebf8720a5148aae64',
                    'taxRate' => 19,
                    'name' => '19%',
                ],
                'price' => [
                    'gross' => 7.9899999999999824,
                    'net' => 6.7142857142857,
                ],
                'translations' => [
                    $germanLanguageId => [
                        'id' => '4f1bcf3bc0fb4e62989e88b3bd37d1a2',
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'name' => 'Backform gelb',
                        'description' => 'inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.',
                    ],
                    Defaults::LANGUAGE_SYSTEM => [
                        'name' => 'Test En',
                    ],
                ],
                'cover' => [
                    'id' => 'd610dccf27754a7faa5c22d7368e6d8f',
                    'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                    'position' => 1,
                    'media' => [
                        'id' => '4b2252d11baa49f3a62e292888f5e439',
                        'title' => 'Backform-gelb',
                    ],
                ],
                'active' => true,
                'isCloseout' => false,
                'pseudoSales' => 0,
                'markAsTopseller' => false,
                'allowNotification' => false,
                'sales' => 0,
                'stock' => 45,
                'minStock' => 0,
                'position' => 0,
                'weight' => 0,
                'minPurchase' => 1,
                'shippingFree' => false,
                'purchasePrice' => 0,
            ],
        ];

        $result = $this->productRepository->create($data, $this->context);

        $products = $result->getEventByDefinition(ProductDefinition::class);
        static::assertCount(1, $products->getIds());

        $translations = $result->getEventByDefinition(ProductManufacturerTranslationDefinition::class);
        static::assertCount(1, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);

        $translations = $result->getEventByDefinition(ProductTranslationDefinition::class);
        static::assertCount(2, $translations->getIds());
        $languageIds = array_column($translations->getPayloads(), 'languageId');
        static::assertContains(Defaults::LANGUAGE_SYSTEM, $languageIds);
        static::assertContains($germanLanguageId, $languageIds);
    }

    public function testTranslationsAssociationOfMissingRoot(): void
    {
        /** @var EntityRepository $categoryRepository */
        $categoryRepository = $this->getContainer()->get('category.repository');

        $category = [
            'id' => Uuid::uuid4()->getHex(),
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
            ],
        ];
        $categoryRepository->create([$category], $this->context);

        /** @var CategoryEntity $catSystem */
        $catSystem = $categoryRepository->search(new Criteria([$category['id']]), $this->context)->first();

        static::assertNotNull($catSystem);
        static::assertEquals('system', $catSystem->getName());
        static::assertEquals('system', $catSystem->getViewData()->getName());
        static::assertCount(1, $catSystem->getTranslations());

        $deDeContext = new Context(new SourceContext(), [Defaults::CATALOG], [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM_DE, Defaults::LANGUAGE_SYSTEM]);
        /** @var CategoryEntity $catDeDe */
        $catDeDe = $categoryRepository->search(new Criteria([$category['id']]), $deDeContext)->first();

        static::assertNotNull($catDeDe);
        static::assertNull($catDeDe->getName());
        static::assertEquals('system', $catDeDe->getViewData()->getName());

        static::assertCount(1, $catDeDe->getTranslations());
    }

    public function testCascadeDeleteRootTranslation(): void
    {
        $rootId = Uuid::uuid4()->getHex();
        $id = Uuid::uuid4()->getHex();

        $this->addLanguage($id, $rootId);

        $categoryRepository = $this->getContainer()->get('category.repository');

        $catId = Uuid::uuid4()->getHex();
        $category = [
            'id' => $catId,
            'name' => 'system',
            'translations' => [
                $rootId => ['name' => 'root'],
                $id => ['name' => 'child'],
            ],
        ];

        $categoryRepository->create([$category], $this->context);

        $categoryTranslationRepository = $this->getContainer()->get('category_translation.repository');
        $deleteId = ['categoryId' => $catId, 'languageId' => $rootId];
        $categoryTranslationRepository->delete([$deleteId], $this->context);

        /* @var CategoryEntity $categoryResult */
        $categoryResult = $categoryRepository->search(new Criteria(['id' => $catId]), $this->context)->first();

        $translations = $categoryResult->getTranslations();
        static::assertCount(1, $translations);
        static::assertEquals(Defaults::LANGUAGE_SYSTEM, $translations->first()->getLanguageId());
    }

    public function testUpsert(): void
    {
        $data = [
            [
                'id' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                'manufacturer' => [
                    'id' => 'e4e8988334a34bb48d397b41a611084f',
                    'name' => 'Das blaue Haus',
                    'link' => 'http://www.blaueshaus-shop.de',
                ],
                'tax' => [
                    'id' => 'fe4eb0fd92a7417ebf8720a5148aae64',
                    'taxRate' => 19,
                    'name' => '19%',
                ],
                'price' => [
                    'gross' => 7.9899999999999824,
                    'net' => 6.7142857142857,
                ],
                'translations' => [
                    [
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'name' => 'Backform gelb',
                        'description' => 'inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.',
                        'descriptionLong' => '
    sors capulus se Quies, mox qui Sentus dum confirmo do iam. Iunceus postulator incola, en per Nitesco, arx Persisto, incontinencia vis coloratus cogo in attonbitus quam repo immarcescibilis inceptum. Ego Vena series sudo ac Nitidus. Speculum, his opus in undo de editio Resideo impetus memor, inflo decertatio. His Manus dilabor do, eia lumen, sed Desisto qua evello sono hinc, ars his misericordite.
    ',
                        'language' => [
                            'id' => Defaults::LANGUAGE_SYSTEM,
                            'name' => 'system',
                        ],
                    ],
                ],
                'media' => [
                    [
                        'id' => 'd610dccf27754a7faa5c22d7368e6d8f',
                        'productId' => '79dc5e0b5bd1404a9dec7841f6254c7e',
                        'isCover' => true,
                        'position' => 1,
                        'media' => [
                            'id' => '4b2252d11baa49f3a62e292888f5e439',
                            'name' => 'Backform-gelb',
                            'album' => [
                                'id' => 'a7104eb19fc649fa86cf6fe6c26ad65a',
                                'name' => 'Artikel',
                                'position' => 2,
                                'createThumbnails' => false,
                                'thumbnailSize' => '200x200;600x600;1280x1280',
                                'icon' => 'sprite-inbox',
                                'thumbnailHighDpi' => true,
                                'thumbnailQuality' => 90,
                                'thumbnailHighDpiQuality' => 60,
                            ],
                        ],
                    ],
                ],
                'active' => true,
                'isCloseout' => false,
                'pseudoSales' => 0,
                'markAsTopseller' => false,
                'allowNotification' => false,
                'sales' => 0,
                'stock' => 45,
                'minStock' => 0,
                'position' => 0,
                'weight' => 0,
                'minPurchase' => 1,
                'shippingFree' => false,
                'purchasePrice' => 0,
            ],
        ];
        $productRepo = $this->getContainer()->get('product.repository');
        $affected = $productRepo->upsert($data, Context::createDefaultContext());

        static::assertNotNull($affected->getEventByDefinition(LanguageDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(ProductTranslationDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(TaxDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductManufacturerDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(ProductManufacturerTranslationDefinition::class));

        static::assertNotNull($affected->getEventByDefinition(ProductMediaDefinition::class));
        static::assertNotNull($affected->getEventByDefinition(MediaDefinition::class));
    }

    public function testMissingTranslationLanguageViolation(): void
    {
        $categoryRepository = $this->getContainer()->get('category.repository');

        $cat = [
            'name' => 'foo',
            'translations' => [
                ['name' => 'translation without a language or languageId'],
            ],
        ];
        /* @var WriteStackException|null $exception */
        $exception = null;
        try {
            $categoryRepository->create([$cat], $this->context);
        } catch (WriteStackException $e) {
            $exception = $e;
        }

        static::assertInstanceOf(WriteStackException::class, $exception);
        $innerExceptions = $exception->getExceptions();
        static::assertInstanceOf(MissingTranslationLanguageException::class, $innerExceptions[0]);
    }

    private function addLanguage($id, $rootLanguageId = null): void
    {
        $languages = [];
        if ($rootLanguageId) {
            $languages[] = [
                'id' => $rootLanguageId,
                'name' => 'root language ' . $rootLanguageId,
                'localeId' => Defaults::LOCALE_SYSTEM,
                'translationCode' => [
                    'code' => 'x-tst_root_' . $rootLanguageId,
                    'name' => 'root iso name' . $rootLanguageId,
                    'territory' => 'root territory ' . $rootLanguageId,
                ],
            ];
        }

        $languages[] = [
            'id' => $id,
            'parentId' => $rootLanguageId,
            'name' => 'test language ' . $id,
            'localeId' => Defaults::LOCALE_SYSTEM,
            'translationCode' => [
                'code' => 'x-tst_' . $id,
                'name' => 'iso name' . $id,
                'territory' => 'territory ' . $id,
            ],
        ];

        $this->languageRepository->create($languages, $this->context);
    }
}
