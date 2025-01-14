<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing\Annotation;

use Shopware\Core\Framework\Feature;

/**
 * @package storefront
 *
 * @Annotation
 *
 * @deprecated tag:v6.6.0 - Will be removed use `defaults: {"_noStore"=true}` instead
 */
class NoStore
{
    public const ALIAS = 'noStore';

    public function getAliasName(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return self::ALIAS;
    }

    public function allowArray(): bool
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.6.0.0')
        );

        return false;
    }
}
