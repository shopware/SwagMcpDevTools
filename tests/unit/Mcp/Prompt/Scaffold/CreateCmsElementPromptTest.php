<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Mcp\Prompt\Scaffold;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Prompt\Scaffold\CreateCmsElementPrompt;

/**
 * @internal
 */
#[CoversClass(CreateCmsElementPrompt::class)]
class CreateCmsElementPromptTest extends TestCase
{
    use ScaffoldPromptAssertions;

    public function testRemindsAboutBothHalves(): void
    {
        $content = $this->assertUserMessage((new CreateCmsElementPrompt())('SwagFoo', 'swag-banner', 'Banner'));

        static::assertStringContainsString('AbstractCmsElementResolver', $content);
        static::assertStringContainsString('shopware.cms.data_resolver', $content);
        static::assertStringContainsString('registerCmsElement', $content);
    }
}
