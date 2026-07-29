<?php declare(strict_types=1);

namespace Swag\McpDevTools\Tests\Unit\Tool;

use Mcp\Capability\Attribute\McpTool;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Swag\McpDevTools\Mcp\Tool\ListSkillsTool;
use Swag\McpDevTools\Mcp\Tool\LoadSkillTool;

/**
 * list-skills and load-skill are the only two tools in the dev-skills group, so a
 * model picks between them on their descriptions alone. Twice now the pair has
 * traded failures because list-skills carried load-skill's vocabulary:
 *
 *  - Before PR #8/#9 it ended with "use swag-dev-tools-load-skill to read one",
 *    and gpt-5.4-mini answered "Read me the instructions in the 'entity-definition'
 *    skill" with list-skills.
 *  - PR #9 tried to fix that by negating the overlap ("DO NOT use this to read a
 *    skill's instructions ... never the SKILL.md body"). That made it worse: the
 *    negation does not remove the tokens, so all three load_skill fixtures lost to
 *    list-skills and the primary model went from 20/21 to 18/21 on dev-tools.
 *
 * The rule this test encodes: describe what a tool DOES, and let the sibling's own
 * description own its verbs. Removing the overlap works; negating it does not.
 *
 * @internal
 */
final class SkillsToolDescriptionTest extends TestCase
{
    /**
     * Verbs and nouns that belong to load-skill. list-skills must not contain them
     * at all, not even inside a "DO NOT" clause.
     */
    #[DataProvider('loadSkillVocabularyProvider')]
    public function testListSkillsDescriptionAvoidsLoadSkillVocabulary(string $pattern, string $term): void
    {
        $description = self::descriptionOf(ListSkillsTool::class);

        static::assertDoesNotMatchRegularExpression(
            $pattern,
            $description,
            \sprintf(
                'list-skills must not describe itself with "%s"; that word belongs to load-skill and '
                . 'pulls read-a-skill prompts to the wrong tool even when it is negated.',
                $term,
            ),
        );
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function loadSkillVocabularyProvider(): array
    {
        return [
            'read' => ['/\bread\b/i', 'read'],
            'instructions' => ['/\binstructions\b/i', 'instructions'],
            'contents' => ['/\bcontents\b/i', 'contents'],
            'body' => ['/\bbody\b/i', 'body'],
            'SKILL.md' => ['/SKILL\.md/i', 'SKILL.md'],
            'sibling tool name' => ['/swag-dev-tools-load-skill/', 'swag-dev-tools-load-skill'],
        ];
    }

    /**
     * The disambiguation fixture asks for "the catalogue of skills with a one-line
     * description of each", so list-skills has to keep owning those words.
     */
    #[DataProvider('listSkillVocabularyProvider')]
    public function testListSkillsDescriptionKeepsItsOwnVocabulary(string $term): void
    {
        static::assertStringContainsStringIgnoringCase(
            $term,
            self::descriptionOf(ListSkillsTool::class),
            \sprintf('list-skills must keep owning "%s" so the index prompt still resolves to it.', $term),
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function listSkillVocabularyProvider(): array
    {
        return [
            'catalogue' => ['catalogue'],
            'one-line description' => ['one-line description'],
            'source' => ['source'],
        ];
    }

    /**
     * The cross-reference is only safe in this direction: load-skill needs a name it
     * may not have, so pointing at list-skills earns its keep. The reverse pointer is
     * what broke tool selection, so it stays deleted.
     */
    public function testOnlyLoadSkillReferencesItsSibling(): void
    {
        static::assertStringContainsString(
            'swag-dev-tools-list-skills',
            self::descriptionOf(LoadSkillTool::class),
            'load-skill should still tell the model where to find a skill name it does not know.',
        );
    }

    /**
     * @param class-string $toolClass
     */
    private static function descriptionOf(string $toolClass): string
    {
        $attributes = (new \ReflectionClass($toolClass))->getAttributes(McpTool::class);

        static::assertCount(1, $attributes, \sprintf('%s must have exactly one #[McpTool] attribute.', $toolClass));

        $description = $attributes[0]->newInstance()->description;

        static::assertIsString($description, \sprintf('%s must declare a description.', $toolClass));

        return $description;
    }
}
