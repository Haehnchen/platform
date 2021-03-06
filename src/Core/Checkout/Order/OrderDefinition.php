<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order;

use Shopware\Core\Checkout\Order\Aggregate\OrderAddress\OrderAddressDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderCustomer\OrderCustomerDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CartPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\SearchKeywordAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\ReadOnly;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class OrderDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order';
    }

    public static function getCollectionClass(): string
    {
        return OrderCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new IntField('auto_increment', 'autoIncrement'))->addFlags(new ReadOnly()),

            (new FkField('billing_address_id', 'billingAddressId', OrderAddressDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderAddressDefinition::class, 'billing_address_version_id'))->addFlags(new Required()),

            (new FkField('order_customer_id', 'orderCustomerId', OrderCustomerDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderCustomerDefinition::class))->addFlags(new Required()),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            (new FkField('sales_channel_id', 'salesChannelId', SalesChannelDefinition::class))->addFlags(new Required()),

            (new DateField('date', 'date'))->addFlags(new Required()),

            new CartPriceField('price', 'price'),
            (new FloatField('amount_total', 'amountTotal'))->addFlags(new ReadOnly()),
            (new FloatField('amount_net', 'amountNet'))->addFlags(new ReadOnly()),
            (new FloatField('position_price', 'positionPrice'))->addFlags(new ReadOnly()),
            (new StringField('tax_status', 'taxStatus'))->addFlags(new ReadOnly()),

            new CalculatedPriceField('shipping_costs', 'shippingCosts'),
            (new FloatField('shipping_total', 'shippingTotal'))->addFlags(new ReadOnly()),
            (new FloatField('currency_factor', 'currencyFactor'))->addFlags(new Required()),
            new StringField('deep_link_code', 'deepLinkCode'),

            (new FkField('state_id', 'stateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, true),

            new CreatedAtField(),
            new UpdatedAtField(),

            (new ManyToOneAssociationField('orderCustomer', 'order_customer_id', OrderCustomerDefinition::class, true))->addFlags(new SearchRanking(0.5)),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('salesChannel', 'sales_channel_id', SalesChannelDefinition::class, true),
            (new OneToManyAssociationField('addresses', OrderAddressDefinition::class, 'order_id', true))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('deliveries', OrderDeliveryDefinition::class, 'order_id', true))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('lineItems', OrderLineItemDefinition::class, 'order_id', false))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            (new OneToManyAssociationField('transactions', OrderTransactionDefinition::class, 'order_id', false))->addFlags(new CascadeDelete()),
            new SearchKeywordAssociationField(),
        ]);
    }
}
