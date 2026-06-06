# Contract Buyer Name Override Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add an optional `buyer_name_override` to booking contracts so an organization can be named as the Buyer while the signer signs on its behalf.

**Architecture:** A nullable string column on the `contracts` table flows through the existing custom-terms save paths (web + mobile API), is returned by the mobile formatter, and changes how the Buyer name renders in the PDF and both editors. When set, the PDF/preview show the override as Buyer with "By: [signer], on behalf of [override]"; when blank, everything renders exactly as today.

**Tech Stack:** Laravel 11 (PHP), Inertia + Vue 3 + PrimeVue (web), Flutter/Dart + Riverpod (mobile). Laravel commands run via `docker compose exec app …`.

**Repos & branches:**
- `TTS` (backend + web): branch `feat/contract-buyer-name-override` off `staging` (already created). PRs target `staging`.
- `tts_bandmate` (mobile): create branch `feat/contract-buyer-name-override` off `main`. PRs target `main`.

**Build order:** Tasks 1–7 (backend + web, TTS repo) land first so the API contract is real, then Tasks 8–12 (mobile, tts_bandmate repo).

---

## PART A — Backend + Web (TTS repo)

All commands assume CWD `/home/eddie/github/TTS` and Laravel artisan/test commands run through Docker: `docker compose exec app <cmd>`.

### Task 1: Migration — add `buyer_name_override` column

**Files:**
- Create: `database/migrations/2026_06_06_000000_add_buyer_name_override_to_contracts_table.php`

- [ ] **Step 1: Generate the migration**

Run: `docker compose exec app php artisan make:migration add_buyer_name_override_to_contracts_table --table=contracts`

(If the generated filename differs, use it; the date prefix is fine.)

- [ ] **Step 2: Write the migration body**

