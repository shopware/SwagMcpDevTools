<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Server\RequestContext;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationEntity;

#[McpTool(
    name: 'swag-dev-tools-notifications',
    description: 'Poll for Shopware background operation notifications: indexer completions, import/export results. Use after triggering long-running operations like dal:refresh:index or import jobs. Set wait=true to block until a notification arrives — streams progress updates via SSE up to timeout seconds. Use since=<ISO-8601> for incremental polling; pass the returned timestamp as since on the next call to get only new events. DO NOT use this for runtime PHP errors or stack traces — use swag-dev-tools-log-stream instead. DO NOT use this for structured business event logs — query entity "log_entry" with shopware-entity-search instead.',
)]
class NotificationsTool extends McpToolResponse
{
    /**
     * @param EntityRepository<NotificationCollection> $notificationRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
    ) {
    }

    public function __invoke(
        RequestContext $context,
        ?string $since = null,
        int $limit = 20,
        bool $wait = false,
        int $timeout = 60,
    ): string {
        $client = $context->getClientGateway();

        if (!$wait) {
            return $this->success($this->fetchNotifications($since, $limit));
        }

        $elapsed = 0;
        $interval = 3;
        while ($elapsed < $timeout) {
            $result = $this->fetchNotifications($since, $limit);
            if ($result['count'] > 0) {
                $client->progress(100.0, 100.0, 'Notification received.');

                return $this->success($result);
            }
            $client->progress((float) $elapsed, (float) $timeout, \sprintf('Waiting... (%ds elapsed)', $elapsed));
            sleep($interval);
            $elapsed += $interval;
        }

        return $this->success(['timeout' => true, 'count' => 0, 'notifications' => [], 'timestamp' => null]);
    }

    /**
     * @return array{count: int, timestamp: string|null, notifications: list<array{id: string, status: string, message: string, created_at: string|null}>}
     */
    private function fetchNotifications(?string $since, int $limit): array
    {
        $criteria = new Criteria();

        if ($since !== null) {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GT => $since]));
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        $systemContext = Context::createDefaultContext();

        /** @var NotificationCollection $notifications */
        $notifications = $systemContext->scope(Context::SYSTEM_SCOPE, function (Context $ctx) use ($criteria) {
            return $this->notificationRepository->search($criteria, $ctx)->getEntities();
        });

        $items = [];
        $latestTimestamp = null;

        /** @var NotificationEntity $notification */
        foreach ($notifications as $notification) {
            $createdAt = $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM);
            $items[] = [
                'id' => $notification->getId(),
                'status' => $notification->getStatus(),
                'message' => $notification->getMessage(),
                'created_at' => $createdAt,
            ];
            $latestTimestamp = $createdAt;
        }

        return [
            'count' => \count($items),
            'timestamp' => $latestTimestamp,
            'notifications' => $items,
        ];
    }
}
