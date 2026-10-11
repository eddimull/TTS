# TTS Bandmate MCP server (Claude connector)

TTS Bandmate exposes a remote [Model Context Protocol](https://modelcontextprotocol.io) server at
`https://<host>/mcp`. It offers what the band developer API offers (booked dates, events, bookings
CRUD, price estimates), but signs in as a TTS **user** through OAuth 2.1 rather than with a band
API token. One connection covers every band the user owns or is a member of.

## Connecting from claude.ai

1. In claude.ai go to **Settings → Connectors → Add custom connector**.
2. Name it "TTS Bandmate" and enter the URL `https://<host>/mcp` (production:
   `https://<production host>/mcp`). Leave the OAuth client ID/secret fields empty; Claude
   registers itself.
3. Click **Connect**. A TTS Bandmate window opens. Log in if asked, then review the consent screen
   ("Allow Claude to access TTS Bandmate as <you>?") and click **Approve**.
4. In a chat, enable the connector and ask, for example, "Is Three Thirty Seven free on
   2027-05-15?" or "Estimate a 4-hour wedding in Lafayette on 2027-05-15."

To disconnect, remove the connector in claude.ai. Refresh tokens expire after 90 days without use.

Claude Desktop uses the same claude.ai connector. Claude Code also works:
`claude mcp add --transport http tts-bandmate https://<host>/mcp` (it uses a loopback redirect,
which the server allows).

> claude.ai connects to the server **from Anthropic's cloud**, so the server must be reachable over
> public HTTPS. `https://local.tts.band:8710` won't work. To try it against a local stack, expose it
> through a tunnel and set `APP_URL` to the tunnel URL so the OAuth metadata advertises it:
>
> ```bash
> cloudflared tunnel --url http://localhost:8080      # or: ngrok http 8080
> # then in .env: APP_URL=https://<random>.trycloudflare.com, and restart the app
> ```

## Scopes

The consent screen lists the scopes the client requested. **If the client requests none (claude.ai
may omit `scope`), it is shown and, on approval, granted all four.** A scope limits what the
*connection* may do. It never adds to what the user may do: every tool also checks the user's own
role and permissions in the band.

| Scope            | Allows                                        |
|------------------|-----------------------------------------------|
| `bookings:read`  | View bookings and booked dates                |
| `bookings:write` | Create, update and delete bookings            |
| `events:read`    | View events                                   |
| `pricing:read`   | Price estimates (band owners only)            |

laravel/mcp also registers a generic `mcp:use` scope. It isn't advertised, but a client that
requests it is treated as having all four scopes. The consent screen labels it "Full access".

## Tools

`band_id` comes from `list_bands`. Subs (users who only sub for a band) can't use band tools:
the developer-API data is band-wide, and subs may only see their assigned gigs.

| Tool                 | Scope            | Band permission required       | Mirrors                        |
|----------------------|------------------|--------------------------------|--------------------------------|
| `list_bands`         | –                | – (owner/member bands only)    | –                              |
| `list_event_types`   | –                | –                              | mobile `GET /event-types`      |
| `check_availability` | `bookings:read`  | owner or `read:bookings`       | `GET /api/booked-dates`        |
| `list_events`        | `events:read`    | owner or `read:events`         | `GET /api/events`              |
| `list_bookings`      | `bookings:read`  | owner or `read:bookings`       | `GET /api/bookings`            |
| `get_booking`        | `bookings:read`  | owner or `read:bookings`       | `GET /api/bookings/{id}`       |
| `create_booking`     | `bookings:write` | owner or `write:bookings`      | `POST /api/bookings`           |
| `update_booking`     | `bookings:write` | owner or `write:bookings`      | `PATCH /api/bookings/{id}`     |
| `delete_booking`     | `bookings:write` | owner or `write:bookings`      | `DELETE /api/bookings/{id}` (destructive) |
| `add_booking_contact`| `bookings:write` | owner or `write:bookings`      | `POST /api/bookings/{id}/contacts` |
| `estimate_price`     | `pricing:read`   | **owner only**                 | `POST /api/price-estimate`     |

How the tools differ from the REST endpoints:

* `check_availability` with `date` adds `available` (true when every booking that day is cancelled
  or there are none).
* Tool arguments may carry explicit `null`s. Null date filters are ignored. In `update_booking`,
  `null` clears only `notes`, `venue_name` and `venue_address`; other null fields stay unchanged.
* Bookings created by `create_booking` have the calling user as author. REST uses the band's first
  owner.

Booking creation (`create_booking` and `POST /api/bookings`) goes through the same
`App\Services\Mobile\BookingService::createBooking()` as the mobile app: it creates the contract
(pending, seeded with `storage/app/contract/InitialTerms.json`), templates the event's
`additional_data` from the event type (load in / soundcheck / quiet; ceremony and dances for
weddings), assigns the band's default roster and sets the event value to the price. Unlike the
apps, `status` is never derived from `contract_option`: it is the given status, or `draft`.
`contact` / `contacts` (and `add_booking_contact`) attach client contacts through
`App\Services\BookingContactService`, reusing the band's contact with the same email. `is_primary`
defaults to true while the booking has no other primary contact.
* `estimate_price` returns the same JSON as REST (`JSON_PRESERVE_ZERO_FRACTION`), without escaped
  slashes or unicode.

The REST controllers and the tools share their query, validation and formatting code
(`App\Services\BandApi\*`, `PriceEstimatorService`). `tests/Feature/DeveloperApiResponseSnapshotTest`
pins the REST responses byte for byte.

## Endpoints

| Endpoint                                               | Purpose                                                   |
|--------------------------------------------------------|-----------------------------------------------------------|
| `POST /mcp`                                            | MCP (Streamable HTTP, JSON responses). 401 + `WWW-Authenticate: Bearer realm="mcp", resource_metadata="…/.well-known/oauth-protected-resource/mcp"` without a valid token |
| `GET /.well-known/oauth-protected-resource[/mcp]`      | RFC 9728 protected-resource metadata                      |
| `GET /.well-known/oauth-authorization-server[/mcp]`    | RFC 8414 authorization-server metadata                    |
| `POST /oauth/register`                                 | RFC 7591 dynamic client registration (public PKCE clients, 10/min per IP) |
| `GET /oauth/authorize`                                 | Consent screen (Inertia `OAuth/Authorize`); guests go to login and come back |
| `POST` / `DELETE /oauth/authorize`                     | Approve / deny                                            |
| `POST /oauth/token`                                    | `authorization_code` (PKCE S256 required) and `refresh_token` grants |

Access tokens last 8 hours and refresh tokens 90 days. Refresh tokens rotate on use.

Allowed redirect URIs for registration: `https://claude.ai/…`, `https://claude.com/…` and
loopback `http://localhost:*` / `http://127.0.0.1:*`. Override with `MCP_REDIRECT_DOMAINS`
(comma-separated scheme+host, or `*`).

## How auth fits together

* **Passport** (`laravel/passport` 13) is the OAuth server. It is used only by the `mcp` guard.
  Sanctum (mobile app), the band API tokens (`band.api`) and web sessions are unchanged. The
  `User` model is unchanged too: Passport's guard attaches the access token through the existing
  (Sanctum) `withAccessToken()`/`tokenCan()`, which work with Passport's `AccessToken`.
* The `mcp` guard uses the `mcp_users` provider, which loads the same users but has no `model`
  key. This keeps spatie/laravel-permission resolving band permissions under the `web` guard while
  `mcp` is the request's guard. See the comments in `config/auth.php`.
* `App\Mcp\OAuth\BandmateMcpRegistrar` replaces laravel/mcp's registrar so discovery advertises our
  scopes. `RegisterClientController` reports them from registration. Both are wired in
  `App\Providers\McpOAuthServiceProvider`.
* `AuthenticateMcp` (`auth.mcp`) always answers 401, never a login redirect, so clients get the
  OAuth challenge.

## Production deploy

1. **Install and migrate.** Run `composer install --no-dev` (it adds `laravel/passport` and
   `league/oauth2-server`), then `php artisan migrate --force`. The migrations add only the
   `oauth_auth_codes`, `oauth_access_tokens`, `oauth_refresh_tokens`, `oauth_clients` and
   `oauth_device_codes` tables.
2. **Signing keys.** Passport signs access tokens with an RSA key pair. Do one of the following:
   * Run `php artisan passport:keys` once on the server. It writes `storage/oauth-private.key` and
     `storage/oauth-public.key`, which are gitignored. Keep them across deploys (shared
     `storage/`) and `chmod 600` the private key.
   * Or set env vars, which suit containers. Use real newlines or `\n`:
     ```
     PASSPORT_PRIVATE_KEY="-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----"
     PASSPORT_PUBLIC_KEY="-----BEGIN PUBLIC KEY-----\n...\n-----END PUBLIC KEY-----"
     ```
     Generate the pair with `php artisan passport:keys --force` locally, or with `openssl genrsa`.

   Without keys, `/mcp` and `/oauth/token` return 500. Rotating the keys invalidates every issued
   access token; clients then refresh or reconnect.
3. **Public HTTPS URL.** `APP_URL` must be the public `https://` origin, and proxies must be
   trusted (`TrustProxies`), so the metadata URLs and the `WWW-Authenticate` challenge say
   `https://<host>/…`. If the issuer must differ from `APP_URL`, set `MCP_AUTHORIZATION_SERVER`.
4. **Optional env:** `MCP_REDIRECT_DOMAINS` (see above).
5. Clear caches as usual: `php artisan config:cache && php artisan route:cache`.
   `routes/ai.php` is loaded by laravel/mcp and included in the route cache.
6. Smoke test:
   ```bash
   curl -s https://<host>/.well-known/oauth-protected-resource/mcp | jq
   curl -si -X POST https://<host>/mcp -H 'Content-Type: application/json' -d '{}' | grep -i www-authenticate
   ```
   Then add the connector in claude.ai as described above.

Housekeeping (optional, e.g. daily): `php artisan passport:purge` removes revoked or expired tokens
and auth codes.

## Local testing without claude.ai

```bash
docker-compose exec app php artisan passport:keys
docker-compose exec app php artisan mcp:inspector mcp   # MCP Inspector against /mcp (OAuth flow included)
```

Tests: `tests/Feature/Mcp/` (OAuth surface plus every tool) and
`resources/js/tests/pages/OAuthAuthorize.test.js` (consent screen).
