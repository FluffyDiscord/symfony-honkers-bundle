# Symfony honkers.dev Bundle

Symfony bundle for the [honkers.dev](https://honkers.dev) chatbot: the `/chatbot/v1` tool-server endpoints,
their auth, one-click pairing and outbound reporting. PHP 8.1+, Symfony 6.4+. MIT.

Sylius users: use [`fluffydiscord/sylius-honkers-plugin`](https://github.com/FluffyDiscord/sylius-honkers-plugin).
Upgrading from 1.x: see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Features

- [Tools](#add-a-tool) and [data sources](#add-a-data-source) — one autoconfigured class each, no config
- [Connect from the dashboard](#connect-from-the-dashboard) — the shop admin clicks **Connect**; nobody copies a secret
- [Store credentials elsewhere](#store-credentials-elsewhere) — database, vault, per-channel keys
- [Push catalog changes](#push-catalog-changes) — the chatbot re-indexes just the entries you changed
- [Chat click tracking](#chat-click-tracking) — chat link visits reported with no code
- [Widget](#widget) — chat embed markup from your config

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/chatbot/v1/tools` | List tools with generated JSON input schemas; labels translated per `Accept-Language`. |
| POST | `/chatbot/v1/tools/{name}` | Call a tool with `{ arguments, context: { conversationId, locale, channelCode } }`. |
| GET | `/chatbot/v1/sources` | List data sources with served locales. |
| GET | `/chatbot/v1/sources/{name}` | Read a data source, keyset-paginated. |
| GET | `/chatbot/pair?code=…` | Browser lands here when the admin clicks **Connect**. |

**Tools** answer "what is true right now" — the backend calls them mid-conversation for live data.
**Sources** answer "what the shop is" — the backend pulls them in bulk and ingests them into its own
retrieval index; never called per chat.

## Install

```bash
composer require fluffydiscord/symfony-honkers-bundle
```

1. `config/bundles.php` — Symfony Flex already did this if it's on.

```php
FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle::class => ['all' => true],
```

2. `config/packages/fluffy_discord_honkers.yaml`

```yaml
fluffy_discord_honkers:
    backend_url: '%env(CHATBOT_BACKEND_URL)%'
```

3. `.env.local` — skip the three secrets if you [connect from the dashboard](#connect-from-the-dashboard)
   with your own [credentials store](#store-credentials-elsewhere).

```dotenv
CHATBOT_BACKEND_URL=https://honkers.dev
CHATBOT_API_SECRET=
CHATBOT_INGEST_SECRET=
CHATBOT_SITE_KEY=
```

4. `config/routes/fluffy_discord_honkers.php` — routes aren't auto-loaded.

```php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@FluffyDiscordHonkersBundle/config/routes.php');
};
```

5. `config/packages/security.yaml` — the backend sends `Authorization: Bearer <api secret>`.

```yaml
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

> `/chatbot/pair` sits outside `^/chatbot/v1` on purpose: it's a browser redirect, the one-time code is
> the proof. Keep it reachable without login.

## Configuration

```yaml
fluffy_discord_honkers:
    backend_url: ''
    widget:
        enabled: true
        cdn_url: ''
        defer: true
```

| Option | Default | Meaning |
|---|---|---|
| `backend_url` | `''` | honkers.dev origin for outbound calls and pairing. Empty → nothing sent. Not https outside `dev` → refused. |
| `widget.enabled` | `true` | Parameter `fluffydiscord_honkers.widget.enabled` for whoever prints the widget. |
| `widget.cdn_url` | `''` | Widget script URL. `''` = `https://honkers.b-cdn.net/widget/v1/chat.js`. |
| `widget.defer` | `true` | `false` → plain `<script src="…">`, no `defer`. |

**Credentials aren't config.** The default provider reads them from env:

| Env var | Used for | Empty |
|---|---|---|
| `CHATBOT_API_SECRET` | Bearer the backend sends to `/chatbot/v1` | every call → 401 `unauthorized` |
| `CHATBOT_INGEST_SECRET` | outbound calls, `Bearer <site key>.<ingest secret>` | `hasIngestSecret()` → `false`, no outbound calls; widget still works |
| `CHATBOT_SITE_KEY` | outbound calls, widget | `findCurrentSite()` → `null` |

Missing env vars never break the boot.

## Store credentials elsewhere

**Keep the secrets in a database or vault → implement `CredentialsProviderInterface` and alias it.**

`src/Chatbot/DatabaseCredentials.php`

```php
use FluffyDiscord\Honkers\DTO\SiteCredentials;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;

class DatabaseCredentials implements CredentialsProviderInterface
{
    public function __construct(
        private readonly ChatbotSettingsRepository $settingsRepository,
    ) {
    }

    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
    {
        $settings = $this->settingsRepository->findCurrent();
        if ($settings === null) {
            return false;
        }

        return hash_equals($settings->apiSecretHash, hash('sha256', $token));
    }

    public function findCurrentSite(): ?SiteCredentials
    {
        $settings = $this->settingsRepository->findCurrent();
        if ($settings === null) {
            return null;
        }

        return new SiteCredentials($settings->siteKey, $settings->ingestSecret);
    }
}
```

`config/services.yaml`

```diff
 services:
+    FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface: '@App\Chatbot\DatabaseCredentials'
```

| Method | Called | Return |
|---|---|---|
| `isApiSecretValid($token)` | every `/chatbot/v1` request | `true` = the backend may call. You own the compare → store only a hash if you like. |
| `findCurrentSite()` | landing requests with `?gooseclid=`, your own outbound/widget code | the site of the current request; `null` → nothing reported, empty ingest secret → widget only |

- Read the store on each call, don't keep it in a property → a re-paired secret works without a restart,
  also in long-running workers.
- Use `hash_equals` for the compare.

### Enable Connect

**Implement `CredentialsWriterInterface` too → the dashboard's Connect button fills your store.**

```php
use FluffyDiscord\Honkers\Pairing\HostMatcher;
use FluffyDiscord\HonkersBundle\Contract\CredentialsWriterInterface;
use FluffyDiscord\HonkersBundle\Pairing\PairedCredentials;
use Symfony\Component\HttpFoundation\Request;

class DatabaseCredentials implements CredentialsProviderInterface, CredentialsWriterInterface
{
    public function __construct(
        private readonly ChatbotSettingsRepository $settingsRepository,
        private readonly HostMatcher               $hostMatcher,
    ) {
    }

    public function findTrustedHost(Request $request): ?string
    {
        foreach ($this->settingsRepository->findShopHostnames() as $shopHostname) {
            $isRequestHost = $this->hostMatcher->isSameHost($shopHostname, $request->getHost());
            if ($isRequestHost) {
                return $shopHostname;
            }
        }

        return null;
    }

    public function save(PairedCredentials $credentials, Request $request): void
    {
        $this->settingsRepository->saveCredentials(
            $credentials->siteKey,
            hash('sha256', $credentials->apiSecret),
            $credentials->ingestSecret,
        );
    }
}
```

`config/services.yaml`

```diff
 services:
     FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface: '@App\Chatbot\DatabaseCredentials'
+    FluffyDiscord\HonkersBundle\Contract\CredentialsWriterInterface: '@App\Chatbot\DatabaseCredentials'
```

| Method | Return / effect |
|---|---|
| `findTrustedHost($request)` | the hostname this shop answers on, from your own data; `null` → `pairing_hostname_missing` |
| `save($credentials, $request)` | store `siteKey`, `apiSecret`, `ingestSecret`; `channelCodes` = the connection's channels, if you key per channel; `verifiedDomains` = the website's verified domains, to check each channel's host |

- `save()` throws `PairingException` → error page with its code, nothing else happens. Any other exception → 500.
  Use a `PairingErrorCode` value for a translated message:
  `throw new PairingException('Read-only store.', PairingErrorCode::Unsupported->value)`.
- No writer aliased → `/chatbot/pair` answers 409: "paste the credentials manually".

## Connect from the dashboard

**Needs a [credentials writer](#enable-connect).** Then nobody types a secret:

1. The admin clicks **Connect** on a tool-server connection in the honkers.dev dashboard.
2. The browser opens `{connection base URL}/chatbot/pair?code=…` on your shop.
3. The bundle makes two 64-character secrets, redeems the code at your configured `backend_url` and calls
   `save()`.
4. The browser goes back to the dashboard, which tests the connection with the new API secret.

> The dashboard starts pairing only for a connection whose base URL is on a verified domain of the website.

**The host of the connection's base URL must equal `findTrustedHost()`.** Case, a trailing dot and IDN
spelling don't matter, `www.` does: `www.shop.example` ≠ `shop.example`. Mismatch → `pairing_wrong_shop`,
nothing saved. Return a hostname you stored, never the request's `Host` header — anyone can send any `Host`.
Compare your own hosts the same way with `HostMatcher` (autowired).

**The backend never redirects the secrets.** Its answer redirects → not followed, `invalid_response`. Its
return URL off the `backend_url` origin → `invalid_response`, nothing saved.

Something fails → a short page in the request locale (English, Czech shipped) with the message and the
error code:

| Code | Status | Cause |
|---|---|---|
| — | 409 | No `CredentialsWriterInterface` service |
| `invalid_code` | 400 | `code` missing or malformed |
| `backend_url_refused` | 400 | `backend_url` empty, or not https outside `dev` |
| `pairing_hostname_missing` | 400 | `findTrustedHost()` returned `null` |
| `pairing_wrong_shop` | 400 | Base URL host ≠ trusted host |
| `pairing_not_found` | 400 | Code unknown, used, or older than 10 minutes |
| `rate_limited` | 400 | Too many unknown codes from the shop's IP; wait 10 minutes |
| `transport_failed` | 400 | Backend unreachable |
| `validation_failed`, `invalid_request`, `invalid_response` | 400 | Backend refused the request, answered unexpectedly or redirected |
| code of the `PairingException` `save()` throws | 400 | Your writer refused |

> After `pairing_wrong_shop` or a failing `save()` the code is already used up → click **Connect** again.

## Error format

Every `/chatbot/v1` endpoint returns errors as:

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
The default returns the kernel locale only; override that service to serve more (the Sylius plugin
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
5 s max duration) and your `backend_url`. Pass the site from `CredentialsProviderInterface` per call:

> **The backend accepts changes only from an active site.** A draft site answers 401 — activate it in the
> dashboard first.

```php
use FluffyDiscord\Honkers\DTO\CatalogChange;
use FluffyDiscord\Honkers\Enum\CatalogSourceName;

$site = $credentialsProvider->findCurrentSite();
$canSend = $site !== null && $site->hasIngestSecret();
if ($canSend) {
    $result = $catalogIngestClient->send($site, new CatalogChange(
        CatalogSourceName::Products,
        'cs_CZ',
        ['CLIPPER-01', 'CLIPPER-02'],
    ));
}
```

Max 500 ids per call. The request sends `Authorization: Bearer <site key>.<ingest secret>`. `$result->accepted` is the 202;
`$result->isThrottled()` with `$result->retryAfterSeconds` is a 429.

## Widget

**The bundle doesn't print the widget; it gives you the pieces.** `WidgetSnippet` renders the markup,
the `widget.*` options are parameters:

```php
use FluffyDiscord\Honkers\Widget\WidgetSnippet;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ChatWidget
{
    public function __construct(
        private readonly WidgetSnippet                $widgetSnippet,
        private readonly CredentialsProviderInterface $credentialsProvider,

        #[Autowire(param: 'fluffydiscord_honkers.backend_url')]
        private readonly string $backendUrl,

        #[Autowire(param: 'fluffydiscord_honkers.widget.cdn_url')]
        private readonly string $cdnUrl,

        #[Autowire(param: 'fluffydiscord_honkers.widget.defer')]
        private readonly bool $defer,
    ) {
    }

    public function render(): string
    {
        $site = $this->credentialsProvider->findCurrentSite();
        if ($site === null) {
            return '';
        }

        return $this->widgetSnippet->render($this->backendUrl, $site->siteKey, $this->cdnUrl, '', $this->defer);
    }
}
```

Output:

```html
<script src="https://honkers.b-cdn.net/widget/v1/chat.js" defer></script>
<ai-chat-widget site-key="{site key}" backend-url="{backend_url}"></ai-chat-widget>
```

The widget talks only to `backend_url`; it never calls `/chatbot/v1` itself.
(`fluffydiscord/sylius-honkers-plugin` injects it into the shop layout for you.)

## Chat click tracking

**Visits from chat links are reported for you.** Links the chat sends carry `?gooseclid=…`; when such a
page loads with a 2xx, the bundle tells honkers.dev after the response is sent — even if the widget is
blocked. Needs `backend_url` and a site from `findCurrentSite()`.

- Reported: main GET request, no prefetch, page address without query or fragment.
- `findCurrentSite()` → `null`, empty ingest secret, or `backend_url` empty → nothing sent, nothing logged.
- `findCurrentSite()` throws → logged warning, nothing sent.
- Backend unreachable → logged warning; your page is never affected.
- Non-https `backend_url` → skipped with a logged warning outside the `dev` env.

**Report orders yourself.** Remember the `gooseclid` (e.g. in the session) and, when the order is
placed, call `FluffyDiscord\Honkers\Telemetry\TelemetryClient`:

```php
use FluffyDiscord\Honkers\DTO\ChatOrder;

$site = $credentialsProvider->findCurrentSite();
$canSend = $site !== null && $site->hasIngestSecret();
if ($canSend) {
    $telemetryClient->reportOrder($site, new ChatOrder(
        $clickId,
        'ORDER-1042',
        129900,
        'CZK',
    ));
}
```

Revenue is in ISO 4217 minor units: `129900` = 1299.00 CZK.

It throws `TelemetryException` on failure — catch and log it.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT — see [LICENSE](LICENSE).
