<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Field;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueCountAggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\SourceContext;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class WhitelistRuleFieldTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var EntityRepository
     */
    private $repository;

    /**
     * @throws \Doctrine\DBAL\DBALException
     */
    public function setUp()
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->repository = $this->getContainer()->get('product.repository');
        parent::setUp();
    }

    public function testReadEntityWithWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'whitelistIds' => [$rule3],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$product1, $product2, $product3]);

        //context without rules should return the product
        $context = $this->createContextWithRules();
        $products = $this->repository->search($criteria, $context);
        static::assertFalse($products->has($product1));
        static::assertFalse($products->has($product2));
        static::assertTrue($products->has($product3));

        //context with rule which isn't added to the product should return the product
        $context = $this->createContextWithRules([$rule3]);
        $products = $this->repository->search($criteria, $context);
        static::assertFalse($products->has($product1));
        static::assertTrue($products->has($product2));
        static::assertTrue($products->has($product3));

        //context with rule which is added to the product should return the product
        $context = $this->createContextWithRules([$rule1]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($product1));
        static::assertFalse($products->has($product2));
        static::assertTrue($products->has($product3));

        $context = $this->createContextWithRules([$rule2]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($product1));
        static::assertFalse($products->has($product2));
        static::assertTrue($products->has($product3));

        $context = $this->createContextWithRules([$rule1, $rule2]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($product1));
        static::assertFalse($products->has($product2));
        static::assertTrue($products->has($product3));

        $context = $this->createContextWithRules([$rule1, $rule3]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($product1));
        static::assertTrue($products->has($product2));
        static::assertTrue($products->has($product3));
    }

    public function testInheritedWhitelist()
    {
        $parentId1 = Uuid::uuid4()->getHex();
        $productId1 = Uuid::uuid4()->getHex();

        $parentId2 = Uuid::uuid4()->getHex();
        $productId2 = Uuid::uuid4()->getHex();

        $parentId3 = Uuid::uuid4()->getHex();
        $productId3 = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parentId1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $productId1,
                'parentId' => $parentId1,
            ],

            [
                'id' => $parentId2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => $productId2,
                'parentId' => $parentId2,
                'whitelistIds' => [$rule1, $rule2],
            ],

            [
                'id' => $parentId3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test'],
            ],
            [
                'id' => $productId3,
                'parentId' => $parentId3,
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria([$productId1, $productId2, $productId3]);

        //context without rules should return the product
        $context = $this->createContextWithRules();
        $products = $this->repository->search($criteria, $context);
        static::assertFalse($products->has($productId1));
        static::assertFalse($products->has($productId2));
        static::assertTrue($products->has($productId3));

        //context with rule which isn't added to the product should return the product
        $context = $this->createContextWithRules([$rule3]);
        $products = $this->repository->search($criteria, $context);
        static::assertFalse($products->has($productId1));
        static::assertFalse($products->has($productId2));
        static::assertTrue($products->has($productId3));

        //context with rule which is added to the product should return the product
        $context = $this->createContextWithRules([$rule1]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($productId1));
        static::assertTrue($products->has($productId2));
        static::assertTrue($products->has($productId3));

        $context = $this->createContextWithRules([$rule2]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($productId1));
        static::assertTrue($products->has($productId2));
        static::assertTrue($products->has($productId3));

        $context = $this->createContextWithRules([$rule1, $rule2]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($productId1));
        static::assertTrue($products->has($productId2));
        static::assertTrue($products->has($productId3));

        $context = $this->createContextWithRules([$rule1, $rule3]);
        $products = $this->repository->search($criteria, $context);
        static::assertTrue($products->has($productId1));
        static::assertTrue($products->has($productId2));
        static::assertTrue($products->has($productId3));
    }

    public function testSearchWhitelistedRule()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();

        $parent1 = Uuid::uuid4()->getHex();
        $parent2 = Uuid::uuid4()->getHex();
        $parent3 = Uuid::uuid4()->getHex();

        $product4 = Uuid::uuid4()->getHex();
        $product5 = Uuid::uuid4()->getHex();
        $product6 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $parent1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule1, $rule2],
            ],
            //child has inherited whitelist
            ['id' => $product1, 'parentId' => $parent1],

            [
                'id' => $parent2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
            ],
            //child has whitelist, parent not
            ['id' => $product2, 'parentId' => $parent2, 'whitelistIds' => [$rule1, $rule2]],

            [
                'id' => $parent3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
            ],
            //child and parent have no whitelist
            ['id' => $product3, 'parentId' => $parent3],

            [
                'id' => $product4,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $product5,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule3],
            ],
            [
                'id' => $product6,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.manufacturerId', $manufacturerId));

        //context without rules should return the product
        $context = $this->createContextWithRules();
        $result = $this->repository->searchIds($criteria, $context);
        static::assertNotContains($product1, $result->getIds());
        static::assertNotContains($product2, $result->getIds());
        static::assertContains($product3, $result->getIds());
        static::assertNotContains($product4, $result->getIds());
        static::assertNotContains($product5, $result->getIds());
        static::assertContains($product6, $result->getIds());

        //context without rules should return the product
        $context = $this->createContextWithRules([$rule3]);
        $result = $this->repository->searchIds($criteria, $context);
        static::assertNotContains($product1, $result->getIds());
        static::assertNotContains($product2, $result->getIds());
        static::assertContains($product3, $result->getIds());
        static::assertNotContains($product4, $result->getIds());
        static::assertContains($product5, $result->getIds());
        static::assertContains($product6, $result->getIds());

        //context without rules should return the product
        $context = $this->createContextWithRules([$rule1]);
        $result = $this->repository->searchIds($criteria, $context);
        static::assertContains($product1, $result->getIds());
        static::assertContains($product2, $result->getIds());
        static::assertContains($product3, $result->getIds());
        static::assertContains($product4, $result->getIds());
        static::assertNotContains($product5, $result->getIds());
        static::assertContains($product6, $result->getIds());

        //context without rules should return the product
        $context = $this->createContextWithRules([$rule1, $rule3]);
        $result = $this->repository->searchIds($criteria, $context);
        static::assertContains($product1, $result->getIds());
        static::assertContains($product2, $result->getIds());
        static::assertContains($product3, $result->getIds());
        static::assertContains($product4, $result->getIds());
        static::assertContains($product5, $result->getIds());
        static::assertContains($product6, $result->getIds());
    }

    public function testSearchWithOneToManyWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();
        $product4 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();
        $manufacturerId2 = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule1],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule2],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule3],
            ],

            [
                'id' => $product4,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId2],
                'whitelistIds' => [$rule3],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_manufacturer.products.ean', __FUNCTION__));

        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');

        $context = $this->createContextWithRules();
        $result = $manufacturerRepository->searchIds($criteria, $context);
        static::assertNotContains($manufacturerId, $result->getIds());
        static::assertNotContains($manufacturerId2, $result->getIds());

        $context = $this->createContextWithRules([$rule1]);
        $result = $manufacturerRepository->searchIds($criteria, $context);
        static::assertContains($manufacturerId, $result->getIds());
        static::assertNotContains($manufacturerId2, $result->getIds());

        $context = $this->createContextWithRules([$rule3]);
        $result = $manufacturerRepository->searchIds($criteria, $context);
        static::assertContains($manufacturerId, $result->getIds());
        static::assertContains($manufacturerId2, $result->getIds());

        $context = $this->createContextWithRules([$rule1, $rule2, $rule3]);
        $result = $manufacturerRepository->searchIds($criteria, $context);
        static::assertContains($manufacturerId, $result->getIds());
        static::assertContains($manufacturerId2, $result->getIds());
    }

    public function testSearchWithManyToManyWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();
        $product4 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();
        $categoryId = Uuid::uuid4()->getHex();
        $categoryId2 = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['name' => 'test', 'id' => $categoryId],
                ],
                'whitelistIds' => [$rule1],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['name' => 'test', 'id' => $categoryId],
                ],
                'whitelistIds' => [$rule2],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['name' => 'test', 'id' => $categoryId],
                ],
                'whitelistIds' => [$rule3],
            ],

            [
                'id' => $product4,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test',
                'ean' => __FUNCTION__,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['name' => 'test', 'id' => $categoryId2],
                ],
                'whitelistIds' => [$rule3],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.products.ean', __FUNCTION__));

        $categoryRepository = $this->getContainer()->get('category.repository');

        $context = $this->createContextWithRules();
        $result = $categoryRepository->searchIds($criteria, $context);
        static::assertNotContains($categoryId, $result->getIds());
        static::assertNotContains($categoryId2, $result->getIds());

        $context = $this->createContextWithRules([$rule1]);
        $result = $categoryRepository->searchIds($criteria, $context);
        static::assertContains($categoryId, $result->getIds());
        static::assertNotContains($categoryId2, $result->getIds());

        $context = $this->createContextWithRules([$rule3]);
        $result = $categoryRepository->searchIds($criteria, $context);
        static::assertContains($categoryId, $result->getIds());
        static::assertContains($categoryId2, $result->getIds());

        $context = $this->createContextWithRules([$rule1, $rule2, $rule3]);
        $result = $categoryRepository->searchIds($criteria, $context);
        static::assertContains($categoryId, $result->getIds());
        static::assertContains($categoryId2, $result->getIds());
    }

    public function testAggregationWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'ean' => $product1,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'ean' => $product2,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule3],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'ean' => $product3,
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.manufacturerId', $manufacturerId));
        $criteria->addAggregation(new ValueAggregation('product.ean', 'eans'));

        /** @var ValueCountAggregationResult $result */
        $context = $this->createContextWithRules();
        $result = $this->repository->aggregate($criteria, $context)->getAggregations()->get('eans');
        static::assertNotContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule1]);
        $result = $this->repository->aggregate($criteria, $context)->getAggregations()->get('eans');
        static::assertContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule2, $rule3]);
        $result = $this->repository->aggregate($criteria, $context)->getAggregations()->get('eans');
        static::assertContains($product1, $result->getValues());
        static::assertContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());
    }

    public function testAggregationWithOneToManyWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'whitelistIds' => [$rule3],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product_manufacturer.id', $manufacturerId));
        $criteria->addAggregation(new ValueAggregation('product_manufacturer.products.id', 'products'));

        $manufacturerRepository = $this->getContainer()->get('product_manufacturer.repository');
        /** @var ValueCountAggregationResult $result */
        $context = $this->createContextWithRules();
        $result = $manufacturerRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertNotContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule1]);
        $result = $manufacturerRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule2, $rule3]);
        $result = $manufacturerRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertContains($product1, $result->getValues());
        static::assertContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());
    }

    public function testAggregationWithManyToManyWhitelist()
    {
        $product1 = Uuid::uuid4()->getHex();
        $product2 = Uuid::uuid4()->getHex();
        $product3 = Uuid::uuid4()->getHex();

        $manufacturerId = Uuid::uuid4()->getHex();
        $categoryId = Uuid::uuid4()->getHex();

        $rule1 = Uuid::uuid4()->getHex();
        $rule2 = Uuid::uuid4()->getHex();
        $rule3 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $product1,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
                'whitelistIds' => [$rule1, $rule2],
            ],
            [
                'id' => $product2,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
                'whitelistIds' => [$rule3],
            ],
            [
                'id' => $product3,
                'tax' => ['name' => 'test', 'taxRate' => 15],
                'name' => 'test product',
                'price' => ['gross' => 10, 'net' => 9],
                'manufacturer' => ['name' => 'test', 'id' => $manufacturerId],
                'categories' => [
                    ['id' => $categoryId, 'name' => 'test'],
                ],
            ],
        ];

        $this->repository->create($products, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('category.id', $categoryId));
        $criteria->addAggregation(new ValueAggregation('category.products.id', 'products'));

        $categoryRepository = $this->getContainer()->get('category.repository');
        /** @var ValueCountAggregationResult $result */
        $context = $this->createContextWithRules();
        $result = $categoryRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertNotContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule1]);
        $result = $categoryRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertContains($product1, $result->getValues());
        static::assertNotContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());

        $context = $this->createContextWithRules([$rule2, $rule3]);
        $result = $categoryRepository->aggregate($criteria, $context)->getAggregations()->get('products');
        static::assertContains($product1, $result->getValues());
        static::assertContains($product2, $result->getValues());
        static::assertContains($product3, $result->getValues());
    }

    private function createContextWithRules(array $ruleIds = [])
    {
        $source = new SourceContext('cli');
        $source->setSalesChannelId(Defaults::SALES_CHANNEL);

        return new Context($source, [Defaults::CATALOG], $ruleIds, Defaults::CURRENCY);
    }
}
