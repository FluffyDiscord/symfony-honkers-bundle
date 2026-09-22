# Symfony honkers.dev Bundle

Exposes the [honkers.dev SDK](https://github.com/FluffyDiscord/honkers-sdk) chatbot tool-server over
HTTP: the four endpoints, access-token security, DI wiring, the outbound catalog-ingest client and the
widget snippet. Ships **no domain tools** — write your own, or use
[`fluffydiscord/sylius-honkers-bundle`](https://github.com/FluffyDiscord/sylius-honkers-bundle) for
Sylius defaults.

PHP 8.1+, Symfony 6.4+.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/chatbot/v1/tools` | List tools with generated JSON input schemas; labels translated per `Accept-Language`. |
| POST | `/chatbot/v1/tools/{name}` | Call a tool with `{ arguments, context: { conversationId, locale, channelCode } }`. |
| GET | `/chatbot/v1/sources` | List data sources with served locales. |
| GET | `/chatbot/v1/sources/{name}` | Read a data source, keyset-paginated. |

**Tools** answer "what is true right now" — the backend calls them mid-conversation for live data.
**Sources** answer "what the shop is" — the backend pulls them in bulk and ingests them into its own
retrieval index; never called per chat.

## Install

```bash
composer require fluffydiscord/symfony-honkers-bundle
```

```php
// config/bundles.php
FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle::class => ['all' => true],
```

**Only `api_secret` is required.** The rest power the outbound + widget services below.

```yaml
# config/packages/fluffy_discord_honkers.yaml
fluffy_discord_honkers:
    api_secret: '%env(CHATBOT_API_SECRET)%'       # inbound: Bearer the backend sends you
    backend_url: '%env(CHATBOT_BACKEND_URL)%'     # outbound: honkers.dev origin
    ingest_secret: '%env(CHATBOT_INGEST_SECRET)%' # outbound: catalog-push secret
    widget:
        enabled: true
        site_key: '%env(CHATBOT_SITE_KEY)%'
        cdn_url: '%env(CHATBOT_WIDGET_CDN_URL)%'   # optional; empty → {backend_url}/widget/v1/chat.js
```

Routes are not auto-loaded — import them:

```php
// config/routes/fluffy_discord_honkers.php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@FluffyDiscordHonkersBundle/config/routes.php');
};
```

### Security

**Guard `/chatbot/v1` with an access-token firewall.** The backend authenticates with
`Authorization: Bearer <api_secret>`.

```yaml
# config/packages/security.yaml
security:
    providers:
        chatbot_backend:
            memory:
                users: []
    firewalls:
        chatbot_api:
            pattern: ^/chatbot/v1
            stateless: true
            provider: chatbot_backend
            entry_point: FluffyDiscord\HonkersBundle\Security\ApiAuthenticationFailureHandler
            access_token:
                token_handler: FluffyDiscord\HonkersBundle\Security\ApiSecretAuthenticator
                failure_handler: FluffyDiscord\HonkersBundle\Security\ApiAuthenticationFailureHandler
    access_control:
        - { path: ^/chatbot/v1, roles: ROLE_CHATBOT_BACKEND }
```

Firewalls match in order — put `chatbot_api` **before** any catch-all firewall that also matches `^/`.

## Error format

Every endpoint returns errors as:

```json
{ "error": { "code": "...", "message": "...", "violations": [] } }
```

`violations` is non-empty only on HTTP 422 (`validation_failed`).

## Locales

**Every locale you send must be one ICU knows** (`symfony/intl`). Spelling does not matter: `cs-CZ`,
`cs_cz` and `CS-cz` all mean `cs_CZ`. The requested locale is matched against the locales served —
first as the same locale, then as the same language — and everything queried and rendered uses the
served locale's own full code, never the requested spelling and never a bare language.

What a bad locale costs differs per endpoint, because each has somewhere different to fall back to:

| Endpoint | Not an ICU locale | Valid but unserved |
|---|---|---|
| `GET /sources/{name}?locale=` | 400 `invalid_locale` | 400 `invalid_locale` |
| `POST /tools/{name}` (`context.locale`) | 422 `validation_failed`, a `context.locale` violation | falls back to the current locale |
| `GET /tools` (`Accept-Language`) | 400 `bad_request` | falls back to the current locale |

Which locales are served, and what "current locale" means, comes from `ChatbotLocaleContextInterface`.
The default returns the kernel locale only; override that service to serve more (the Sylius wrapper
makes it channel-aware).

## Add a tool

One class plus one arguments DTO, no configuration:

```php
readonly class MyArguments
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(max: 64)]
        public string $query = '',
    ) {
    }
}

