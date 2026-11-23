<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Tool\Concern;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Mcp\Result\McpToolError;
use Shopware\Core\Framework\Mcp\Result\McpToolResult;

/**
 * @internal
 *
 * Privilege checks that return an {@see McpToolResult}, built only from API available in
 * shopware/core 6.7.16. They mirror `McpToolResponse::requirePrivilegeResult()` and
 * `missingPrivilegesResult()` from 6.7.17 (same message, code and details), which replace the
 * deprecated string helpers `requirePrivilege()` and `missingPrivilegesError()`.
 *
 * Drop this trait in favour of the core helpers once the bundle requires shopware/core 6.7.17.
 */
trait RequiresPrivileges
{
    /**
     * @return McpToolResult|null a failure if a privilege is missing, null if all are granted
     */
    protected function requirePrivilegeResult(Context $context, string ...$privileges): ?McpToolResult
    {
        foreach ($privileges as $privilege) {
            if (!$context->isAllowed($privilege)) {
                return $this->missingPrivilegesResult([$privilege]);
            }
        }

        return null;
    }

    /**
     * Canonical failure for privilege denials, so clients can match on a single "Missing privilege:" prefix.
     *
     * @param non-empty-list<string> $privileges
     */
    protected function missingPrivilegesResult(array $privileges): McpToolResult
    {
        return McpToolResult::failure(\sprintf('Missing privilege: %s', implode(', ', $privileges)), McpToolError::MISSING_PRIVILEGE);
    }
}
