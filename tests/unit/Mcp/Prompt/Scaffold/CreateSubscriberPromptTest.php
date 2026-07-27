<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateSubscriberPrompt;

/**
 * @internal
 */
#[CoversClass(CreateSubscriberPrompt::class)]
class CreateSubscriberPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutInterfaceAndTag(): void
    {
        $content = $this->assertUserMessage((new CreateSubscriberPrompt())('SwagFoo', 'FooSubscriber', 'ProductEvents::PRODUCT_LOADED_EVENT'));

        static::assertStringContainsString('EventSubscriberInterface', $content);
        static::assertStringContainsString('getSubscribedEvents', $content);
        static::assertStringContainsString('kernel.event_subscriber', $content);
    }
}
