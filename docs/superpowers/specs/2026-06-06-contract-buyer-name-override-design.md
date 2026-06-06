# Contract Buyer Name Override — Design

**Date:** 2026-06-06
**Repos:** `TTS` (Laravel backend + Vue web UI), `tts_bandmate` (Flutter mobile)

## Problem

The booking contract uses the signer's name as the "Buyer" everywhere it appears.
When the buyer is an organization (e.g. "The City of Scott") but the signer is an
individual acting for it (e.g. the mayor), the contract is legally imprecise — the
mayor's personal name is not the buyer.

We need an optional **buyer name override** so the Buyer can be set to the
organization's name, while the signer's individual name remains on the signature
line as the person signing on the organization's behalf.

## Decisions (from brainstorming)

- **Signature handling:** Override is the Buyer; the signer signs on its behalf.
  The signature block shows the override as Buyer, and the actual signer's name
  on the signing line (e.g. "By: [Mayor], on behalf of The City of Scott").
- **Storage:** Persisted on the `contracts` table (survives across re-sends),
  edited via the same flow as `custom_terms`.
- **UIs:** Both the web app (Vue) and the mobile app (Flutter).

## Data model

Add a nullable column to the **`contracts`** table:

- `buyer_name_override` — `string`, nullable, max 255.

Add it to the `Contracts` model's `$fillable`. No cast needed (plain string).

## PDF rendering (`resources/views/pdf/bookingContract.blade.php`)

Compute once near the top of the template:

```php
$buyerName = $booking->contract->buyer_name_override ?: $signer->name;
```

(`?:` so an empty string falls back to the signer's name.)

1. **Agreement intro (line ~11):**
   `...enter into this Agreement with <strong>{{ $buyerName }}</strong>
   (hereinafter referred to as "Buyer")...`

2. **Signature block (lines ~118–134):**
   - **When an override is set:**
     - "Buyer" heading (unchanged)
     - "I Agree to the terms and conditions of this contract" (unchanged)
     - Buyer name line: `<strong>{{ $buyerName }}</strong>`
     - Signing line: `By: <strong>{{ $signer->name }}</strong>, on behalf of
       {{ $buyerName }} - <strong>{{ date('m/d/Y') }}</strong>`
   - **When no override (blank):** render exactly as today —
     `<strong class="underline">{{ $signer->name }}</strong> - {{ date('m/d/Y') }}`.

   The signature field placeholder (`{signature:user...}`) is unchanged.

## Backend (Laravel — TTS repo)

1. **Migration:** add `buyer_name_override` nullable string to `contracts`.
2. **`Contracts` model:** add `buyer_name_override` to `$fillable`.
3. **Validation:**
   - `UpdateContractsRequest` (web): add
     `'buyer_name_override' => ['nullable', 'string', 'max:255']`.
   - `Mobile/UpdateBookingContractTermsRequest`: same rule.
4. **Controllers — persist the field alongside `custom_terms`:**
   - Web: `ContractsController` save path.
   - Mobile: `Api/Mobile/BookingsController::saveContractTerms` — update the
     contract with `buyer_name_override` in addition to `custom_terms`.
5. **Mobile API formatter:** include `buyer_name_override` in the contract JSON
   returned to the mobile app (so Flutter can read it back).
6. **PDF template:** rendering logic above.

`buyer_name_override` is a separate top-level field in the request body, **not**
folded into the `custom_terms` array.

## Web UI (Vue)

- Add a single optional text input in
  `resources/js/Pages/Bookings/Components/EditableContractWYSIWYG.vue`, near the
  buyer/compensation area. Label: "Buyer name override" with helper text
  "Leave blank to use the signer's name."
- Thread the value through `ContractEditor.vue`'s `router.post` payload alongside
  `custom_terms`.

## Mobile UI (Flutter — tts_bandmate repo)

1. **`BookingContract` model** (`data/models/booking_contract.dart`): add
   `buyerNameOverride` (String?), parse in `fromJson`.
2. **`ContractEditorState` + `ContractEditorNotifier`**
   (`providers/contract_editor_provider.dart`): hold `buyerNameOverride`,
   seed it from `detail.contract?.buyerNameOverride` in `build()`, add
   `updateBuyerNameOverride(String)` using the same debounced-save path as
   `updateTitle`/`updateContent`.
3. **`saveContractTerms`** (`data/bookings_repository.dart`): include
   `buyer_name_override` in the POST body. Signature gains the override param.
4. **UI:**
   - A `CupertinoTextField` for the override near the top of the editor (above the
     terms list), wired to `updateBuyerNameOverride`.
   - `ContractSignatureBlock`: when an override is present, render the "Buyer =
     override; By: [signer], on behalf of [override]" layout to match the PDF;
     otherwise render as today.

## Testing

- **Laravel (`docker compose exec app …`):** feature test that the rendered
  contract view shows the override as Buyer (intro + signature block) when set and
  the signer's name when blank; validation accepts a valid override and rejects
  >255 chars; web + mobile save endpoints persist the field; mobile formatter
  includes it in the response.
- **Flutter:** unit tests for `BookingContract.fromJson` parsing the new field,
  and `ContractEditorNotifier.updateBuyerNameOverride` (debounce + the save body
  includes `buyer_name_override`).

## Rollout notes

- Spans two repos → two branches / two PRs. The backend API change should land
  (or the API contract be agreed) before the Flutter side integrates end-to-end.
- TTS PRs target `staging`; tts_bandmate PRs target `main`.
- Backward compatible: existing contracts have `buyer_name_override = null` and
  render exactly as before.
