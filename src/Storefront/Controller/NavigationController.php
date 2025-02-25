<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedHook;
use Shopware\Storefront\Page\Navigation\NavigationPageLoaderInterface;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoadedHook;
use Shopware\Storefront\Pagelet\Menu\Offcanvas\MenuOffcanvasPageletLoaderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package content
 *
 * @Route(defaults={"_routeScope"={"storefront"}})
 *
 * @internal
 */
class NavigationController extends StorefrontController
{
    private NavigationPageLoaderInterface $navigationPageLoader;

    private MenuOffcanvasPageletLoaderInterface $offcanvasLoader;

    /**
     * @internal
     */
    public function __construct(
        NavigationPageLoaderInterface $navigationPageLoader,
        MenuOffcanvasPageletLoaderInterface $offcanvasLoader
    ) {
        $this->navigationPageLoader = $navigationPageLoader;
        $this->offcanvasLoader = $offcanvasLoader;
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/", name="frontend.home.page", options={"seo"="true"}, methods={"GET"}, defaults={"_httpCache"=true})
     */
    public function home(Request $request, SalesChannelContext $context): ?Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.3.3.0")
     * @Route("/navigation/{navigationId}", name="frontend.navigation.page", options={"seo"=true}, methods={"GET"}, defaults={"_httpCache"=true})
     */
    public function index(SalesChannelContext $context, Request $request): Response
    {
        $page = $this->navigationPageLoader->load($request, $context);

        $this->hook(new NavigationPageLoadedHook($page, $context));

        return $this->renderStorefront('@Storefront/storefront/page/content/index.html.twig', ['page' => $page]);
    }

    /**
     * @Since("6.0.0.0")
     * @Route("/widgets/menu/offcanvas", name="frontend.menu.offcanvas", methods={"GET"}, defaults={"XmlHttpRequest"=true, "_httpCache"=true})
     */
    public function offcanvas(Request $request, SalesChannelContext $context): Response
    {
        $page = $this->offcanvasLoader->load($request, $context);

        $this->hook(new MenuOffcanvasPageletLoadedHook($page, $context));

        $response = $this->renderStorefront(
            '@Storefront/storefront/layout/navigation/offcanvas/navigation-pagelet.html.twig',
            ['page' => $page]
        );

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
