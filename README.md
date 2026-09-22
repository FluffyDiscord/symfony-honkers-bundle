# Symfony Honkers Bundle

Vanilla Symfony bundle that exposes the [Honkers SDK](https://github.com/FluffyDiscord/honkers-sdk)
chatbot tool-server over HTTP. It wires the SDK's registries, schema generator and validator,
adds the four API endpoints, the access-token security and the exception-to-JSON listener — and
ships **no domain tools of its own**. Bring your own tools and data sources; Sylius shops use
`fluffydiscord/sylius-honkers-bundle` on top for ready-made defaults.

Requires PHP 8.1+ and Symfony 6.4+.

## Endpoints

| method | path | purpose |
|---|---|---|
| GET | `/chatbot/v1/tools` | list tools + JSON-schema |
| POST | `/chatbot/v1/tools/{name}` | call a tool |
| GET | `/chatbot/v1/sources` | list data sources |
| GET | `/chatbot/v1/sources/{name}` | read a data source |

## Install

```bash
composer require fluffydiscord/symfony-honkers-bundle
```

Register the bundle, configure the shared secret and import the routes:

```php
// config/bundles.php
FluffyDiscord\HonkersBundle\FluffyDiscordHonkersBundle::class => ['all' => true],
```

```yaml
# config/packages/fluffy_discord_honkers.yaml
fluffy_discord_honkers:
    api_secret: '%env(CHATBOT_API_SECRET)%'
```

```php
// config/routes/fluffy_discord_honkers.php
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return static function (RoutingConfigurator $routes): void {
    $routes->import('@FluffyDiscordHonkersBundle/config/routes.php');
};
```

Secure the endpoints with an access-token firewall backed by the bundle's
`ApiSecretAuthenticator` / `ApiAuthenticationFailureHandler`.

## Add a tool

Implement `FluffyDiscord\Honkers\Contract\ChatbotToolInterface` (or `ChatbotDataSourceInterface`,
`ToolChoiceLoaderInterface`). Autoconfiguration tags it — no manual registration. A non-Sylius app
gets a request-locale-only `ChatbotLocaleContextInterface`; override that service to add channels.

## Tests

```bash
composer install
vendor/bin/phpunit
```
