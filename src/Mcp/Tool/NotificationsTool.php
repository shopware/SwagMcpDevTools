<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use Mcp\Schema\Enum\LoggingLevel;
use Mcp\Server\RequestContext;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Mcp\Attribute\McpToolGroup;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\Framework\Mcp\Tool\McpToolResponse;
use Shopware\Core\Framework\Notification\NotificationCollection;
use Shopware\Core\Framework\Notification\NotificationEntity;
use Shopware\Core\Framework\Notification\NotificationService;

#[McpTool(
    name: 'swag-dev-tools-notifications',
    title: 'Notifications',
    description: 'Poll for Shopware background operation notifications: indexer completions, import/export results. Use after triggering long-running operations like dal:refresh:index or import jobs. Set wait=true to block until a notification arrives — streams progress updates via SSE up to timeout seconds. Use since=<ISO-8601> for incremental polling; pass the returned timestamp as since on the next call to get only new events. DO NOT use this for runtime PHP errors or stack traces — use swag-dev-tools-log-stream instead. DO NOT use this for structured business event logs — query entity "log_entry" with shopware-entity-search instead.',
)]
#[McpToolGroup('dev-extensions')]
class NotificationsTool extends McpToolResponse
{
    /**
     * @param EntityRepository<NotificationCollection> $notificationRepository
     */
    public function __construct(
        private readonly EntityRepository $notificationRepository,
        private readonly NotificationService $notificationService,
        private readonly McpContextProvider $contextProvider,
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
                if (\Fiber::getCurrent() !== null) {
                    $client->log(LoggingLevel::Info, $result['notifications'], 'swag-dev-tools');
                }
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
        $context = $this->contextProvider->getContext();

        if ($context->getSource() instanceof AdminApiSource) {
            // Go through the same service the Admin API endpoint uses, so that adminOnly
            // notifications and those carrying requiredPrivileges are filtered against the
            // caller's ACL role. Reading the repository directly would bypass both.
            $result = $this->notificationService->getNotifications($context, $limit, $since);
            $notifications = $result['notifications'];
            $cursor = $result['timestamp'];
        } else {
            // CLI transport (bin/console mcp:debug) carries no admin source to filter
            // against, and already implies shell access — there is no privilege boundary
            // left to enforce, so fall back to reading everything.
            $notifications = $this->fetchWithoutFiltering($since, $limit);
            $cursor = $notifications->last()?->getCreatedAt()?->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        $items = [];

        /** @var NotificationEntity $notification */
        foreach ($notifications as $notification) {
            $items[] = [
                'id' => $notification->getId(),
                'status' => $notification->getStatus(),
                'message' => $notification->getMessage(),
                'created_at' => $notification->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ];
        }

        return [
            'count' => \count($items),
            'timestamp' => $this->toAtom($cursor),
            'notifications' => $items,
        ];
    }

    private function fetchWithoutFiltering(?string $since, int $limit): NotificationCollection
    {
        $criteria = new Criteria();

        if ($since !== null) {
            $criteria->addFilter(new RangeFilter('createdAt', [RangeFilter::GT => $since]));
        }

        $criteria->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $criteria->setLimit($limit);

        return Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $ctx) use ($criteria) {
            return $this->notificationRepository->search($criteria, $ctx)->getEntities();
        });
    }

    /**
     * NotificationService reports its cursor in storage format; the tool's documented
     * contract is ISO-8601, since callers pass it straight back as the since argument.
     */
    private function toAtom(?string $storageTimestamp): ?string
    {
        if ($storageTimestamp === null) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat(
            Defaults::STORAGE_DATE_TIME_FORMAT,
            $storageTimestamp,
            new \DateTimeZone('UTC'),
        );

        return $date === false ? null : $date->format(\DateTimeInterface::ATOM);
    }
}
