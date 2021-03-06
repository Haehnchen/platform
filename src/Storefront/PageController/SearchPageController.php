<?php declare(strict_types=1);

namespace Shopware\Storefront\PageController;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Routing\InternalRequest;
use Shopware\Storefront\Framework\Controller\StorefrontController;
use Shopware\Storefront\Page\Search\SearchPageLoader;
use Shopware\Storefront\Pagelet\Listing\Subscriber\SearchTermSubscriber;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class SearchPageController extends StorefrontController
{
    /**
     * @var SearchPageLoader
     */
    private $searchPageLoader;

    public function __construct(SearchPageLoader $searchPageLoader)
    {
        $this->searchPageLoader = $searchPageLoader;
    }

    /**
     * @Route("/search", name="frontend.search.page", options={"seo"=false}, methods={"GET"})
     *
     * @return Response
     */
    public function index(CheckoutContext $context, InternalRequest $request): Response
    {
        $request->requireGet(SearchTermSubscriber::TERM_PARAMETER);

        $page = $this->searchPageLoader->load($request, $context);

        return $this->renderStorefront('@Storefront/index/index.html.twig', ['page' => $page]);
    }
}
