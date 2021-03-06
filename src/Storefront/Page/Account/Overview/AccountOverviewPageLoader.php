<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account\Overview;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Page\PageWithHeaderLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class AccountOverviewPageLoader
{
    /**
     * @var PageWithHeaderLoader
     */
    private $pageWithHeaderLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        PageWithHeaderLoader $pageWithHeaderLoader,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->pageWithHeaderLoader = $pageWithHeaderLoader;
    }

    public function load(InternalRequest $request, CheckoutContext $context): AccountOverviewPage
    {
        $page = $this->pageWithHeaderLoader->load($request, $context);

        $page = AccountOverviewPage::createFrom($page);

        $page->setCustomer($context->getCustomer());

        $this->eventDispatcher->dispatch(
            AccountOverviewPageLoadedEvent::NAME,
            new AccountOverviewPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
