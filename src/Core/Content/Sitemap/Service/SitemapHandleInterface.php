<?php declare(strict_types=1);

namespace Shopware\Core\Content\Sitemap\Service;

/**
 * @package sales-channel
 */
interface SitemapHandleInterface
{
    public function write(array $urls): void;

    public function finish(): void;
}
