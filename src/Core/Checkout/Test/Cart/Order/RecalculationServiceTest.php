<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Order;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehaviorContext;
use Shopware\Core\Checkout\Cart\Delivery\Struct\Delivery;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryDate;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryPosition;
use Shopware\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Shopware\Core\Checkout\Cart\Enrichment;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Order\OrderPersister;
use Shopware\Core\Checkout\Cart\Order\RecalculationService;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Processor;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Cart\Transaction\Struct\TransactionCollection;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Context\CheckoutContextFactory;
use Shopware\Core\Checkout\Context\CheckoutContextService;
use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressEntity;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\ExtensionHelper;
use Shopware\Core\Framework\Test\TestCaseHelper\ReflectionHelper;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class RecalculationServiceTest extends TestCase
{
    use IntegrationTestBehaviour,
        AdminApiTestBehaviour;

    /**
     * @var CheckoutContext
     */
    protected $checkoutContext;

    /**
     * @var Context
     */
    protected $context;

    /**
     * @var string
     */
    protected $customerId;

    protected function setUp()
    {
        parent::setUp();
        $this->context = Context::createDefaultContext();

        $this->customerId = $this->createCustomer();
        $shippingMethodId = $this->createShippingMethod();
        $this->checkoutContext = $this->getContainer()->get(CheckoutContextFactory::class)->create(
            Uuid::uuid4()->getHex(),
            Defaults::SALES_CHANNEL,
            [
                CheckoutContextService::CUSTOMER_ID => $this->customerId,
                CheckoutContextService::SHIPPING_METHOD_ID => $shippingMethodId,
            ]
        );
    }

    public function testPersistOrderAndConvertToCart()
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        $criteria = (new Criteria([$orderId]))
            ->addAssociation('lineItems')
            ->addAssociation('deliveries');
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($orderId);
        $convertedCart = $this->getContainer()->get(OrderConverter::class)->convertToCart($order, $this->context);

        // check name and token
        self::assertEquals(OrderConverter::CART_TYPE, $convertedCart->getName());
        self::assertNotEquals($cart->getToken(), $convertedCart->getToken());
        self::assertTrue(Uuid::isValid($convertedCart->getToken()));

        // set name and token to be equal for further comparison
        $cart->setName($convertedCart->getName());
        $cart->setToken($convertedCart->getToken());

        // transactions are currently not supported so they are excluded for comparison
        $cart->setTransactions(new TransactionCollection());

        // remove all extensions for comparision
        $extensionHelper = new ExtensionHelper();
        $extensionHelper->removeExtensions($convertedCart);
        $extensionHelper->removeExtensions($cart);

        // remove delivery information from line items

        /** @var Delivery $delivery */
        foreach ($cart->getDeliveries() as $delivery) {
            // remove address from ShippingLocation
            $property = ReflectionHelper::getProperty(ShippingLocation::class, 'address');
            $property->setValue($delivery->getLocation(), null);

            /** @var DeliveryPosition $position */
            foreach ($delivery->getPositions() as $position) {
                $position->getLineItem()->setDeliveryInformation(null);
            }
        }

        /** @var LineItem $lineItem */
        foreach ($cart->getLineItems() as $lineItem) {
            $lineItem->setDeliveryInformation(null);
        }

        self::assertEquals($cart, $convertedCart, print_r(['original' => $cart, 'converted' => $convertedCart], true));
    }

    public function testOrderConverterController()
    {
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        $client = $this->getClient();

        // transform order to cart
        $client->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order/%s/convert-to-cart/',
                PlatformRequest::API_VERSION,
                $orderId
            )
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $token = $content['token'];
        static::assertTrue(Uuid::isValid($token));

        // get cart over proxy
        $client->request(
            'GET',
            sprintf(
                '/api/v%s/_proxy/storefront-api/%s/v%s/checkout/cart',
                PlatformRequest::API_VERSION,
                Defaults::SALES_CHANNEL,
                PlatformRequest::API_VERSION
            ),
            [
                'token' => $token,
                'name' => OrderConverter::CART_TYPE,
            ]
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)['data'];

        static::assertEquals(62, $content['price']['netPrice']);
        static::assertEquals(72.16, $content['price']['totalPrice']);
        static::assertEquals(72.16, $content['price']['positionPrice']);
        static::assertEquals(CartPrice::TAX_STATE_GROSS, $content['price']['taxStatus']);
        static::assertCount(3, $content['lineItems']);

        // increase quantity of line item from 5 to 10
        $client->request(
            'PATCH',
            sprintf(
                '/api/v%s/_proxy/storefront-api/%s/v%s/checkout/cart/line-item/%s',
                PlatformRequest::API_VERSION,
                Defaults::SALES_CHANNEL,
                PlatformRequest::API_VERSION,
                $cart->getLineItems()->first()->getKey()
            ),
            [
                'quantity' => 10,
                'token' => $token,
                'name' => OrderConverter::CART_TYPE,
            ]
        );

        $response = $client->getResponse();
        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $content = json_decode($response->getContent(), true)['data'];

        static::assertEquals(112, $content['price']['netPrice']);
        static::assertEquals(131.66, $content['price']['totalPrice']);
        static::assertEquals(131.66, $content['price']['positionPrice']);
        static::assertCount(3, $content['lineItems']);
    }

    public function testRecalculationController()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // recalculate order
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order/%s/recalculate',
                PlatformRequest::API_VERSION,
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());

        // read order
        $versionContext = $this->context->createWithVersionId($versionId);
        /** @var OrderEntity $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $versionContext)->get($orderId);

        static::assertNotNull($order->getOrderCustomer());

        // recalculate order 2nd time
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order/%s/recalculate',
                PlatformRequest::API_VERSION,
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testAddProductToOrder()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;
        $this->addProductToVersionedOrder($productName, $productPrice, $productTaxRate, $orderId, $versionId);
    }

    public function testAddCustomLineItemToOrder()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $this->addCustomLineItemToVersionedOrder($orderId, $versionId);
    }

    public function testCreatedVersionedOrderAndMerge()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        $productName = 'Test';
        $productPrice = 10.0;
        $productTaxRate = 19.0;
        $productId = $this->addProductToVersionedOrder($productName, $productPrice, $productTaxRate, $orderId, $versionId);

        // merge versioned order
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/version/merge/%s/%s',
                PlatformRequest::API_VERSION,
                OrderDefinition::getEntityName(),
                $versionId
            )
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        // read merged order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('order.lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context)->get($orderId);
        static::assertNotEmpty($order);

        $product = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $productId) {
                $product = $lineItem;
            }
        }

        static::assertNotNull($product);
        $productPriceInclTax = 10 + ($productPrice * $productTaxRate / 100);
        static::assertSame($product->getPrice()->getUnitPrice(), $productPriceInclTax);
        /** @var TaxRule $taxRule */
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);
    }

    public function testChangeShippingCosts()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);
        $versionContext = $this->context->createWithVersionId($versionId);

        $critera = new Criteria();
        $critera->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $orderDeliveryRepository = $this->getContainer()->get('order_delivery.repository');
        $deliveries = $orderDeliveryRepository->search($critera, $versionContext);

        static::assertSame(1, $deliveries->count());
        /** @var CalculatedPrice $shippingCosts */
        $shippingCosts = $deliveries->first()->getShippingCosts();

        static::assertSame(1, $shippingCosts->getQuantity());
        static::assertSame(10.0, $shippingCosts->getUnitPrice());
        static::assertSame(10.0, $shippingCosts->getTotalPrice());
        static::assertSame(3, $shippingCosts->getCalculatedTaxes()->count());

        // change shipping costs
        $newShippingCosts = new CalculatedPrice(5, 5, new CalculatedTaxCollection(), new TaxRuleCollection());

        $payload = [
            'id' => $deliveries->first()->getId(),
            'shippingCosts' => $newShippingCosts,
        ];

        $orderDeliveryRepository->upsert([$payload], $versionContext);

        $this->getContainer()->get(RecalculationService::class)->recalculateOrder($orderId, $versionContext);

        $critera = new Criteria();
        $critera->addFilter(new EqualsFilter('order_delivery.orderId', $orderId));
        $deliveries = $orderDeliveryRepository->search($critera, $versionContext);

        /** @var CalculatedPrice $newShippingCosts */
        $newShippingCosts = $deliveries->first()->getShippingCosts();
        static::assertSame(1, $newShippingCosts->getQuantity());
        static::assertSame(5.0, $newShippingCosts->getUnitPrice());
        static::assertSame(5.0, $newShippingCosts->getTotalPrice());
        static::assertSame(3, $newShippingCosts->getCalculatedTaxes()->count());
        static::assertEquals($shippingCosts->getTaxRules(), $newShippingCosts->getTaxRules());
        static::assertEquals(5,
            $newShippingCosts->getCalculatedTaxes()->get(5)->getPrice() +
            $newShippingCosts->getCalculatedTaxes()->get(7)->getPrice() +
            $newShippingCosts->getCalculatedTaxes()->get(19)->getPrice()
        );
    }

    public function testReplaceBillingAddress()
    {
        // create order
        $cart = $this->generateDemoCart();
        $orderId = $this->persistCart($cart);

        // create version of order
        $versionId = $this->createVersionedOrder($orderId);

        // create a new address for the existing customer

        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        $orderAddressId = $order->getAddresses()->first()->getId();

        $salutation = 'Replace salutation';
        $firstName = 'Replace first name';
        $lastName = 'Replace last name';
        $street = 'Replace street';
        $city = 'Replace city';
        $zipcode = '98765';

        $customerAddressId = $this->addAddressToCustomer(
            $this->customerId,
            $salutation,
            $firstName,
            $lastName,
            $street,
            $city,
            $zipcode
        );

        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order-address/%s/customer-address/%s',
                PlatformRequest::API_VERSION,
                $orderAddressId,
                $customerAddressId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getClient()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search(new Criteria([$orderId]), $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotNull($order);
        /** @var OrderAddressEntity $orderAddress */
        $orderAddress = $order->getAddresses()->first();

        static::assertSame($orderAddressId, $orderAddress->getId());
        static::assertSame($salutation, $orderAddress->getSalutation());
        static::assertSame($firstName, $orderAddress->getFirstName());
        static::assertSame($lastName, $orderAddress->getLastName());
        static::assertSame($street, $orderAddress->getStreet());
        static::assertSame($city, $orderAddress->getCity());
        static::assertSame($zipcode, $orderAddress->getZipcode());
    }

    private function addAddressToCustomer(
        string $customerId,
        string $salutation,
        string $firstName,
        string $lastName,
        string $street,
        string $city,
        string $zipcode): string
    {
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => $salutation,
                    'firstName' => $firstName,
                    'lastName' => $lastName,
                    'street' => $street,
                    'zipcode' => $zipcode,
                    'city' => $city,
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $addressId;
    }

    private function createProduct(string $name, float $price, float $taxRate): string
    {
        $productId = Uuid::uuid4()->getHex();
        $data = [
            'id' => $productId,
            'name' => $name,
            'price' => ['gross' => $price + ($price * $taxRate / 100), 'net' => $price],
            'manufacturer' => ['name' => 'create'],
            'tax' => ['name' => 'create', 'taxRate' => $taxRate],
        ];
        $this->getContainer()->get('product.repository')->create([$data], $this->context);

        return $productId;
    }

    private function createCustomer(): string
    {
        $customerId = Uuid::uuid4()->getHex();
        $addressId = Uuid::uuid4()->getHex();

        $customer = [
            'id' => $customerId,
            'number' => '1337',
            'salutation' => 'Mr',
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'customerNumber' => '1337',
            'email' => Uuid::uuid4()->getHex() . '@example.com',
            'password' => 'shopware',
            'defaultPaymentMethodId' => Defaults::PAYMENT_METHOD_INVOICE,
            'groupId' => Defaults::FALLBACK_CUSTOMER_GROUP,
            'salesChannelId' => Defaults::SALES_CHANNEL,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'customerId' => $customerId,
                    'countryId' => Defaults::COUNTRY,
                    'salutation' => 'Mr',
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        $this->getContainer()->get('customer.repository')->upsert([$customer], $this->context);

        return $customerId;
    }

    private function generateDemoCart(): Cart
    {
        $cart = new Cart('A', 'a-b-c');
        $deliveryInformation = new DeliveryInformation(
            100,
            0,
            new DeliveryDate(new \DateTime(), new \DateTime()),
            new DeliveryDate(new \DateTime(), new \DateTime())
        );
        $cart->add(
            (new LineItem('1', 'product_', 5))
                ->setPriceDefinition(new QuantityPriceDefinition(10, new TaxRuleCollection([new TaxRule(19)]), 5))
                ->setLabel('First product')
                ->setPayloadValue('id', '1')
                ->setStackable(true)
                ->setDeliveryInformation($deliveryInformation)
        );
        $cart->add(
            (new LineItem('2', 'custom_absolute', 1))
                // todo should be supported SOON
//                ->setPriceDefinition(new AbsolutePriceDefinition(3))
                ->setPriceDefinition(new QuantityPriceDefinition(3, new TaxRuleCollection([new TaxRule(7)]), 1))
                ->setLabel('Second custom line item with absolute price definition')
                ->setDeliveryInformation($deliveryInformation)
        );

        $cart->add(
            (new LineItem('abcdefg', 'nested', 1))
                ->setLabel('Third line item (multi level nested)')
                ->setDeliveryInformation($deliveryInformation)
                ->addChild(
                    (new LineItem('3-1', 'custom', 1))
                        ->setLabel('Custom child depth 1 of the third line item')
                        ->addChild(
                            (new LineItem('3-1-1', 'product_', 1))
                                ->setPriceDefinition(new QuantityPriceDefinition(9, new TaxRuleCollection([new TaxRule(5)]), 1))
                                ->setLabel('Product depth 2 of third line item')
                                ->setPayloadValue('id', '3-1-1')
                        )
                )
        );
        $cart = $this->getContainer()->get(Enrichment::class)->enrich($cart, $this->checkoutContext);
        $cart = $this->getContainer()->get(Processor::class)->process($cart, $this->checkoutContext, new CartBehaviorContext());

        return $cart;
    }

    private function persistCart(Cart $cart): string
    {
        $events = $this->getContainer()->get(OrderPersister::class)->persist($cart, $this->checkoutContext);
        $orderIds = $events->getEventByDefinition(OrderDefinition::class)->getIds();

        if (count($orderIds) !== 1) {
            self::fail('Order could not be persisted');
        }

        return $orderIds[0];
    }

    private function createVersionedOrder(string $orderId): string
    {
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/version/order/%s',
                PlatformRequest::API_VERSION,
                $orderId
            )
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $content = json_decode($response->getContent(), true);
        $versionId = $content['version_id'];
        static::assertEquals($orderId, $content['id']);
        static::assertEquals('order', $content['entity']);
        static::assertTrue(Uuid::isValid($versionId));

        return $versionId;
    }

    private function addProductToVersionedOrder(
        string $productName,
        float $productPrice,
        float $productTaxRate,
        string $orderId,
        string $versionId): string
    {
        $productId = $this->createProduct($productName, $productPrice, $productTaxRate);

        // add product to order
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order/%s/product/%s',
                PlatformRequest::API_VERSION,
                $orderId,
                $productId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ]
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('order.lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);

        $product = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $productId) {
                $product = $lineItem;
            }
        }

        static::assertNotNull($product);
        $productPriceInclTax = 10 + ($productPrice * $productTaxRate / 100);
        static::assertSame($product->getPrice()->getUnitPrice(), $productPriceInclTax);
        /** @var TaxRule $taxRule */
        $taxRule = $product->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), $productTaxRate);

        return $productId;
    }

    private function addCustomLineItemToVersionedOrder(string $orderId, string $versionId): void
    {
        $identifier = Uuid::uuid4()->getHex();
        $data = [
            'identifier' => $identifier,
            'type' => 'test',
            'quantity' => 10,
            'label' => 'example label',
            'description' => 'example description',
            'priceDefinition' => [
                'price' => 27.99,
                'quantity' => 10,
                'isCalculated' => false,
                'taxRules' => [
                    [
                        'taxRate' => 19,
                        'percentage' => 100,
                    ],
                ],
            ],
        ];

        // add product to order
        $this->getClient()->request(
            'POST',
            sprintf(
                '/api/v%s/_action/order/%s/lineItem',
                PlatformRequest::API_VERSION,
                $orderId
            ),
            [],
            [],
            [
                'HTTP_' . PlatformRequest::HEADER_VERSION_ID => $versionId,
            ],
            json_encode($data)
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        // read versioned order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('order.lineItems');
        /** @var OrderEntity|null $order */
        $order = $this->getContainer()->get('order.repository')->search($criteria, $this->context->createWithVersionId($versionId))->get($orderId);
        static::assertNotEmpty($order);

        $customLineItem = null;
        foreach ($order->getLineItems() as $lineItem) {
            if ($lineItem->getIdentifier() === $identifier) {
                $customLineItem = $lineItem;
            }
        }

        static::assertNotNull($customLineItem);
        static::assertSame($customLineItem->getPrice()->getUnitPrice(), 33.31);
        static::assertSame($customLineItem->getPrice()->getQuantity(), 10);
        static::assertSame($customLineItem->getPrice()->getTotalPrice(), 333.1);
        /** @var TaxRule $taxRule */
        $taxRule = $customLineItem->getPrice()->getTaxRules()->first();
        static::assertSame($taxRule->getTaxRate(), 19.0);
        static::assertSame($taxRule->getPercentage(), 100.0);
        /** @var CalculatedTax $calculatedTaxes */
        $calculatedTaxes = $customLineItem->getPrice()->getCalculatedTaxes()->first();
        static::assertSame($calculatedTaxes->getPrice(), 333.1);
        static::assertSame($calculatedTaxes->getTaxRate(), 19.0);
        static::assertSame($calculatedTaxes->getTax(), 53.18);
    }

    private function createShippingMethod(): string
    {
        $shippingMethodId = Uuid::uuid4()->getHex();
        $repository = $this->getContainer()->get('shipping_method.repository');

        $data = [
            'id' => $shippingMethodId,
            'type' => 0,
            'name' => 'test shipping method',
            'bindShippingfree' => false,
            'active' => true,
            'prices' => [
                [
                    'shippingMethodId' => Defaults::SHIPPING_METHOD,
                    'quantityFrom' => 0,
                    'price' => '10.00',
                    'factor' => 0,
                ],
            ],
        ];

        $repository->create([$data], $this->context);

        return $shippingMethodId;
    }
}
