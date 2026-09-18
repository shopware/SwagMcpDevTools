<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use Mcp\Schema\JsonRpc\Request;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationEntity;
use Shopware\Core\Framework\Notification\NotificationService;
use Swag\McpDevTools\Mcp\Tool\NotificationsTool;

/**
 * @internal
 */
#[CoversClass(NotificationsTool::class)]
class NotificationsToolTest extends TestCase
{
    /**
     * @var MockObject&EntityRepository<NotificationCollection>
     */
    private MockObject&EntityRepository $repository;

    private NotificationService&MockObject $notificationService;

    private McpContextProvider&MockObject $contextProvider;

    private NotificationsTool $tool;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->contextProvider = $this->createMock(McpContextProvider::class);

        $this->contextProvider->method('getContext')->willReturn($this->adminApiContext());

        $this->tool = new NotificationsTool(
            $this->repository,
            $this->notificationService,
            $this->contextProvider,
        );
    }

    public function testReturnsEmptyWhenNoNotifications(): void
    {
        $this->mockServiceResult(new NotificationCollection(), null);

        $data = $this->invoke($this->makeContext());

        static::assertTrue($data['success']);
        static::assertSame(0, $data['data']['count']);
        static::assertNull($data['data']['timestamp']);
        static::assertSame([], $data['data']['notifications']);
    }

    public function testReturnsNotifications(): void
    {
        $notification = $this->makeNotification('abc123', 'success', 'Indexer \'product.indexer\' finished.');
        $this->mockServiceResult(new NotificationCollection([$notification]), '2026-04-30 10:00:00.000');

        $data = $this->invoke($this->makeContext());

        static::assertTrue($data['success']);
        static::assertSame(1, $data['data']['count']);
        static::assertSame('abc123', $data['data']['notifications'][0]['id']);
        static::assertSame('success', $data['data']['notifications'][0]['status']);
        static::assertSame('Indexer \'product.indexer\' finished.', $data['data']['notifications'][0]['message']);
        static::assertSame('2026-04-30T10:00:00.000+00:00', $data['data']['timestamp']);
    }

    /**
     * Regression guard: the tool must never read the notification repository directly for
     * an Admin API caller. Doing so bypasses the adminOnly and requiredPrivileges filtering
     * that NotificationService applies, exposing every notification in the shop to any
     * caller — including one holding no ACL privileges at all.
     */
    public function testDelegatesToNotificationServiceInsteadOfReadingRepository(): void
    {
        $this->repository->expects($this->never())->method('search');

        $this->notificationService
            ->expects($this->once())
            ->method('getNotifications')
            ->with(
                static::isInstanceOf(Context::class),
                20,
                null,
            )
            ->willReturn(['notifications' => new NotificationCollection(), 'timestamp' => null]);

        $this->invoke($this->makeContext());
    }

    public function testPassesSinceToNotificationServiceAsCursor(): void
    {
        $this->notificationService
            ->expects($this->once())
            ->method('getNotifications')
            ->with(
                static::isInstanceOf(Context::class),
                50,
                '2026-04-30T00:00:00+00:00',
            )
            ->willReturn(['notifications' => new NotificationCollection(), 'timestamp' => null]);

        $this->invoke($this->makeContext(), since: '2026-04-30T00:00:00+00:00', limit: 50);
    }

    public function testFallsBackToRepositoryForNonAdminSource(): void
    {
        $notification = $this->makeNotification('cli1', 'info', 'from cli');

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(Context::createCLIContext());

        $this->notificationService->expects($this->never())->method('getNotifications');

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new NotificationCollection([$notification]));
        $this->repository->expects($this->once())->method('search')->willReturn($result);

        $tool = new NotificationsTool($this->repository, $this->notificationService, $contextProvider);
        $data = json_decode(($tool)($this->makeContext()), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(1, $data['data']['count']);
        static::assertSame('cli1', $data['data']['notifications'][0]['id']);
    }

    public function testWaitModeReturnsImmediatelyWhenNotificationPresent(): void
    {
        $notification = $this->makeNotification('xyz', 'info', 'done');
        $this->mockServiceResult(new NotificationCollection([$notification]), '2026-04-30 10:00:00.000');

        $data = $this->invoke($this->makeContext(), wait: true);

        static::assertTrue($data['success']);
        static::assertSame(1, $data['data']['count']);
    }

    public function testWaitModeTimesOutWithoutNotifications(): void
    {
        $this->mockServiceResult(new NotificationCollection(), null);

        $data = $this->invoke($this->makeContext(), wait: true, timeout: 0);

        static::assertTrue($data['success']);
        static::assertTrue($data['data']['timeout']);
        static::assertSame(0, $data['data']['count']);
    }

    /**
     * Regression guard for the cursor contract: the service reports its cursor in storage
     * format, which carries milliseconds. Formatting it as plain ATOM would drop them, and
     * the truncated value fed back as `since` re-matches everything created earlier in that
     * same second — turning incremental polling into duplicate delivery.
     */
    public function testCursorPreservesMilliseconds(): void
    {
        $this->mockServiceResult(new NotificationCollection(), '2026-04-30 10:00:00.500');

        $data = $this->invoke($this->makeContext());

        static::assertSame('2026-04-30T10:00:00.500+00:00', $data['data']['timestamp']);
    }

    /**
     * "Not an AdminApiSource" must not be read as "must be the trusted CLI". Any source we
     * have no read path for is refused rather than silently handed the unfiltered read.
     */
    public function testRefusesUnknownContextSource(): void
    {
        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn(new Context(new SalesChannelApiSource('sales-channel-id')));

        $this->notificationService->expects($this->never())->method('getNotifications');
        $this->repository->expects($this->never())->method('search');

        $tool = new NotificationsTool($this->repository, $this->notificationService, $contextProvider);
        $data = json_decode(($tool)($this->makeContext()), true, 512, \JSON_THROW_ON_ERROR);

        static::assertFalse($data['success']);
        static::assertStringContainsString('Unsupported context source', $data['error']);
    }

    /**
     * The CLI fallback must elevate the context it was given rather than forging a fresh
     * one, so nothing about the caller is silently discarded.
     */
    public function testCliFallbackElevatesTheResolvedContext(): void
    {
        $cliContext = Context::createCLIContext();

        $contextProvider = $this->createMock(McpContextProvider::class);
        $contextProvider->method('getContext')->willReturn($cliContext);

        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn(new NotificationCollection());

        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::anything(),
                static::callback(static fn (Context $ctx): bool => $ctx->getScope() === Context::SYSTEM_SCOPE
                    && $ctx->getSource() === $cliContext->getSource()),
            )
            ->willReturn($result);

        $tool = new NotificationsTool($this->repository, $this->notificationService, $contextProvider);
        $data = json_decode(($tool)($this->makeContext()), true, 512, \JSON_THROW_ON_ERROR);

        static::assertTrue($data['success']);
    }

    private function adminApiContext(): Context
    {
        return new Context(new AdminApiSource(null, 'integration-id'));
    }

    private function makeContext(): RequestContext
    {
        $session = $this->createMock(SessionInterface::class);
        // Return the default value for any session key so ClientGateway::progress() silently no-ops
        $session->method('get')->willReturnArgument(1);

        return new RequestContext($session, $this->createMock(Request::class));
    }

    private function makeNotification(string $id, string $status, string $message): NotificationEntity
    {
        $entity = new NotificationEntity();
        $entity->setId($id);
        $entity->setStatus($status);
        $entity->setMessage($message);
        $entity->setCreatedAt(new \DateTimeImmutable('2026-04-30T10:00:00+00:00'));

        return $entity;
    }

    private function mockServiceResult(NotificationCollection $collection, ?string $timestamp): void
    {
        $this->notificationService
            ->method('getNotifications')
            ->willReturn(['notifications' => $collection, 'timestamp' => $timestamp]);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoke(
        RequestContext $context,
        ?string $since = null,
        int $limit = 20,
        bool $wait = false,
        int $timeout = 60,
    ): array {
        $output = ($this->tool)($context, $since, $limit, $wait, $timeout);

        return json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
    }
}
