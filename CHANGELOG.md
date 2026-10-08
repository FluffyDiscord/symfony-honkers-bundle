# Changelog

## v2.0.0

See [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Added

- Credentials from a service. `CredentialsProviderInterface` checks the API secret the backend sends and gives
  the site key + ingest secret of the current request. The default `EnvCredentialsProvider` reads
  `CHATBOT_API_SECRET`, `CHATBOT_INGEST_SECRET` and `CHATBOT_SITE_KEY`; alias your own to keep them in a
  database or vault.
- Connect from the dashboard. `GET /chatbot/pair?code=…` redeems the code, generates both secrets and stores them
  through your `CredentialsWriterInterface` (`findTrustedHost()`, `save()`), then sends the admin back to the
  dashboard. Refuses a connection whose base URL host isn't the shop's trusted host, or whose return URL is off
  the `backend_url` origin. Error pages in English and Czech; `PairingErrorCode` lists the codes.
- `HostMatcher` (honkers-sdk) is autowirable, to compare hosts the way pairing does.
- `widget.defer` (default `true`); `false` → the widget script loads without `defer`.

### Changed

- Requires `fluffydiscord/honkers-sdk` ^2.0 and `symfony/yaml` (the bundle's translations are YAML).
- The chat link visit is reported with the site from `findCurrentSite()`; `null` or no ingest secret → nothing
  sent.
- `findCurrentSite()` returns the site whenever the site key is set; an empty ingest secret only stops outbound
  calls, the widget still works.
- `BackendReportGuard::canReport($feature)` no longer takes the site key.
- Calls to `backend_url` don't follow redirects.
- License is MIT.

### Removed

- Config `api_secret`, `ingest_secret` and `widget.site_key`, and their parameters.
- `SiteKeyContextInterface` and `ConfiguredSiteKeyContext`.
- `BackendReportGuard::getMissingConfigurationKeys()`; use `getMissingBackendConfigurationKeys()`.
