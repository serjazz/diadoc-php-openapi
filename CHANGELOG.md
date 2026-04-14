# CHANGELOG

## 1.0.0

### Breaking changes

- Replaced legacy Diadoc auth (`DiadocAuth`, `/Authenticate`, `/V3/Authenticate`) with OpenID Connect Authorization Code flow.
- `DiadocApi` constructor signature changed:
  - old: `new DiadocApi($ddauthApiClientId, $serviceUrl, $debug, $signerProvider)`
  - new: `new DiadocApi($clientId, $clientSecret, $serviceUrl, $identityBaseUrl, $debug, $signerProvider)`
- Removed `authenticateLogin()` and `authenticateLoginV3()` usage path for obtaining API token.
- Authorization header format changed to `Authorization: Bearer <access_token>`.

### Added

- OIDC methods in `DiadocApi`:
  - `buildAuthorizationUrl()`
  - `exchangeAuthorizationCode()`
  - `refreshAccessToken()`
  - `setOAuthSession()`
  - `getOAuthSessionState()`
  - `setOAuthSessionPersistenceCallback()`
- Automatic token lifecycle handling:
  - proactive refresh by `expires_at`
  - one retry on `401` with refresh token
- Scope detection for environments:
  - `Diadoc.PublicAPI` for production
  - `Diadoc.PublicAPI.Staging` for staging/test (`diadoc-api-staging`, `diadoc-api-test`)
- Local OAuth test server for CLI flow with browser callback:
  - `docker/oauth-test/router.php`
  - `docker compose --profile oauth up oauth-test`

### Updated

- Documentation:
  - `Readme.md` rewritten for OIDC flow and Docker usage
  - `TESTING_PHP_7_1.md` updated with OAuth test scenario
  - `.env.example` updated with OIDC variables
- Test helpers:
  - `tests/helpers/ApiClient.php` now restores OAuth session from cache/env
  - `tests/Unit/AuthTest.php` updated for OIDC URL/scope behavior

### Migration from 0.4.0

1. Configure new env vars: `OAUTH_CLIENT_ID`, `OAUTH_CLIENT_SECRET`, `DIADOC_URL`, optional `OAUTH_IDENTITY_URL`.
2. Implement browser redirect + callback to get authorization `code`.
3. Exchange `code` via `exchangeAuthorizationCode()` and persist returned session.
4. Remove old login/password auth flow usage with `authenticateLogin*`.
5. Ensure `ORG_ID` belongs to the authenticated user in the selected environment.

## 0.0.3

- Generate signed content not only from file
- Fix default timezone in `DateHelper`

## 0.0.2

- Methods for Events
- Wrappers `BoxApi`, `OrganizationApi`
- `GetDocument` method
- Bugfix

## 0.0.1

Init