# Mobile Token Refresh — Design

**Date:** 2026-06-14
**Status:** Approved
**Repos:** TTS (Laravel backend) + tts_bandmate (Flutter client)

## Problem

Mobile Sanctum token abilities are computed once, at issuance, by
`App\Services\Mobile\TokenService::buildAbilities()`. The list is a flat set of
strings like `read:events`, `write:bookings`, derived from the user's current
bands and permissions.

When the user's bands or roles change *after* the token is issued, the token is
not reissued, so its abilities go stale. Affected paths include:

- **Going solo** (`POST /api/mobile/bands/solo`): creates a personal band the
  user owns, but does not mint a new token. The user now owns a band where they
  *should* have `write:bookings`, but their token lacks it.
- Accepting an invite, being assigned as a sub, etc.

Concrete failure: after `goSolo`, a user tries to create a personal gig
(`POST /api/mobile/bands/{band}/bookings`). The `mobile.band:write:bookings`
middleware runs `tokenCan('write:bookings')` → false → **403 "Insufficient
token permissions"**, which is inaccurate — the user does have the right, their
token is just stale.

## Root cause

Token abilities are a point-in-time snapshot. There is no mechanism to re-sync
them with the user's current permissions short of a full logout/login.

## Goals

- Make `goSolo` immediately usable for creating a personal gig.
- Provide a durable, reactive fix for the whole stale-token class, not just
  `goSolo`.
- Do not weaken authorization.

## Non-goals (YAGNI)

- No proactive/background token refresh.
- No reissuing a token from every band/role mutation endpoint — only `goSolo`
  (the common case) plus the reactive retry path. Other endpoints can adopt the
  "return a fresh token" pattern later if a concrete need appears.

## Security analysis

Refreshing a token cannot grant access the user shouldn't have. Two independent
reasons:

1. `buildAbilities()` only grants `write:bookings` (etc.) for a band where
   `$user->canWrite('bookings', $band->id)` is true — i.e. the user owns the
   band or holds the Spatie permission. Abilities mirror real permissions.
2. The token ability is **not** band-scoped, but the
   `EnsureUserInBand` middleware checks `$user->allBands()->contains('id', $band->id)`
   **first** (returns 403 "You are not a member of this band"), and only then
   checks `tokenCan($ability)`. So even a `write:bookings` ability is useless
   against a band the user is not a member of.

The gate is therefore `(membership in band) AND (token has ability)`. Refresh
only re-syncs the *ability* half with reality; it can never bypass the
*membership* half. A security test asserts this explicitly.

## Design

### 1. Backend: `POST /api/mobile/token/refresh`

- **Route group:** `auth:sanctum` (the band-agnostic mobile group, same group
  that holds `/auth/me`, `/dashboard`, and `/bands/solo`). No band gate.
- **Behavior:**
  1. Re-run `buildAbilities($user)`.
  2. Read the current access token's `name` (device name); fall back to a
     default (e.g. `'mobile'`) if absent.
  3. Issue a new token with the same `device_name`.
  4. Delete the calling token (`$request->user()->currentAccessToken()->delete()`).
  5. Return `{ token, user, bands }` — same shape as login/register.
- **Lifecycle:** replace current device token (no accumulation; old
  under-privileged token invalidated).

### 2. Backend: `goSolo` returns a fresh token

After creating the personal band, assigning `band-owner`, and (existing)
idempotency handling:

- Mint a new token via the same replace-current-device path used by refresh.
- Return `{ token, bands }` instead of just `{ bands }`.
- The idempotent "already has a personal band" early-return path should also
  return a token for shape consistency (re-mint, since the user already owns the
  band and abilities already include it — harmless and keeps the client's
  contract uniform).

To avoid duplication, extract the "re-mint for current device + delete old"
logic into a small private helper on `TokenService` (e.g.
`reissueForCurrentDevice(User $user, ?PersonalAccessToken $current): string`),
used by both the refresh endpoint and `goSolo`.

### 3. Client (Flutter, tts_bandmate)

- **`goSolo()`** (`bands_repository.dart`): read `token` from the response,
  `storage.writeToken(token)`, update the Dio Authorization header. Still return
  the bands list to existing callers.
- **`refreshToken()`** in the auth/token repository: `POST /token/refresh`,
  persist the new token, update the header. Single source of truth for
  refresh-and-persist.
- **Refresh + auto-retry interceptor** (Dio): on a `403` whose body `message`
  equals exactly `"Insufficient token permissions"`:
  1. Call `refreshToken()` once.
  2. Retry the original request once with the new token.
  3. If it still fails, surface the error.
  - Other 403s (including "You are not a member of this band") surface
    immediately.
  - Loop guards: never refresh in response to the refresh call itself; mark the
    retried request so a second 403 does not trigger another refresh.

## Testing

### Backend (phpunit, `docker compose exec app`)
- `goSolo` response includes a `token`; the new token's abilities include
  `write:bookings` for the new personal band.
- A booking can be created on the personal band using the new token.
- The old (pre-goSolo) token is invalidated after goSolo/refresh.
- `POST /token/refresh` re-mints abilities after a role/band change (token that
  lacked an ability gains it once the underlying permission exists).
- **Security:** a refreshed token does NOT allow acting on a band the user is
  not a member of (still 403 at the membership gate).
- Refresh requires authentication (`auth:sanctum`); unauthenticated → 401.

### Client (flutter test)
- Interceptor refreshes + retries once on the specific 403 message, then
  succeeds.
- Interceptor does not loop (a persistent 403 surfaces after one retry).
- Interceptor does not refresh on other 403s.
- `goSolo()` persists the returned token.

## Affected files (anticipated)

**TTS:**
- `routes/api.php` — add `token/refresh` route.
- `app/Http/Controllers/Api/Mobile/AuthController.php` (or a small
  `TokenController`) — `refresh()` action.
- `app/Http/Controllers/Api/Mobile/OnboardingController.php` — `goSolo()` returns
  a token.
- `app/Services/Mobile/TokenService.php` — `reissueForCurrentDevice()` helper.
- `tests/Feature/Api/Mobile/` — new test(s).

**tts_bandmate:**
- `lib/core/network/api_endpoints.dart` — `mobileTokenRefresh` constant.
- `lib/features/bands/data/bands_repository.dart` — `goSolo()` persists token.
- auth/token repository — `refreshToken()`.
- Dio interceptor (wherever the auth interceptor lives) — refresh+retry.
- corresponding tests.
