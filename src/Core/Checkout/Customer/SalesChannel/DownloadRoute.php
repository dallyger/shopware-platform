<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItemDownload\OrderLineItemDownloadEntity;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class DownloadRoute extends AbstractDownloadRoute
{
    private EntityRepository $downloadRepository;

    private DownloadResponseGenerator $downloadResponseGenerator;

    /**
     * @internal
     */
    public function __construct(
        EntityRepository $downloadRepository,
        DownloadResponseGenerator $downloadResponseGenerator
    ) {
        $this->downloadRepository = $downloadRepository;
        $this->downloadResponseGenerator = $downloadResponseGenerator;
    }

    public function getDecorated(): AbstractDownloadRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.4.19.0")
     * @Route("/store-api/order/download/{orderId}/{downloadId}", name="store-api.account.order.single.download", methods={"GET"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function load(Request $request, SalesChannelContext $context): Response
    {
        $customer = $context->getCustomer();
        $downloadId = $request->get('downloadId', false);
        $orderId = $request->get('orderId', false);

        if (!$customer) {
            throw CartException::customerNotLoggedIn();
        }

        if ($downloadId === false || $orderId === false) {
            throw new MissingRequestParameterException(!$downloadId ? 'downloadId' : 'orderId');
        }

        $criteria = new Criteria([$downloadId]);
        $criteria->addAssociation('media');
        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_AND,
            [
                new EqualsFilter('orderLineItem.order.id', $orderId),
                new EqualsFilter('orderLineItem.order.orderCustomer.customerId', $customer->getId()),
                new EqualsFilter('accessGranted', true),
            ]
        ));

        $download = $this->downloadRepository->search($criteria, $context->getContext())->first();

        if (!$download instanceof OrderLineItemDownloadEntity || !$download->getMedia()) {
            throw new FileNotFoundException($downloadId);
        }

        $media = $download->getMedia();

        return $this->downloadResponseGenerator->getResponse($media, $context);
    }
}
