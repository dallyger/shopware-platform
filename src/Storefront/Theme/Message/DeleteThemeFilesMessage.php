<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Message;

use Shopware\Core\Framework\MessageQueue\AsyncMessageInterface;

/**
 * @package storefront
 *
 * used to delay the deletion of theme files
 */
class DeleteThemeFilesMessage implements AsyncMessageInterface
{
    public function __construct(
        private readonly string $themePath,
        private readonly string $salesChannelId,
        private readonly string $themeId
    ) {
    }

    public function getThemePath(): string
    {
        return $this->themePath;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function getThemeId(): string
    {
        return $this->themeId;
    }
}
