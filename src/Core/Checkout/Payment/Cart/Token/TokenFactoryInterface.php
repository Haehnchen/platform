<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Cart\Token;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Framework\Context;

interface TokenFactoryInterface
{
    public function generateToken(OrderTransactionEntity $transaction, Context $context, ?string $finishUrl = null, int $expiresInSeconds = 1800): string;

    public function parseToken(string $token, Context $context): TokenStruct;

    public function invalidateToken(string $tokenId, Context $context): bool;
}
