# Symfony Honkers Bundle

Exposes the [Honkers SDK](https://github.com/FluffyDiscord/honkers-sdk) chatbot tool-server over
HTTP: the four endpoints, access-token security, DI wiring. Ships **no domain tools** — bring your
own, or use `fluffydiscord/sylius-honkers-bundle` for Sylius defaults.

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
    api_secret: '%env(CHATBOT_API_SECRET)%'
```

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

## Tests

```bash
composer install
vendor/bin/phpunit
```
