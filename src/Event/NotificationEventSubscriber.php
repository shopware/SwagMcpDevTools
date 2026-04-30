<?php declare(strict_types=1);

namespace Swag\McpDevTools\Event;

use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Notification\NotificationService;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class NotificationEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProgressFinishedEvent::class => 'onIndexerFinished',
            ImportExportAfterProcessFinishedEvent::class => 'onImportExportFinished',
        ];
    }

    public function onIndexerFinished(ProgressFinishedEvent $event): void
    {
        $this->notificationService->createNotification([
            'id' => Uuid::randomHex(),
            'status' => 'success',
            'message' => \sprintf('Indexer \'%s\' finished.', $event->getMessage()),
            'adminOnly' => false,
            'requiredPrivileges' => [],
        ], Context::createDefaultContext());
    }

    public function onImportExportFinished(ImportExportAfterProcessFinishedEvent $event): void
    {
        $log = $event->getLogEntity();
        $activity = $log->getActivity();
        $profile = $log->getProfileName() ?? 'unknown';
        $records = $log->getRecords();
        $state = $log->getState();

        $this->notificationService->createNotification([
            'id' => Uuid::randomHex(),
            'status' => $state === 'succeeded' ? 'success' : 'info',
            'message' => \sprintf(
                '%s \'%s\' finished with state \'%s\' (%d records).',
                ucfirst($activity),
                $profile,
                $state,
                $records,
            ),
            'adminOnly' => false,
            'requiredPrivileges' => [],
        ], Context::createDefaultContext());
    }
}
