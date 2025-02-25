<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Service;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Shopware\Core\Defaults;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
class WebhookCleanup
{
    /**
     * @internal
     */
    public function __construct(
        private SystemConfigService $systemConfigService,
        private Connection $connection,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    public function removeOldLogs(): void
    {
        $entryLifetimeSeconds = $this->systemConfigService->getInt('core.webhook.entryLifetimeSeconds');

        if ($entryLifetimeSeconds === -1) {
            return;
        }

        $deleteBefore = $this->clock
            ->now()
            ->modify("- $entryLifetimeSeconds seconds")
            ->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement(
            'DELETE FROM `webhook_event_log` WHERE `created_at` < :before',
            ['before' => $deleteBefore]
        );
    }
}
