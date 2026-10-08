# Upgrade from 1.x to 2.0

## Delete the secrets from the config

**The env var names stay; the bundle reads them itself.** Keep `CHATBOT_API_SECRET`,
`CHATBOT_INGEST_SECRET` and `CHATBOT_SITE_KEY` in your env.

`config/packages/fluffy_discord_honkers.yaml`

```diff
 fluffy_discord_honkers:
-    api_secret: '%env(CHATBOT_API_SECRET)%'
     backend_url: '%env(CHATBOT_BACKEND_URL)%'
-    ingest_secret: '%env(CHATBOT_INGEST_SECRET)%'
     widget:
         enabled: true
-        site_key: '%env(CHATBOT_SITE_KEY)%'
         cdn_url: '%env(CHATBOT_WIDGET_CDN_URL)%'
```

Left in → the container fails to build (`Unrecognized option`).

Gone with them: the parameters `fluffydiscord_honkers.api_secret`, `fluffydiscord_honkers.ingest_secret`
and `fluffydiscord_honkers.widget.site_key`. Read credentials from `CredentialsProviderInterface`
instead.

## Replace `SiteKeyContextInterface`

**`SiteKeyContextInterface` and `ConfiguredSiteKeyContext` are removed.** Your own site-key resolver →
implement `CredentialsProviderInterface` and alias that.

`config/services.php`

```diff
-$services->alias(FluffyDiscord\HonkersBundle\Contract\SiteKeyContextInterface::class, App\Chatbot\MySiteKeyContext::class);
+$services->alias(FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface::class, App\Chatbot\MyCredentials::class);
```

| 1.x | 2.0 |
|---|---|
| `SiteKeyContextInterface::getSiteKey(): string` | `CredentialsProviderInterface::findCurrentSite(): ?SiteCredentials` |
| `api_secret` compared by the bundle | `CredentialsProviderInterface::isApiSecretValid(string $token): bool` |

See [Store credentials elsewhere](README.md#store-credentials-elsewhere).

## Pass credentials to the SDK clients

**`CatalogIngestClient` and `TelemetryClient` take a `SiteCredentials` per call** (honkers-sdk 2.0).

```diff
-$catalogIngestClient->send($siteKey, $change);
-$telemetryClient->reportOrder($siteKey, $order);
+$site = $credentialsProvider->findCurrentSite();
+$catalogIngestClient->send($site, $change);
+$telemetryClient->reportOrder($site, $order);
```

`findCurrentSite()` returns `null` when the site key is empty. An empty ingest secret →
`$site->hasIngestSecret()` is `false`. Check both before sending.

## Drop the site key from `BackendReportGuard`

**`canReport()` takes only the feature name now.** `getMissingConfigurationKeys()` is removed; check
`$site->hasIngestSecret()` instead and use `getMissingBackendConfigurationKeys()` for `backend_url`.

```diff
-$canReport = $backendReportGuard->canReport($siteKey, 'catalog change');
+$canReport = $backendReportGuard->canReport('catalog change');
```

## Point `backend_url` at the final URL

**Calls to `backend_url` don't follow redirects anymore.** A URL that redirects (`http://` → `https://`,
bare → `www.`) → the call fails. Set the URL the redirect lands on.

## New route: `/chatbot/pair`

The bundle's route file now also loads `GET /chatbot/pair`. Login required on `^/` → let it through
anonymously, or Connect from the dashboard can't reach it. See
[Connect from the dashboard](README.md#connect-from-the-dashboard).

## Requirements

- `fluffydiscord/honkers-sdk` ^2.0.