Replace the generated file's `up`/`down` with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->string('buyer_name_override')->nullable()->after('custom_terms');
        });
    }

    public function down(): void
    {
        Schema::table('contracts', function (Blueprint $table) {
            $table->dropColumn('buyer_name_override');
        });
    }
};
```

- [ ] **Step 3: Run the migration**

Run: `docker compose exec app php artisan migrate`
Expected: migration runs, "DONE".

- [ ] **Step 4: Commit**

```bash
git add database/migrations/*_add_buyer_name_override_to_contracts_table.php
git commit -m "feat(contract): add buyer_name_override column to contracts"
```

---

### Task 2: Model — make `buyer_name_override` fillable

**Files:**
- Modify: `app/Models/Contracts.php:19-25`

- [ ] **Step 1: Add to `$fillable`**

In `app/Models/Contracts.php`, change the `$fillable` array (currently ending with `'custom_terms',`) to:

```php
    protected $fillable = [
        'envelope_id',
        'author_id',
        'status',
        'asset_url',
        'custom_terms',
        'buyer_name_override',
    ];
```

- [ ] **Step 2: Verify it loads**

Run: `docker compose exec app php artisan tinker --execute="echo in_array('buyer_name_override', (new App\Models\Contracts)->getFillable()) ? 'ok' : 'missing';"`
Expected: `ok`

- [ ] **Step 3: Commit**

```bash
git add app/Models/Contracts.php
git commit -m "feat(contract): make buyer_name_override fillable"
```

---

### Task 3: Validation — accept `buyer_name_override` in both requests

**Files:**
- Modify: `app/Http/Requests/UpdateContractsRequest.php:24-31`
- Modify: `app/Http/Requests/Mobile/UpdateBookingContractTermsRequest.php:14-21`

- [ ] **Step 1: Web request — add the rule**

In `app/Http/Requests/UpdateContractsRequest.php`, change `rules()` to:

```php
    public function rules(): array
    {
        return [
            'custom_terms' => ['required', 'array', 'max:20'],
            'custom_terms.*.title' => ['required', 'string', 'min:3', 'max:255'],
            'custom_terms.*.content' => ['required', 'string', 'min:3'],
            'buyer_name_override' => ['nullable', 'string', 'max:255'],
        ];
    }
```

- [ ] **Step 2: Mobile request — add the rule**

In `app/Http/Requests/Mobile/UpdateBookingContractTermsRequest.php`, change `rules()` to:

```php
    public function rules(): array
    {
        return [
            'custom_terms'           => ['required', 'array'],
            'custom_terms.*.title'   => ['nullable', 'string', 'max:255'],
            'custom_terms.*.content' => ['nullable', 'string'],
            'buyer_name_override'    => ['nullable', 'string', 'max:255'],
        ];
    }
```

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/UpdateContractsRequest.php app/Http/Requests/Mobile/UpdateBookingContractTermsRequest.php
git commit -m "feat(contract): validate buyer_name_override in web + mobile requests"
```

---

### Task 4: Mobile API — persist override and return it in the formatter

**Files:**
- Modify: `app/Http/Controllers/Api/Mobile/BookingsController.php:561-580`
- Modify: `app/Services/Mobile/BookingFormatter.php:55-62`
- Test: `tests/Feature/Mobile/SaveContractTermsTest.php` (create or extend)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mobile/SaveContractTermsTest.php` (if a similar test already exists for `saveContractTerms`, add the `it_persists_buyer_name_override` method to it instead and skip creating a new file):

```php
<?php

namespace Tests\Feature\Mobile;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SaveContractTermsTest extends TestCase
{
    use RefreshDatabase;

    public function test_persists_buyer_name_override_and_returns_it(): void
    {
        $user = User::factory()->create();
        $band = Bands::factory()->create();
        $band->members()->attach($user->id, ['role' => 'owner']);
        $booking = Bookings::factory()->create(['band_id' => $band->id]);

        Sanctum::actingAs($user);

        $response = $this->postJson(
            "/api/mobile/bands/{$band->id}/bookings/{$booking->id}/contract/terms",
            [
                'custom_terms' => [
                    ['title' => 'Term A', 'content' => 'Content A'],
                ],
                'buyer_name_override' => 'The City of Scott',
            ]
        );

        $response->assertOk();
        $this->assertSame(
            'The City of Scott',
            $response->json('booking.contract.buyer_name_override')
        );
        $this->assertDatabaseHas('contracts', [
            'contractable_id' => $booking->id,
            'contractable_type' => Bookings::class,
            'buyer_name_override' => 'The City of Scott',
        ]);
    }
}
```

> Note: factory/relationship setup (`Bands::factory`, `members()->attach`, band membership for `mobile.band` middleware) must match this repo's existing mobile feature tests. If an existing mobile booking test shows the correct auth/band setup, mirror it exactly. Adjust the arrange block to whatever makes the request authorize.

- [ ] **Step 2: Run it to verify it fails**

Run: `docker compose exec app php artisan test --filter=test_persists_buyer_name_override_and_returns_it`
Expected: FAIL — `buyer_name_override` is null (controller doesn't save it yet) / formatter doesn't return it.

- [ ] **Step 3: Persist the override in the controller**

In `app/Http/Controllers/Api/Mobile/BookingsController.php`, change the `$contract->update(...)` line inside `saveContractTerms` from:

```php
        $contract->update(['custom_terms' => $validated['custom_terms']]);
```

to:

```php
        $contract->update([
            'custom_terms'        => $validated['custom_terms'],
            'buyer_name_override' => $validated['buyer_name_override'] ?? null,
        ]);
```

- [ ] **Step 4: Return the override in the formatter**

In `app/Services/Mobile/BookingFormatter.php`, in the contract block (currently lines ~55-62), add the field:

```php
            $base['contract'] = [
                'id'                  => $c->id,
                'status'              => $c->status,
                'asset_url'           => $c->asset_url,
                'envelope_id'         => $c->envelope_id,
                'custom_terms'        => $c->custom_terms,
                'buyer_name_override' => $c->buyer_name_override,
                'updated_at'          => $c->updated_at?->toIso8601String(),
            ];
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose exec app php artisan test --filter=test_persists_buyer_name_override_and_returns_it`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/Mobile/BookingsController.php app/Services/Mobile/BookingFormatter.php tests/Feature/Mobile/SaveContractTermsTest.php
git commit -m "feat(contract): persist + return buyer_name_override in mobile API"
```

---

### Task 5: PDF template — render override as Buyer

**Files:**
- Modify: `resources/views/pdf/bookingContract.blade.php:9-13` (intro) and `:118-135` (signature block)
- Test: `tests/Feature/ContractPdfRenderTest.php` (create)

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/ContractPdfRenderTest.php`. This renders the blade view directly (no PDF binary) and asserts on the HTML:

```php
<?php

namespace Tests\Feature;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Contracts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractPdfRenderTest extends TestCase
{
    use RefreshDatabase;

    private function renderContract(Bookings $booking, Contacts $signer): string
    {
        return view('pdf.bookingContract', [
            'booking' => $booking->load('band', 'contract', 'contacts', 'events'),
            'logoDataUri' => '',
            'signer' => $signer,
        ])->render();
    }

    public function test_uses_signer_name_as_buyer_when_no_override(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $signer = Contacts::factory()->create(['band_id' => $band->id, 'name' => 'Mayor Jane Doe']);
        $booking->contacts()->attach($signer->id);
        Contracts::factory()->create([
            'contractable_id' => $booking->id,
            'contractable_type' => Bookings::class,
            'custom_terms' => [],
            'buyer_name_override' => null,
        ]);

        $html = $this->renderContract($booking->fresh(), $signer);

        $this->assertStringContainsString('with <strong>Mayor Jane Doe</strong>', $html);
        $this->assertStringNotContainsString('on behalf of', $html);
    }

    public function test_uses_override_as_buyer_and_signer_signs_on_behalf(): void
    {
        $band = Bands::factory()->create();
        $booking = Bookings::factory()->create(['band_id' => $band->id]);
        $signer = Contacts::factory()->create(['band_id' => $band->id, 'name' => 'Mayor Jane Doe']);
        $booking->contacts()->attach($signer->id);
        Contracts::factory()->create([
            'contractable_id' => $booking->id,
            'contractable_type' => Bookings::class,
            'custom_terms' => [],
            'buyer_name_override' => 'The City of Scott',
        ]);

        $html = $this->renderContract($booking->fresh(), $signer);

        $this->assertStringContainsString('with <strong>The City of Scott</strong>', $html);
        $this->assertStringContainsString('on behalf of The City of Scott', $html);
        $this->assertStringContainsString('Mayor Jane Doe', $html);
    }
}
```

> Note: mirror the arrange block (factories, `contacts()->attach`, `Contracts::factory`) against existing TTS tests. If `Contracts` has no factory, create the contract via `$booking->contract()->create([...])` instead.

- [ ] **Step 2: Run to verify it fails**

Run: `docker compose exec app php artisan test --filter=ContractPdfRenderTest`
Expected: FAIL — `test_uses_override...` fails because the template still renders `$signer->name` and has no "on behalf of".

- [ ] **Step 3: Add the `$buyerName` computation + intro change**

In `resources/views/pdf/bookingContract.blade.php`, at the very top of the `@section('content')` (after line 2), add:

```blade
@php
    $buyerName = ($booking->contract && $booking->contract->buyer_name_override)
        ? $booking->contract->buyer_name_override
        : $signer->name;
    $hasBuyerOverride = $booking->contract && filled($booking->contract->buyer_name_override);
@endphp
```

Then change the intro (line ~11) from:

```blade
    with <strong>{{ $signer->name }}</strong> (hereinafter referred to as "Buyer"), for the engagement of a live musical performance
```

to:

```blade
    with <strong>{{ $buyerName }}</strong> (hereinafter referred to as "Buyer"), for the engagement of a live musical performance
```

- [ ] **Step 4: Change the signature block**

In the same file, replace the signature name `<div>` (lines ~124-126):

```blade
        <div>
            <strong class="underline">{{ $signer->name }}</strong> - <strong>{{ date('m/d/Y') }}</strong>
        </div>
```

with:

```blade
        @if ($hasBuyerOverride)
            <div>
                <strong class="underline">{{ $buyerName }}</strong> - <strong>{{ date('m/d/Y') }}</strong>
            </div>
            <div>
                By: <strong>{{ $signer->name }}</strong>, on behalf of {{ $buyerName }}
            </div>
        @else
            <div>
                <strong class="underline">{{ $signer->name }}</strong> - <strong>{{ date('m/d/Y') }}</strong>
            </div>
        @endif
```

- [ ] **Step 5: Run the test to verify it passes**

Run: `docker compose exec app php artisan test --filter=ContractPdfRenderTest`
Expected: PASS (both methods)

- [ ] **Step 6: Commit**

```bash
git add resources/views/pdf/bookingContract.blade.php tests/Feature/ContractPdfRenderTest.php
git commit -m "feat(contract): render buyer_name_override as Buyer in PDF"
```

---

### Task 6: Web UI — add buyer-name-override input

**Files:**
- Modify: `resources/js/Pages/Bookings/Components/EditableContractWYSIWYG.vue` (template intro ~48-54, signature block ~222-233, props ~249-262, emits ~264, script state)
- Modify: `resources/js/Pages/Bookings/Components/ContractEditor.vue` (state ~35-38, save payload ~52, pass prop ~3-11)

- [ ] **Step 1: WYSIWYG — add prop + emit + local state**

In `EditableContractWYSIWYG.vue`, add a prop to `defineProps` (after the `band` prop, before the closing `});` at line ~261):

```js
    band: {
      type: Object,
      required: true
    },
    buyerNameOverride: {
      type: String,
      default: ''
    }
```

Change the emits (line ~264) to include the new event:

```js
  const emit = defineEmits(['update:terms', 'update:buyerNameOverride', 'save', 'generate-pdf', 'send-contract']);
```

After `const termsLocal = ref([]);` (line ~269), add a local ref seeded from the prop:

```js
  const buyerNameLocal = ref(props.buyerNameOverride ?? '');

  const emitBuyerNameUpdate = () => {
    emit('update:buyerNameOverride', buyerNameLocal.value);
  };
```

- [ ] **Step 2: WYSIWYG — add the input field in the template**

In `EditableContractWYSIWYG.vue`, in the `contract-content` block right after the intro `<div class="mb-4">…</div>` that ends at line ~54, insert:

```html
      <div
        v-if="editMode"
        class="mb-4 p-3 border border-dashed border-gray-300 rounded"
      >
        <label class="block text-sm font-medium mb-1">
          Buyer name override (optional)
        </label>
        <InputText
          v-model="buyerNameLocal"
          class="w-full"
          placeholder="Leave blank to use the signer's name"
          @input="emitBuyerNameUpdate"
        />
        <p class="text-xs text-gray-400 mt-1">
          Use when the Buyer is an organization and the signer signs on its behalf.
        </p>
      </div>
```

- [ ] **Step 3: WYSIWYG — reflect override in the intro + signature preview**

Add a computed for the displayed buyer name. After the `emitBuyerNameUpdate` function add:

```js
  const displayBuyerName = computed(() =>
    buyerNameLocal.value && buyerNameLocal.value.trim().length
      ? buyerNameLocal.value
      : props.booking.contacts[0]?.name
  );
```

In the intro (line ~51) change `{{ booking.contacts[0].name }}` to `{{ displayBuyerName }}`.

Replace the signature name block (lines ~227-229):

```html
        <div>
          <strong class="underline">{{ booking.contacts[0].name }}</strong> - <strong>{{ new Date().toLocaleDateString() }}</strong>
        </div>
```

with:

```html
        <template v-if="buyerNameLocal && buyerNameLocal.trim().length">
          <div>
            <strong class="underline">{{ displayBuyerName }}</strong> - <strong>{{ new Date().toLocaleDateString() }}</strong>
          </div>
          <div>
            By: <strong>{{ booking.contacts[0].name }}</strong>, on behalf of {{ displayBuyerName }}
          </div>
        </template>
        <template v-else>
          <div>
            <strong class="underline">{{ booking.contacts[0].name }}</strong> - <strong>{{ new Date().toLocaleDateString() }}</strong>
          </div>
        </template>
```

- [ ] **Step 4: ContractEditor — thread state through to save**

In `ContractEditor.vue`, add a ref alongside `terms` (after line ~37):

```js
const buyerNameOverride = ref(props.booking?.contract?.buyer_name_override ?? '');
```

Add a handler next to `updateTerms` (after line ~44):

```js
const updateBuyerNameOverride = (value) => {
    buyerNameOverride.value = value;
    unsavedChanges.value = true;
};
```

Change the `saveContract` payload (line ~52) from `{ custom_terms: terms.value }` to:

```js
        { custom_terms: terms.value, buyer_name_override: buyerNameOverride.value },
```

Pass the prop + listener to the child (template, after line ~5 `:band="band"`):

```html
        <EditableContractWYSIWYG
            :initial-terms="terms"
            :booking="booking"
            :band="band"
            :buyer-name-override="buyerNameOverride"
            @update:terms="updateTerms"
            @update:buyer-name-override="updateBuyerNameOverride"
            @generate-pdf="generatePDF"
            @save="saveContract"
            @send-contract="showSendContractPopup"
        />
```

- [ ] **Step 5: Build the assets to verify no compile errors**

Run: `docker compose exec app npm run build`
Expected: build succeeds with no Vue compile errors. (If the project uses a different build command, e.g. `npm run dev`/vite, use that; check `package.json` scripts.)

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Bookings/Components/EditableContractWYSIWYG.vue resources/js/Pages/Bookings/Components/ContractEditor.vue
git commit -m "feat(contract): web UI for buyer name override"
```

---

### Task 7: Backend — full suite green + open PR

- [ ] **Step 1: Run the contract-related tests**

Run: `docker compose exec app php artisan test --filter=Contract`
Expected: all PASS.

- [ ] **Step 2: Push and open PR (targets `staging`)**

```bash
git push -u origin feat/contract-buyer-name-override
gh pr create --base staging --title "feat(contract): buyer name override" --body "$(cat <<'EOF'
Adds an optional buyer name override on booking contracts so an organization can be the Buyer while the signer signs on its behalf.

- Migration + fillable for `contracts.buyer_name_override`
- Validation in web + mobile contract-terms requests
- Mobile API persists the field and returns it via BookingFormatter
- PDF renders the override as Buyer with "By: [signer], on behalf of [override]"
- Web contract editor field + live preview

Backward compatible: existing contracts (`buyer_name_override = null`) render exactly as before.

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## PART B — Mobile (tts_bandmate repo)

All commands assume CWD `/home/eddie/github/tts_bandmate`. Flutter runs on the host.

### Task 8: Branch off main

- [ ] **Step 1: Create the branch**

```bash
cd /home/eddie/github/tts_bandmate
git checkout main && git pull
git checkout -b feat/contract-buyer-name-override
```

Expected: on a new branch off latest `main`.

---

### Task 9: Model — parse `buyer_name_override`

**Files:**
- Modify: `lib/features/bookings/data/models/booking_contract.dart`
- Test: `test/features/bookings/booking_contract_test.dart` (create)

- [ ] **Step 1: Write the failing test**

Create `test/features/bookings/booking_contract_test.dart`:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:tts_bandmate/features/bookings/data/models/booking_contract.dart';

void main() {
  group('BookingContract.fromJson', () {
    test('parses buyer_name_override when present', () {
      final c = BookingContract.fromJson({
        'id': 1,
        'buyer_name_override': 'The City of Scott',
        'custom_terms': <dynamic>[],
      });
      expect(c.buyerNameOverride, 'The City of Scott');
    });

    test('buyer_name_override is null when absent', () {
      final c = BookingContract.fromJson({'id': 1});
      expect(c.buyerNameOverride, isNull);
    });
  });
}
```

> Note: confirm the package import path (`package:tts_bandmate/...`). Check the `name:` field in `pubspec.yaml` and an existing test's import to match it exactly.

- [ ] **Step 2: Run to verify it fails**

Run: `flutter test test/features/bookings/booking_contract_test.dart`
Expected: FAIL — `buyerNameOverride` getter does not exist.

- [ ] **Step 3: Add the field + parse it**

In `lib/features/bookings/data/models/booking_contract.dart`:

Add the field (after `customTerms` at line 8):

```dart
  final List<ContractTerm>? customTerms;
  final String? buyerNameOverride;
```

Add to the constructor (after `this.customTerms,` line 16):

```dart
    this.customTerms,
    this.buyerNameOverride,
```

In `fromJson`, add to the returned object (after `customTerms: terms,` line 37):

```dart
      customTerms: terms,
      buyerNameOverride: json['buyer_name_override'] as String?,
```

- [ ] **Step 4: Run the test to verify it passes**

Run: `flutter test test/features/bookings/booking_contract_test.dart`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add lib/features/bookings/data/models/booking_contract.dart test/features/bookings/booking_contract_test.dart
git commit -m "feat(contract): parse buyer_name_override in BookingContract"
```

---

### Task 10: Repository — send `buyer_name_override` in save body

**Files:**
- Modify: `lib/features/bookings/data/bookings_repository.dart:309-320`

- [ ] **Step 1: Update the method signature + body**

In `lib/features/bookings/data/bookings_repository.dart`, change `saveContractTerms` to accept and send the override:

```dart
  /// Save the booking's contract custom terms.
  Future<BookingDetail> saveContractTerms(
    int bandId,
    int bookingId,
    List<ContractTerm> terms, {
    String? buyerNameOverride,
  }) async {
    final response = await _dio.post(
      ApiEndpoints.mobileBookingContractTerms(bandId, bookingId),
      data: {
        'custom_terms': terms.map((t) => t.toJson()).toList(),
        'buyer_name_override': buyerNameOverride,
      },
    );
    return BookingDetail.fromJson(response.data['booking']);
  }
```

- [ ] **Step 2: Verify it compiles**

Run: `flutter analyze lib/features/bookings/data/bookings_repository.dart`
Expected: No errors (callers still compile because the new param is optional/named).

- [ ] **Step 3: Commit**

```bash
git add lib/features/bookings/data/bookings_repository.dart
git commit -m "feat(contract): send buyer_name_override from repository"
```

---

### Task 11: Provider — hold override + debounced save

**Files:**
- Modify: `lib/features/bookings/providers/contract_editor_provider.dart`
- Test: `test/features/bookings/contract_editor_provider_test.dart` (create or extend)

- [ ] **Step 1: Write the failing test**

Create `test/features/bookings/contract_editor_provider_test.dart`. It drives the notifier with a fake repo and asserts `updateBuyerNameOverride` sends the value:

```dart
import 'package:flutter_test/flutter_test.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:tts_bandmate/features/bookings/data/bookings_repository.dart';
import 'package:tts_bandmate/features/bookings/data/models/booking_detail.dart';
import 'package:tts_bandmate/features/bookings/data/models/contract_term.dart';
import 'package:tts_bandmate/features/bookings/providers/contract_editor_provider.dart';
import 'package:tts_bandmate/features/bookings/providers/bookings_provider.dart';

class _FakeRepo implements BookingsRepository {
  String? lastBuyerNameOverride;
  bool saveCalled = false;

  @override
  Future<BookingDetail> saveContractTerms(
    int bandId,
    int bookingId,
    List<ContractTerm> terms, {
    String? buyerNameOverride,
  }) async {
    saveCalled = true;
    lastBuyerNameOverride = buyerNameOverride;
    // Return value is not consumed by save(); throw if your BookingDetail
    // has no cheap constructor — instead mirror an existing fake in the repo's tests.
    throw UnimplementedError();
  }

  @override
  dynamic noSuchMethod(Invocation invocation) => super.noSuchMethod(invocation);
}

void main() {
  test('updateBuyerNameOverride stores the value and triggers save', () async {
    // Arrange: this needs bookingDetailProvider overridden to return a
    // BookingDetail whose contract has known terms. Mirror the existing
    // contract_editor provider test setup in this repo if one exists.
    // The assertion of interest:
    //   container.read(contractEditorProvider(key).notifier)
    //       .updateBuyerNameOverride('The City of Scott');
    //   await container.read(contractEditorProvider(key).notifier).save(force: true);
    //   expect(fakeRepo.lastBuyerNameOverride, 'The City of Scott');
  }, skip: 'Wire arrange block to match existing provider-test harness in this repo');
}
```

> Note: The provider depends on `bookingDetailProvider(_key).future`. Set up the test by overriding `bookingsRepositoryProvider` with the fake and `bookingDetailProvider` with a known `BookingDetail`, following whatever pattern existing bookings provider tests use. If no such harness exists, keep the test focused on the unit: construct the notifier path that calls `save` and assert the fake received `buyerNameOverride`. Remove the `skip` once the arrange block is wired.

- [ ] **Step 2: Run to verify it fails (or is skipped pending wiring)**

Run: `flutter test test/features/bookings/contract_editor_provider_test.dart`
Expected: the `updateBuyerNameOverride` method does not exist yet → compile error once the `skip` arrange block is filled in. (Until wired, it reports skipped.)

- [ ] **Step 3: Add `buyerNameOverride` to the state**

In `contract_editor_provider.dart`, extend `ContractEditorState`:

Add field + constructor param (after `terms`):

```dart
  const ContractEditorState({
    required this.terms,
    required this.unsavedChanges,
    this.buyerNameOverride,
    this.lastSavedAt,
    this.envelopeId,
  });

  final List<ContractTerm> terms;
  final String? buyerNameOverride;
  final bool unsavedChanges;
  final DateTime? lastSavedAt;
  final String? envelopeId;
```

Extend `copyWith`:

```dart
  ContractEditorState copyWith({
    List<ContractTerm>? terms,
    String? buyerNameOverride,
    bool? unsavedChanges,
    DateTime? lastSavedAt,
    String? envelopeId,
  }) =>
      ContractEditorState(
        terms: terms ?? this.terms,
        buyerNameOverride: buyerNameOverride ?? this.buyerNameOverride,
        unsavedChanges: unsavedChanges ?? this.unsavedChanges,
        lastSavedAt: lastSavedAt ?? this.lastSavedAt,
        envelopeId: envelopeId ?? this.envelopeId,
      );
```

- [ ] **Step 4: Seed it in `build()` and add the update method**

In `build()`, set it from the loaded contract (after computing `terms`/`withIds`, in the returned state):

```dart
    return ContractEditorState(
      terms: withIds,
      buyerNameOverride: detail.contract?.buyerNameOverride,
      unsavedChanges: stored == null,
      lastSavedAt: detail.contract?.updatedAt,
      envelopeId: detail.contract?.envelopeId,
    );
```

Add an update method (next to `updateContent`):

```dart
  void updateBuyerNameOverride(String value) {
    final current = state.value;
    if (current == null) return;
    state = AsyncData(
      current.copyWith(buyerNameOverride: value, unsavedChanges: true),
    );
    _scheduleSave();
  }
```

- [ ] **Step 5: Pass the override through `save()`**

In `save()`, change the repo call from:

```dart
      await repo.saveContractTerms(_key.bandId, _key.bookingId, current.terms);
```

to:

```dart
      await repo.saveContractTerms(
        _key.bandId,
        _key.bookingId,
        current.terms,
        buyerNameOverride: current.buyerNameOverride,
      );
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `flutter test test/features/bookings/contract_editor_provider_test.dart`
Expected: PASS (once the arrange block is wired and `skip` removed).

- [ ] **Step 7: Commit**

```bash
git add lib/features/bookings/providers/contract_editor_provider.dart test/features/bookings/contract_editor_provider_test.dart
git commit -m "feat(contract): hold + save buyer_name_override in editor provider"
```

---

### Task 12: Mobile UI — override field + signature preview

**Files:**
- Modify: `lib/features/bookings/widgets/contract/contract_editor.dart` (add field above terms list ~242-265; pass to signature block ~266-272)
- Modify: `lib/features/bookings/widgets/contract/contract_signature_block.dart`

- [ ] **Step 1: Add the override input above the terms list**

In `contract_editor.dart`, insert a new `SliverToBoxAdapter` immediately before the `SliverPadding` that wraps `ContractTermsList` (before line ~242). Only show it in edit mode:

```dart
                if (_editMode)
                  SliverToBoxAdapter(
                    child: Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Buyer name override (optional)',
                            style: CupertinoTheme.of(context)
                                .textTheme
                                .textStyle
                                .copyWith(
                                  fontWeight: FontWeight.w600,
                                  fontSize: 13,
                                ),
                          ),
                          const SizedBox(height: 6),
                          CupertinoTextField(
                            controller: TextEditingController(
                              text: state.buyerNameOverride ?? '',
                            )..selection = TextSelection.collapsed(
                                offset: (state.buyerNameOverride ?? '').length,
                              ),
                            placeholder: "Leave blank to use the signer's name",
                            onChanged: (v) => ref
                                .read(contractEditorProvider(_key).notifier)
                                .updateBuyerNameOverride(v),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Use when the Buyer is an organization and the signer signs on its behalf.',
                            style: CupertinoTheme.of(context)
                                .textTheme
                                .textStyle
                                .copyWith(
                                  color: CupertinoColors.secondaryLabel,
                                  fontSize: 11,
                                ),
                          ),
                        ],
                      ),
                    ),
                  ),
```

> Note: building a fresh `TextEditingController` in `build` mirrors the existing pattern in `contract_term_card.dart`. Keep it consistent with that file.

- [ ] **Step 2: Pass the override to the signature block**

In `contract_editor.dart`, update the `ContractSignatureBlock` usage (lines ~266-272):

```dart
                SliverToBoxAdapter(
                  child: ContractSignatureBlock(
                    firstContact: widget.booking.contacts.isEmpty
                        ? null
                        : widget.booking.contacts.first,
                    buyerNameOverride: state.buyerNameOverride,
                  ),
                ),
```

- [ ] **Step 3: Render override in the signature block**

Replace `lib/features/bookings/widgets/contract/contract_signature_block.dart` entirely with:

```dart
import 'package:flutter/cupertino.dart';
import 'package:intl/intl.dart';

import '../../data/models/booking_contact.dart';

class ContractSignatureBlock extends StatelessWidget {
  const ContractSignatureBlock({
    super.key,
    required this.firstContact,
    this.buyerNameOverride,
  });

  final BookingContact? firstContact;
  final String? buyerNameOverride;

  @override
  Widget build(BuildContext context) {
    final signerName = firstContact?.name ?? 'Buyer';
    final hasOverride =
        buyerNameOverride != null && buyerNameOverride!.trim().isNotEmpty;
    final buyerName = hasOverride ? buyerNameOverride! : signerName;
    final today = DateFormat('M/d/yyyy').format(DateTime.now());

    final bold = CupertinoTheme.of(context).textTheme.textStyle.copyWith(
          fontWeight: FontWeight.w700,
        );

    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 24),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text('Buyer', style: bold),
          const SizedBox(height: 4),
          const Text('I Agree to the terms and conditions of this contract'),
          const SizedBox(height: 8),
          Text.rich(TextSpan(children: [
            TextSpan(
              text: buyerName,
              style: bold.copyWith(decoration: TextDecoration.underline),
            ),
            const TextSpan(text: ' - '),
            TextSpan(text: today, style: bold),
          ])),
          if (hasOverride) ...[
            const SizedBox(height: 4),
            Text.rich(TextSpan(children: [
              const TextSpan(text: 'By: '),
              TextSpan(text: signerName, style: bold),
              TextSpan(text: ', on behalf of $buyerName'),
            ])),
          ],
          const SizedBox(height: 16),
          const Text('Signature: ___________________________'),
        ],
      ),
    );
  }
}
```

- [ ] **Step 4: Analyze + run the bookings tests**

Run: `flutter analyze lib/features/bookings/`
Expected: No errors.

Run: `flutter test test/features/bookings/`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add lib/features/bookings/widgets/contract/contract_editor.dart lib/features/bookings/widgets/contract/contract_signature_block.dart
git commit -m "feat(contract): mobile UI for buyer name override"
```

---

### Task 13: Mobile — full analyze/test + open PR

- [ ] **Step 1: Full analyze + test**

Run: `flutter analyze`
Expected: No issues (or only pre-existing unrelated ones).

Run: `flutter test`
Expected: all PASS.

- [ ] **Step 2: Push and open PR (targets `main`)**

```bash
git push -u origin feat/contract-buyer-name-override
gh pr create --base main --title "feat(contract): buyer name override" --body "$(cat <<'EOF'
Mobile side of the contract buyer-name-override feature (pairs with the TTS backend PR).

- Parse `buyer_name_override` in BookingContract
- Editor provider holds it + sends it via the contract-terms save
- Repository forwards `buyer_name_override` in the POST body
- Editor screen: override input field (edit mode) + signature-block preview showing "By: [signer], on behalf of [override]"

Requires the backend PR (API now accepts + returns `buyer_name_override`).

🤖 Generated with [Claude Code](https://claude.com/claude-code)
EOF
)"
```

---

## Self-Review Notes

- **Spec coverage:** migration (T1), fillable (T2), web+mobile validation (T3), mobile persist+formatter (T4), PDF render both branches (T5), web UI (T6), mobile model/repo/provider/UI (T9–T12), tests at each layer, two PRs with correct base branches (T7/T13). All spec sections map to a task.
- **Type consistency:** `buyer_name_override` (snake_case) is the API/DB/JSON key everywhere; `buyerNameOverride` (camelCase) is the Dart property; `buyerNameLocal`/`buyerNameOverride` are the Vue refs. `saveContractTerms` gains a named optional `buyerNameOverride` param used identically by the provider. `ContractSignatureBlock` gains `buyerNameOverride`.
- **Fallback semantics:** blank/whitespace override always falls back to the signer's name (PHP `filled()`, Vue `.trim().length`, Dart `.trim().isNotEmpty`) — consistent across all three layers.
- **Known assumptions flagged for the implementer:** test arrange blocks (factories, auth/band middleware setup, Riverpod provider overrides, package import path) must mirror existing tests in each repo; the Vue build command and the existence of a `Contracts` factory should be confirmed against the repo.
