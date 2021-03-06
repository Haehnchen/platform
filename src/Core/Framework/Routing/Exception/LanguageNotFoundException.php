<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class LanguageNotFoundException extends ShopwareHttpException
{
    public const LANGUAGE_NOT_FOUND_ERROR = 'LANGUAGE-NOT-FOUND';
    protected $code = self::LANGUAGE_NOT_FOUND_ERROR;

    public function __construct($languageId, int $code = 0, Throwable $previous = null)
    {
        $message = \sprintf('The language %s was not found.', (string) $languageId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_PRECONDITION_FAILED;
    }
}
