<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\Aggregate\ImportExportLog\ImportExportLogEntity;
use Shopware\Core\Content\ImportExport\Event\ImportExportAfterProcessFinishedEvent;
use Shopware\Core\Content\ImportExport\Struct\Progress;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Notification\NotificationService;
use Swag\McpDevTools\Event\NotificationEventSubscriber;

/**
 * @internal
 */
#[CoversClass(NotificationEventSubscriber::class)]
class NotificationEventSubscriberTest extends TestCase
{
    private MockObject&NotificationService $notificationService;

    private NotificationEventSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->subscriber = new NotificationEventSubscriber($this->notificationService);
    }

    public function testSubscribesToExpectedEvents(): void
    {
        $events = NotificationEventSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(ProgressFinishedEvent::class, $events);
        static::assertArrayHasKey(ImportExportAfterProcessFinishedEvent::class, $events);
    }

    public function testOnIndexerFinishedCreatesSuccessNotification(): void
    {
        $this->notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(
                    static fn (array $data): bool => $data['status'] === 'success'
                    && str_contains($data['message'], 'product.indexer')
                    && $data['adminOnly'] === false
                    && $data['requiredPrivileges'] === []
                ),
                static::anything(),
            );

        $this->subscriber->onIndexerFinished(new ProgressFinishedEvent('product.indexer'));
    }

    public function testOnImportExportFinishedCreatesSuccessNotificationWhenSucceeded(): void
    {
        $this->notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(
                    static fn (array $data): bool => $data['status'] === 'success'
                    && str_contains($data['message'], 'Products')
                    && str_contains($data['message'], '500')
                    && str_contains($data['message'], 'succeeded')
                ),
                static::anything(),
            );

        $this->subscriber->onImportExportFinished(
            $this->makeImportEvent('import', 'Products', 500, 'succeeded'),
        );
    }

    public function testOnImportExportFinishedCreatesInfoNotificationOnNonSuccess(): void
    {
        $this->notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(static fn (array $data): bool => $data['status'] === 'info'),
                static::anything(),
            );

        $this->subscriber->onImportExportFinished(
            $this->makeImportEvent('import', 'Products', 100, 'failed'),
        );
    }

    public function testOnImportExportFinishedFallsBackToUnknownWhenProfileNameMissing(): void
    {
        $this->notificationService->expects($this->once())
            ->method('createNotification')
            ->with(
                static::callback(static fn (array $data): bool => str_contains($data['message'], 'unknown')),
                static::anything(),
            );

        $this->subscriber->onImportExportFinished(
            $this->makeImportEvent('export', null, 0, 'succeeded'),
        );
    }

    private function makeImportEvent(
        string $activity,
        ?string $profileName,
        int $records,
        string $state,
    ): ImportExportAfterProcessFinishedEvent {
        $logEntity = new ImportExportLogEntity();
        $logEntity->setActivity($activity);
        if ($profileName !== null) {
            $logEntity->setProfileName($profileName);
        }
        $logEntity->setRecords($records);
        $logEntity->setState($state);

        return new ImportExportAfterProcessFinishedEvent(
            Context::createDefaultContext(),
            $logEntity,
            new Progress($activity, $state),
        );
    }
}
