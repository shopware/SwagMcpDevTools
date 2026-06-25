<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use Mcp\Schema\JsonRpc\Request;
use Mcp\Server\RequestContext;
use Mcp\Server\Session\SessionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Notification\NotificationEntity;
use Swag\McpDevTools\Mcp\Tool\NotificationsTool;

/**
 * @internal
 */
#[CoversClass(NotificationsTool::class)]
class NotificationsToolTest extends TestCase
{
    /**
     * @var MockObject&EntityRepository<EntityCollection<NotificationEntity>>
     */
    private MockObject&EntityRepository $repository;

    private NotificationsTool $tool;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EntityRepository::class);
        $this->tool = new NotificationsTool($this->repository);
    }

    public function testReturnsEmptyWhenNoNotifications(): void
    {
        $this->mockSearchResult(new EntityCollection());

        $data = $this->invoke($this->makeContext());

        static::assertTrue($data['success']);
        static::assertSame(0, $data['data']['count']);
        static::assertNull($data['data']['timestamp']);
        static::assertSame([], $data['data']['notifications']);
    }

    public function testReturnsNotifications(): void
    {
        $notification = $this->makeNotification('abc123', 'success', 'Indexer \'product.indexer\' finished.');
        $this->mockSearchResult(new EntityCollection([$notification]));

        $data = $this->invoke($this->makeContext());

        static::assertTrue($data['success']);
        static::assertSame(1, $data['data']['count']);
        static::assertSame('abc123', $data['data']['notifications'][0]['id']);
        static::assertSame('success', $data['data']['notifications'][0]['status']);
        static::assertSame('Indexer \'product.indexer\' finished.', $data['data']['notifications'][0]['message']);
        static::assertNotNull($data['data']['timestamp']);
    }

    public function testPassesSinceFilterWhenProvided(): void
    {
        $this->repository
            ->expects($this->once())
            ->method('search')
            ->with(
                static::callback(static fn (Criteria $c): bool => $c->getFilters() !== []),
                static::anything(),
            )
            ->willReturn($this->buildSearchResult(new EntityCollection()));

        $this->invoke($this->makeContext(), since: '2026-04-30T00:00:00+00:00');
    }

    public function testWaitModeReturnsImmediatelyWhenNotificationPresent(): void
    {
        $notification = $this->makeNotification('xyz', 'info', 'done');
        $this->mockSearchResult(new EntityCollection([$notification]));

        $data = $this->invoke($this->makeContext(), wait: true);

        static::assertTrue($data['success']);
        static::assertSame(1, $data['data']['count']);
    }

    public function testWaitModeTimesOutWithoutNotifications(): void
    {
        $this->mockSearchResult(new EntityCollection());

        $data = $this->invoke($this->makeContext(), wait: true, timeout: 0);

        static::assertTrue($data['success']);
        static::assertTrue($data['data']['timeout']);
        static::assertSame(0, $data['data']['count']);
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

    private function mockSearchResult(EntityCollection $collection): void
    {
        $this->repository->method('search')->willReturn($this->buildSearchResult($collection));
    }

    private function buildSearchResult(EntityCollection $collection): EntitySearchResult
    {
        $result = $this->createMock(EntitySearchResult::class);
        $result->method('getEntities')->willReturn($collection);

        return $result;
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
