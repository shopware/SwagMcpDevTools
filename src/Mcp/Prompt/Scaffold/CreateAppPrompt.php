<?php declare(strict_types=1);

namespace Swag\McpDevTools\Mcp\Prompt\Scaffold;

use Mcp\Capability\Attribute\McpPrompt;

class CreateAppPrompt extends AbstractScaffoldPrompt
{
    /**
     * @return list<array{role: string, content: string}>
     */
    #[McpPrompt(
        name: 'swag-dev-tools-create-app',
        title: 'Create App',
        description: 'Scaffold a new Shopware 6 App (manifest.xml based, runs outside Shopware over HTTP). Use for apps rather than plugins when the extension should be cloud-friendly or run its own backend.',
    )]
    public function __invoke(
        string $appName = '',
        string $label = '',
        string $needsBackend = '',
    ): array {
        $appName = $appName !== '' ? $appName : 'MyApp';
        $label = $label !== '' ? $label : $appName;

        $wantsBackend = filter_var($needsBackend, \FILTER_VALIDATE_BOOL);

        $setupBlock = $wantsBackend
            ? <<<'SETUP'

   - **`<setup>`** (required because this app has its own backend): a
     `<registrationUrl>` your app answers for the registration handshake and a
     `<secret>` (dev only — omit in the store version).
SETUP
            : "\n   - Omit `<setup>` unless the app needs a backend/registration handshake.";

        $text = <<<PROMPT
Scaffold a new Shopware 6 **App** (not a plugin). Apps run OUTSIDE Shopware over
HTTP, so they contain NO PHP business classes — no controllers, subscribers, or
services. Behavior is declared in the manifest and executed by your external app
server (webhooks/action-buttons) or by admin extension iframes.

- **App name (== folder name):** {$appName}
- **Label:** {$label}
- **Location:** `custom/apps/{$appName}/`

Study the equivalent of
`tests/unit/Core/Framework/App/Manifest/_fixtures/test/manifest.xml` and the schema
`src/Core/Framework/App/Manifest/Schema/manifest-3.0.xsd` in your Shopware source.

Generate **`custom/apps/{$appName}/manifest.xml`**:

1. **`<meta>`** (required): `name` == `{$appName}`, `label`, `author`, `version`
   (e.g. `1.0.0`); optionally `description`, `license`, `privacy`, `icon`.{$setupBlock}
2. **`<permissions>`** — declare only the DAL access the app needs, per entity and
   operation, e.g. `<read>order</read>`, `<update>order</update>`. Keep it minimal.
3. Add the surfaces the app uses (all optional): `<webhooks>`, `<admin>`
   (action-buttons / modules / main-module), `<custom-fields>`, `<cookies>`,
   `<payments>`, `<gateways>` — see the XSD for the full catalog.

If the user actually asked for PHP behavior (a controller, subscriber, entity,
CMS element, …), that belongs in a **plugin**, not an app — tell them and offer
`swag-dev-tools-create-plugin` instead.

Remind the user to run `bin/console app:install --activate {$appName}`.
PROMPT;

        $text .= $this->skillFooter([]);
        $text .= $this->toolingFooter();

        return $this->userMessage($text);
    }
}
