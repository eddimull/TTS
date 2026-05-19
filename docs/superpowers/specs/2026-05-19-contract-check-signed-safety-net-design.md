# Reinstate `contracts:check-signed` as a webhook-failure safety net

**Date:** 2026-05-19
**Status:** Approved

## Problem

Booking contracts rely solely on PandaDoc's `recipient_completed` webhook to detect
when a contract is fully signed. When a webhook is missed — PandaDoc outage, our
endpoint unavailable, malformed payload — a signed contract remains stuck in `sent`
status indefinitely:

- the booking never flips to `confirmed`
- the signed contract PDF is never archived to S3
- portal access is never granted to the booking's contacts

A proposals-era polling command (`CheckSignedContracts`, signature
`contracts:check-signed`) used to catch this. It was deleted as collateral cleanup
in commit `09f1bec` ("banish the proposals") when the legacy proposals system was
removed, because it operated on `ProposalContracts`. The `app/Console/Kernel.php`
schedule entry that invoked it was not removed, so it has thrown
`NamespaceNotFoundException` on every daily run since that deploy (Sentry issue
TTS-BAND-AD, 89 occurrences).

This design reinstates the safety net for the current booking-era contract flow.

## Goals

- A daily polling command that detects PandaDoc-completed contracts the webhook
  missed and processes them identically to the webhook.
- No duplication of completion logic between the webhook and the command.
- Fix Sentry issue TTS-BAND-AD (the orphaned schedule entry now resolves to a real
  command).

## Non-goals

- Changing the webhook itself or PandaDoc integration behavior.
- Retroactively processing contracts older than the 2-month polling window.
- Any change to proposals (the legacy system stays banished).

## Current state

The completion behavior lives in
`app/Jobs/ProcessPandadocWebhookJob::handleRecipientCompleted()`:

1. set `Contracts.status = 'completed'`
2. download the signed PDF from the PandaDoc API and store it on S3, then set
   `Contracts.asset_url` to the stored path
3. if the contract is for a `Bookings`, set the booking `status = 'confirmed'`
4. grant portal access to the booking's contacts via
   `ContactPortalService::grantPortalAccessAfterContractCompletion()`

`Contracts` is a polymorphic model (`contractable_type`/`contractable_id`) with a
`status` enum of `pending|sent|completed` and an `envelope_id` (the PandaDoc
document id). PandaDoc API auth for document read/download uses the simple API key
header `Authorization: API-Key <key>` with `config('services.pandadoc.api_key')`,
consistent with the existing `updateContractAssetURL` code.

## Design

### 1. Extract `ContractCompletionService`

New service `app/Services/ContractCompletionService.php` with a single public
method:

```php
public function markCompleted(Contracts $contract): void
```

It contains exactly the behavior currently in `handleRecipientCompleted()`:
set status, download + store the signed PDF, flip the booking to `confirmed`,
grant portal access. Portal-access failures are caught and logged (as today) so
they do not abort completion.

The method is idempotent-safe: calling it on an already-`completed` contract
re-runs the steps without error, but callers filter completed contracts out
beforehand so this is not normally exercised.

### 2. Refactor `ProcessPandadocWebhookJob`

`handleRecipientCompleted()` is reduced to: look up the contract by `envelope_id`,
then delegate to `ContractCompletionService::markCompleted()`. The
`updateContractAssetURL()` private method moves into the service. Webhook behavior
is unchanged — this is a pure relocation.

### 3. New command `CheckSignedContracts`

New command `app/Console/Commands/CheckSignedContracts.php`, signature
`contracts:check-signed`:

- Query `Contracts` where `status != 'completed'` and
  `created_at > now()->subMonths(2)` and `envelope_id` is not null.
- For each contract, GET
  `https://api.pandadoc.com/public/v1/documents/{envelope_id}` with the
  `API-Key` header.
- If the response is not OK, log a warning, increment an error count, continue.
- If the document `status` is `document.completed`, call
  `ContractCompletionService::markCompleted()` and increment a processed count.
- Wrap each contract in its own try/catch so one failure does not abort the run.
- Print processed/error counts; return exit code 0.

### 4. Re-add the schedule entry

In `app/Console/Kernel.php`, the existing orphaned entry already reads:

```php
$schedule->command('contracts:check-signed')->dailyAt('11:00');
```

No schedule change is required — once the command class exists, the entry
resolves. The comment ("in case the webhook failed") stays accurate.

## Data flow

```
PandaDoc  --webhook-->  ProcessPandadocWebhookJob.handleRecipientCompleted()
                                                   |
                                                   v
contracts:check-signed (daily) --poll PandaDoc API--> ContractCompletionService.markCompleted()
                                                   |
                                   set status=completed, store PDF,
                                   confirm booking, grant portal access
```

Both triggers converge on the single service method.

## Error handling

- Per-contract try/catch in the command: a malformed contract or API error is
  logged and the loop continues.
- Non-OK PandaDoc responses are logged as warnings and counted as errors.
- Portal-access failures inside `markCompleted` are caught and logged (unchanged
  from current webhook behavior) so the rest of completion still succeeds.

## Testing

Feature test for the command using `Http::fake()`:

- PandaDoc returns `document.completed` → contract flips to `completed`, booking
  flips to `confirmed`.
- PandaDoc returns a non-complete status → no change.
- PandaDoc API errors → run continues, error counted, no exception escapes.
- A contract older than the 2-month window is not polled.
- A contract already `completed` is not polled.

Regression test: `ProcessPandadocWebhookJob` still completes a contract after the
service extraction (booking confirmed, status set).

Storage is faked (`Storage::fake('s3')`). Portal access is exercised through the
real `ContactPortalService` path and asserted by checking the resulting
portal-access state on the booking's contacts, following existing test
conventions.

## Affected files

- `app/Services/ContractCompletionService.php` — new
- `app/Console/Commands/CheckSignedContracts.php` — new
- `app/Jobs/ProcessPandadocWebhookJob.php` — refactor to delegate
- `tests/Feature/CheckSignedContractsCommandTest.php` — new
- existing webhook test — add/confirm regression coverage

`app/Console/Kernel.php` needs no change (the schedule entry already exists).
