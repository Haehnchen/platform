<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Search;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\NestedEvent;
use Shopware\Core\Framework\Routing\InternalRequest;

class SearchPageLoadedEvent extends NestedEvent
{
    public const NAME = 'search.page.loaded';

    /**
     * @var SearchPage
     */
    protected $page;

    /**
     * @var CheckoutContext
     */
    protected $context;

    /**
     * @var InternalRequest
     */
    protected $request;

    public function __construct(SearchPage $page, CheckoutContext $context, InternalRequest $request)
    {
        $this->page = $page;
        $this->context = $context;
        $this->request = $request;
    }

    public function getName(): string
    {
        return self::NAME;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getCheckoutContext(): CheckoutContext
    {
        return $this->context;
    }

    public function getPage(): SearchPage
    {
        return $this->page;
    }

    public function getRequest(): InternalRequest
    {
        return $this->request;
    }
}