readonly class MyTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('my_tool', 'app.chatbot.my_tool.description');
    }

    public function getArgumentsClass(): string
    {
        return MyArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        return new ToolResult([new ContentItem('...')]);
    }
}
```

The interface is autoconfigured. The input schema is generated from the DTO: `Assert\NotBlank` →
required, `Assert\Email` → e-mail format, `Assert\Choice` → enum, `Assert\Length` → maxLength,
nullable → optional. Descriptions and labels are translation keys resolved with the request locale.

**`getDefinition()` must not read constructor arguments** — it runs at container compile time to index
tools by name, and duplicate names fail the build.

### Choices loaded at runtime

`Assert\Choice` takes a fixed list or a static callback — no services. **When the allowed values live
in the database, use `#[ToolChoice]`** instead: `Assert\Choice` with the choices loaded from a
`ToolChoiceLoaderInterface` service (autoconfigured). The loaded values become the property's schema
`enum`, and Symfony's `ChoiceValidator` checks them on every call.

```php
use FluffyDiscord\Honkers\Contract\ToolChoiceLoaderInterface;
use FluffyDiscord\Honkers\Validator\ToolChoice;

readonly class RegionChoiceLoader implements ToolChoiceLoaderInterface
{
    public function __construct(
        private RegionRepository $regionRepository,
    ) {
    }

    public function loadChoices(): array
    {
        return $this->regionRepository->findAllNames();
    }
}

readonly class FindByRegionArguments
{
    public function __construct(
        #[ToolChoice(loader: RegionChoiceLoader::class, message: 'The region argument must be one of the listed names.')]
        public ?string $region = null,
    ) {
    }
}
```

- Takes every `Assert\Choice` option except `choices`, `callback` and `strict`: `multiple`, `min`,
  `max`, `match` and the messages. Violations carry `Choice`'s codes and parameters.
- The property must be `string`, or `array` with `multiple: true` (published as `items.enum`, plus
  `minItems`/`maxItems`). Anything else fails the tool list.
- The loader is looked up by its service id, which must be its class name (the autoconfigured default).
- Choices are the exact values the tool accepts, in every locale — not translated labels. Duplicates
  are dropped.
- An empty loader leaves the argument out of the schema; any value sent then fails validation. A
  required argument (`Assert\NotBlank`) with an empty loader makes the tool uncallable.
- **Return an empty list for missing data; throw only for misconfiguration** — a loader that throws
  fails both the tool list and the call.
- `null` passes; add `Assert\NotBlank` to make the argument required.

## Add a data source

Implement `ChatbotDataSourceInterface` the same way a tool is added — one autoconfigured class.
`SourceDefinition::$locales = null` means "all locales of the current context".

`GET /sources/{name}?locale=cs_CZ&cursor=&ids[]=` returns keyset-paginated documents, 200 per page.
With `ids[]` (max 500) the cursor is ignored and `nextCursor` is `null`.

## Push catalog changes

**Tell honkers.dev which entries changed so it re-indexes them.** Inject
`FluffyDiscord\Honkers\Ingest\CatalogIngestClient` — it is wired to Symfony's HTTP client (2 s timeout,
5 s max duration) and your `backend_url`/`ingest_secret`:

```php
use FluffyDiscord\Honkers\DTO\CatalogChange;
use FluffyDiscord\Honkers\Enum\CatalogSourceName;

$result = $catalogIngestClient->send($siteKey, new CatalogChange(
    CatalogSourceName::Products,
    'cs_CZ',
    ['CLIPPER-01', 'CLIPPER-02'],   // max 500 per call
));
```

The request carries `Authorization: Bearer <siteKey>.<ingest_secret>`. `$result->accepted` is the 202;
`$result->isThrottled()` with `$result->retryAfterSeconds` is a 429.

## Widget

Inject `FluffyDiscord\Honkers\Widget\WidgetSnippet` to render the chat embed from your `widget.*`
config:

```html
<script src="{cdn_url}" defer></script>
<ai-chat-widget site-key="{site_key}" backend-url="{backend_url}"></ai-chat-widget>
```

The widget talks only to `backend_url`; it never calls `/chatbot/v1` itself.
(`fluffydiscord/sylius-honkers-bundle` auto-injects it into the shop layout for you.)

## Tests

```bash
composer install
vendor/bin/phpunit
```
