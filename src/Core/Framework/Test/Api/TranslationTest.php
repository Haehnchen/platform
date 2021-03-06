<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\LanguageNotFoundException;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\Exception\MissingSystemTranslationException;
use Shopware\Core\System\Language\TranslationValidator;
use Shopware\Core\System\Locale\LocaleEntity;
use Symfony\Component\HttpFoundation\Response;

class TranslationTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    public function testNoOverride(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated', 'viewData' => ['name' => 'not translated']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ]
        );
    }

    public function testDefault(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM_DE => ['name' => 'german'],
                ],
            ]
        );
    }

    public function testDefault2(): void
    {
        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'name' => 'not translated',
            ]
        );
    }

    public function testDefaultAndExplicitSystem(): void
    {
        $this->assertTranslation(
            ['name' => 'default'],
            [
                'name' => 'default',
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                ],
            ]
        );
    }

    public function testFallback(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            ['name' => null, 'viewData' => ['name' => 'translated by fallback']],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $fallbackId => ['name' => 'translated by fallback'],
                ],
            ],
            $langId
        );
    }

    public function testDefaultFallback(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testWithLanguageIdParam(): void
    {
        $this->assertTranslation(
            ['name' => 'translated by default fallback'],
            [
                'translations' => [
                    ['languageId' => Defaults::LANGUAGE_SYSTEM, 'name' => 'translated by default fallback'],
                ],
            ]
        );
    }

    public function testOnlySystemLocaleIdentifier()
    {
        $localeRepo = $this->getContainer()->get('locale.repository');
        /** @var LocaleEntity $locale */
        $locale = $localeRepo->search(new Criteria([Defaults::TRANSLATION_CODE_SYSTEM]), Context::createDefaultContext())->first();

        $this->assertTranslation(
            ['name' => 'system translation'],
            [
                'translations' => [
                    $locale->getCode() => ['name' => 'system translation'],
                ],
            ]
        );
    }

    public function testEmptyLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = '';

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testInvalidUuidLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = 'foobar';

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', 'foobar');
        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testNonExistingLanguageIdError(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $headerName = $this->getLangHeaderName();
        $langId = Uuid::uuid4()->getHex();

        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);

        $langId = sprintf('id=%s', Uuid::uuid4()->getHex());
        $this->getClient()->request('GET', $baseResource, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(412, $response->getStatusCode());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(LanguageNotFoundException::LANGUAGE_NOT_FOUND_ERROR, $data['errors'][0]['code']);
    }

    public function testOverride(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            $langId
        );
    }

    public function testOverrideWithExtendedParams(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            ['id' => $langId, 'inherit' => true]
        );
    }

    public function testNoDefaultTranslation(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslationError(
            [
                [
                    'code' => MissingSystemTranslationException::VIOLATION_MISSING_SYSTEM_TRANSLATION,
                    'status' => '400',
                    'source' => [
                        'pointer' => '/translations/' . Defaults::LANGUAGE_SYSTEM,
                    ],
                ],
            ],
            [
                'translations' => [
                    $langId => ['name' => 'translated'],
                ],
            ],
            $langId
        );
    }

    public function testExplicitDefaultTranslation(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $this->assertTranslation(
            ['name' => 'not translated'],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'not translated'],
                    $langId => ['name' => 'translated'],
                ],
            ],
            Defaults::LANGUAGE_SYSTEM
        );
    }

    public function testPartialTranslationWithFallback(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => 'translated',
                'metaTitle' => null,
                'viewData' => [
                    'metaTitle' => 'translated by fallback',
                ],
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $langId => [
                        'name' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'translated by fallback',
                        'metaTitle' => 'translated by fallback',
                    ],
                ],
            ],
            $langId
        );
    }

    public function testChildTranslationWithoutRequiredField(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'name' => null,
                'metaTitle' => 'translated',
                'viewData' => [
                    'name' => 'only translated by fallback',
                    'metaTitle' => 'translated',
                ],
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $langId => [
                        'metaTitle' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'only translated by fallback',
                    ],
                ],
            ],
            $langId
        );
    }

    public function testChildTranslationLongText(): void
    {
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $this->assertTranslation(
            [
                'metaDescription' => 'translated',
                'name' => null,
                'viewData' => [
                    'name' => 'only translated by fallback',
                ],
            ],
            [
                'translations' => [
                    Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                    $langId => [
                        'metaDescription' => 'translated',
                    ],
                    $fallbackId => [
                        'name' => 'only translated by fallback',
                    ],
                ],
            ],
            $langId
        );
    }

    public function testWithOverrideInPatch(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();

        $notTranslated = [
            'id' => $id,
            'name' => 'not translated',
            'metaDescription' => 'not translated',
        ];

        $this->createLanguage($langId);

        $headerName = $this->getLangHeaderName();

        $this->getClient()->request('POST', $baseResource, $notTranslated);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->assertEntityExists($this->getClient(), 'category', $id);

        $translated = [
            'id' => $id,
            'name' => 'translated',
        ];

        $this->getClient()->request('PATCH', $baseResource . '/' . $id, $translated, [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);

        static::assertEquals($translated['name'], $responseData['data']['attributes']['name']);
        static::assertNull($responseData['data']['attributes']['metaDescription']);

        static::assertEquals($notTranslated['metaDescription'], $responseData['data']['meta']['viewData']['metaDescription']);
    }

    public function testDelete(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();

        $name = 'Test category';
        $translatedName = $name . '_translated';

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => $name],
                $langId => ['name' => $translatedName],
            ],
        ];

        $this->createLanguage($langId);

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $headerName = $this->getLangHeaderName();

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => Defaults::LANGUAGE_SYSTEM]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($name, $responseData['data']['attributes']['name']);

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals($translatedName, $responseData['data']['attributes']['name']);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $langId);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], [$headerName => $langId]);
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);
        static::assertEquals(null, $responseData['data']['attributes']['name']);
    }

    public function testDeleteSystemLanguageViolation(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];
        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM);
        $response = $this->getClient()->getResponse();
        static::assertEquals(400, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        static::assertEquals(TranslationValidator::VIOLATION_DELETE_SYSTEM_TRANSLATION, $data['errors'][0]['code']);
        static::assertEquals('/' . $id . '/translations/' . Defaults::LANGUAGE_SYSTEM, $data['errors'][0]['source']['pointer']);
    }

    public function testDeleteEntityWithOneRootTranslation(): void
    {
        /**
         * This works because the dal does not generate a `DeleteCommand` for the `CategoryTranslation`.
         * The translation is delete by the foreign key delete cascade.
         */
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $rootId = Uuid::uuid4()->getHex();

        $this->createLanguage($rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'Test category'],
            ],
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteNonSystemRootTranslations(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $rootDelete = Uuid::uuid4()->getHex();
        $this->createLanguage($rootDelete);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootDelete => ['name' => 'root delete'],
            ],
        ];
        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $rootDelete);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testDeleteChildLanguageTranslation(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $rootId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();

        $this->createLanguage($childId, $rootId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'system'],
                $rootId => ['name' => 'root'],
                $childId => ['name' => 'child'],
            ],
        ];
        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode());
        $this->assertEntityExists($this->getClient(), 'category', $id);

        $this->getClient()->request('DELETE', $baseResource . '/' . $id . '/translations/' . $childId);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());
    }

    public function testTranslationAssociation(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'test'],
            ],
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->getClient()->request('GET', $baseResource . '/' . $id);
        $response = $this->getClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $translations = $data['data']['relationships']['translations']['data'];
        static::assertCount(1, $translations);

        $expectedCombinedId = $id . '-' . Defaults::LANGUAGE_SYSTEM;
        static::assertEquals($expectedCombinedId, $translations[0]['id']);
        static::assertEquals('category_translation', $translations[0]['type']);
    }

    public function testTranslationAssociationOverride(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                $langId => ['name' => 'translated'],
            ],
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $headers = [$this->getLangHeaderName() => $langId];
        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], $headers);
        $response = $this->getClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $translations = $data['data']['relationships']['translations']['data'];
        static::assertCount(2, $translations);

        $expectedCombinedId = $id . '-' . $langId;
        static::assertEquals($expectedCombinedId, $translations[0]['id']);
        static::assertEquals('category_translation', $translations[0]['type']);

        $expectedCombinedId = $id . '-' . Defaults::LANGUAGE_SYSTEM;
        static::assertEquals($expectedCombinedId, $translations[1]['id']);
        static::assertEquals('category_translation', $translations[1]['type']);
    }

    public function testTranslationAssociationOverrideWithFallback(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $id = Uuid::uuid4()->getHex();
        $langId = Uuid::uuid4()->getHex();
        $fallbackId = Uuid::uuid4()->getHex();
        $this->createLanguage($langId, $fallbackId);

        $categoryData = [
            'id' => $id,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => 'default'],
                $fallbackId => ['name' => 'fallback'],
                $langId => ['name' => 'translated', 'description' => 'translated'],
            ],
        ];

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $headers = [$this->getLangHeaderName() => $langId];
        $this->getClient()->request('GET', $baseResource . '/' . $id, [], [], $headers);
        $response = $this->getClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode(), $response->getContent());

        $data = json_decode($response->getContent(), true);
        $translations = $data['data']['relationships']['translations']['data'];
        static::assertCount(3, $translations);

        $expectedCombinedId = $id . '-' . $langId;
        static::assertEquals($expectedCombinedId, $translations[0]['id']);
        static::assertEquals('category_translation', $translations[0]['type']);

        $expectedCombinedId = $id . '-' . $fallbackId;
        static::assertEquals($expectedCombinedId, $translations[1]['id']);
        static::assertEquals('category_translation', $translations[1]['type']);

        $expectedCombinedId = $id . '-' . Defaults::LANGUAGE_SYSTEM;
        static::assertEquals($expectedCombinedId, $translations[2]['id']);
        static::assertEquals('category_translation', $translations[2]['type']);
    }

    public function testMixedTranslationStatus(): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';
        $rootLangId = Uuid::uuid4()->getHex();
        $childLangId = Uuid::uuid4()->getHex();
        $this->createLanguage($childLangId, $rootLangId);

        $idSystem = Uuid::uuid4()->getHex();
        $system = [
            'id' => $idSystem,
            'name' => '1. system',
            'metaTitle' => 'only system',
        ];
        $this->getClient()->request('POST', $baseResource, $system);
        $this->assertEntityExists($this->getClient(), 'category', $idSystem);

        $idRoot = Uuid::uuid4()->getHex();
        $root = [
            'id' => $idRoot,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '2. system', 'metaTitle' => 'only system'],
                $rootLangId => ['name' => '2. root', 'metaDescription' => 'only root'],
            ],
        ];
        $this->getClient()->request('POST', $baseResource, $root);
        $this->assertEntityExists($this->getClient(), 'category', $idRoot);

        $idChild = Uuid::uuid4()->getHex();
        $childAndRoot = [
            'id' => $idChild,
            'translations' => [
                Defaults::LANGUAGE_SYSTEM => ['name' => '3. system', 'metaTitle' => 'only system'],
                $rootLangId => ['name' => '3. root', 'metaDescription' => 'only root'],
                $childLangId => ['name' => '3. child'],
            ],
        ];
        $this->getClient()->request('POST', $baseResource, $childAndRoot);
        $this->assertEntityExists($this->getClient(), 'category', $idChild);

        $headers = [
            'HTTP_ACCEPT' => 'application/json',
            $this->getLangHeaderName() => $childLangId,
        ];
        $this->getClient()->request('GET', $baseResource . '?sort=name', [], [], $headers);
        $response = $this->getClient()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $data = json_decode($response->getContent(), true)['data'];

        static::assertNull($data[0]['name']);
        static::assertNull($data[1]['name']);
        static::assertEquals('3. child', $data[2]['name']);

        static::assertNull($data[0]['metaTitle']);
        static::assertNull($data[1]['metaTitle']);
        static::assertNull($data[2]['metaTitle']);

        static::assertNull($data[0]['metaDescription']);
        static::assertNull($data[1]['metaDescription']);
        static::assertNull($data[2]['metaDescription']);

        static::assertEquals('1. system', $data[0]['viewData']['name']);
        static::assertEquals('2. root', $data[1]['viewData']['name']);
        static::assertEquals('3. child', $data[2]['viewData']['name']);

        static::assertEquals('only system', $data[0]['viewData']['metaTitle']);
        static::assertEquals('only system', $data[1]['viewData']['metaTitle']);
        static::assertEquals('only system', $data[2]['viewData']['metaTitle']);

        static::assertNull($data[0]['viewData']['metaDescription']);
        static::assertEquals('only root', $data[1]['viewData']['metaDescription']);
        static::assertEquals('only root', $data[2]['viewData']['metaDescription']);
    }

    private function getLangHeaderName(): string
    {
        return 'HTTP_' . strtoupper(str_replace('-', '_', PlatformRequest::HEADER_LANGUAGE_ID));
    }

    private function assertTranslationError(array $errors, array $data): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';

        $categoryData = [
            'id' => Uuid::uuid4()->getHex(),
        ];
        $categoryData = array_merge_recursive($categoryData, $data);

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(400, $response->getStatusCode(), $response->getContent());

        $responseData = json_decode($response->getContent(), true);
        static::assertCount(count($errors), $responseData['errors']);

        $actualErrors = array_map(function ($error) {
            $e = [
               'code' => $error['code'],
               'status' => $error['status'],
            ];
            if (isset($error['source'])) {
                $e['source'] = $error['source'];
            }

            return $e;
        }, $responseData['errors']);

        static::assertEquals($errors, $actualErrors);
    }

    private function assertTranslation(array $expectedTranslations, array $data, $langOverride = null): void
    {
        $baseResource = '/api/v' . PlatformRequest::API_VERSION . '/category';

        $categoryData = $data;
        if (!isset($categoryData['id'])) {
            $categoryData['id'] = Uuid::uuid4()->getHex();
        }

        $this->getClient()->request('POST', $baseResource, $categoryData);
        $response = $this->getClient()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), $response->getContent());

        $this->assertEntityExists($this->getClient(), 'category', $categoryData['id']);

        $headers = ['HTTP_ACCEPT' => 'application/json'];
        if ($langOverride) {
            $headers[$this->getLangHeaderName()] = $langOverride;
        }

        $this->getClient()->request('GET', $baseResource . '/' . $categoryData['id'], [], [], $headers);

        $response = $this->getClient()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $responseData = json_decode($response->getContent(), true);

        static::assertArrayHasKey('data', $responseData, $response->getContent());

        static::assertArraySubset($expectedTranslations, $responseData['data']);
    }

    private function createLanguage($langId, $fallbackId = null): void
    {
        $baseUrl = '/api/v' . PlatformRequest::API_VERSION;

        if ($fallbackId) {
            $fallbackLocaleId = Uuid::uuid4()->getHex();
            $parentLanguageData = [
                'id' => $fallbackId,
                'name' => 'test language ' . $fallbackId,
                'locale' => [
                    'id' => $fallbackLocaleId,
                    'code' => 'x-tst_' . $fallbackLocaleId,
                    'name' => 'Test locale ' . $fallbackLocaleId,
                    'territory' => 'Test territory ' . $fallbackLocaleId,
                ],
                'translationCodeId' => $fallbackLocaleId,
            ];
            $this->getClient()->request('POST', $baseUrl . '/language', $parentLanguageData);
            static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode());
        }

        $localeId = Uuid::uuid4()->getHex();
        $languageData = [
            'id' => $langId,
            'name' => 'test language ' . $langId,
            'parentId' => $fallbackId,
            'locale' => [
                'id' => $localeId,
                'code' => 'x-tst_' . $localeId,
                'name' => 'Test locale ' . $localeId,
                'territory' => 'Test territory ' . $localeId,
            ],
            'translationCodeId' => $localeId,
        ];

        $this->getClient()->request('POST', $baseUrl . '/language', $languageData);
        static::assertEquals(204, $this->getClient()->getResponse()->getStatusCode(), $this->getClient()->getResponse()->getContent());

        $this->getClient()->request('GET', $baseUrl . '/language/' . $langId);
    }
}
