# Symfony honkers.dev Bundle

Exposes the [honkers.dev SDK](https://github.com/FluffyDiscord/honkers-sdk) chatbot tool-server over
HTTP: the four endpoints, access-token security, DI wiring. Ships **no domain tools** — bring your
own, or use [`fluffydiscord/sylius-honkers-bundle`](https://github.com/FluffyDiscord/sylius-honkers-bundle)
for Sylius defaults.

PHP 8.1+, Symfony 6.4+.

## Endpoints

| Method | Path | Purpose |
|---|---|---|
| GET | `/chatbot/v1/tools` | list tools + JSON schema |
| POST | `/chatbot/v1/tools/{name}` | call a tool |
| GET | `/chatbot/v1/sources` | list data sources |
| GET | `/chatbot/v1/sources/{name}` | read a data source |

## Install

```bash
composer require fluffydiscord/symfony-honkers-bundle
```

```php
// config/bundles.php
FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle::class => ['all' => true],
```

```yaml
// config/packages/fluffy_discord_honkers.yaml
fluffy_discord_honkers:
    api_secret: '%env(CHATBOT_API_SECRET)%'       # inbound: Bearer the backend sends you
    backend_url: '%env(CHATBOT_BACKEND_URL)%'     # outbound: honkers.dev origin (optional)
    ingest_secret: '%env(CHATBOT_INGEST_SECRET)%' # outbound: catalog-push secret (optional)
    widget:
        enabled: true
        site_key: '%env(CHATBOT_SITE_KEY)%'
        cdn_url: '%env(CHATBOT_WIDGET_CDN_URL)%'   # optional; empty → {backend_url}/widget/v1/chat.js
```

Only `api_secret` is required. The rest power the outbound + widget services below.

Routes are not auto-loaded — import them:

```php
// config/routes/fluffy_discord_honkers.php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@FluffyDiscordHonkersBundle/config/routes.php');
};
```

Secure `/chatbot/v1` with an access-token firewall backed by `ApiSecretAuthenticator` and
`ApiAuthenticationFailureHandler`. The backend sends `Authorization: Bearer <api_secret>`.

## Add a tool

Implement `FluffyDiscord\Honkers\Contract\ChatbotToolInterface` (or `ChatbotDataSourceInterface`,
`ToolChoiceLoaderInterface`). Autoconfiguration tags it — no manual registration.

A non-Sylius app gets a request-locale-only `ChatbotLocaleContextInterface`. Override that service
to add channel-aware locales.

## Push catalog changes

Inject `FluffyDiscord\Honkers\Ingest\CatalogIngestClient` — it's wired to Symfony's HTTP client and
your `backend_url`/`ingest_secret`. Tell honkers.dev which entries changed so it re-indexes them:

```php
use FluffyDiscord\Honkers\DTO\CatalogChange;
use FluffyDiscord\Honkers\Enum\CatalogSourceName;

$result = $catalogIngestClient->send($siteKey, new CatalogChange(
    CatalogSourceName::Products,
    'cs_CZ',
    ['CLIPPER-01', 'CLIPPER-02'],   // max 500 per call
));
```

## Widget

Inject `FluffyDiscord\Honkers\Widget\WidgetSnippet` to render the chat embed markup from your
`widget.*` config. (`fluffydiscord/sylius-honkers-bundle` injects it into the shop layout for you.)

## Tests

```bash
composer install
vendor/bin/phpunit
```
