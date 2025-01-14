<?php declare(strict_types=1);

namespace Shopware\Storefront\Pagelet\Newsletter\Account;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @package customer-order
 */
class NewsletterAccountPageletLoadedEvent extends PageletLoadedEvent
{
    protected NewsletterAccountPagelet $pagelet;

    public function __construct(NewsletterAccountPagelet $pagelet, SalesChannelContext $salesChannelContext, Request $request)
    {
        $this->pagelet = $pagelet;
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): NewsletterAccountPagelet
    {
        return $this->pagelet;
    }
}
