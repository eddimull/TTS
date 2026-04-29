# Questionnaires Part 2 Implementation Plan (Phases 5–6)

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Complete the questionnaires feature by adding the client-facing portal flow (Phase 5) and the band-side event integration (Phase 6).

**Architecture:** Builds on the foundation from part 1 (`docs/superpowers/plans/2026-04-28-questionnaires.md`). Phase 5 adds a `PortalQuestionnaireController` that renders + accepts responses through the existing `auth:contact` guard. Phase 6 adds a `QuestionnaireMappingService`, an `EventQuestionnaireController` for apply/append actions, and a Vue summary panel inside the existing event editor.

**Tech Stack:** Same as part 1 — Laravel 12, Inertia v1, Vue 3, PrimeVue, Tailwind 3, Spatie Permission/Activitylog, PHPUnit 11, Vitest.

**Spec:** `docs/superpowers/specs/2026-04-28-questionnaires-design.md` (Sections 5 and 6).
**Part 1 plan:** `docs/superpowers/plans/2026-04-28-questionnaires.md`.

---

## Critical project rules (read before starting)

Same as part 1. Recap:

1. **All shell commands run inside Docker** via `docker-compose exec app …` (the `app` container handles PHP, npm, and vitest — there is no separate `node` container).
2. **Test method names use the `test_` prefix** — never `it_`, never `/** @test */`, never `#[Test]`.
3. **All migrations are generated via `php artisan make:migration`**, then edited.
4. **Never alter previously deployed migrations.** Fix forward.
5. **Never edit `database/schema/mysql-schema.sql`** directly.
6. **Never run `php artisan schema:dump` on this branch.**
7. **Run tests via** `php artisan test --parallel --processes=4`.
8. **Run `php artisan ziggy:generate`** after route changes.
9. **Commits use HEREDOC format** with the `Co-Authored-By` trailer.

## Phase overview

- **Phase 5: Client portal flow** (Tasks 25–30) — portal controller, autosave, submit, dashboard surfacing, Vue Show page
- **Phase 6: Event integration** (Tasks 31–36) — mapping service, event controller, summary panel on event editor

Each phase ends with a checkpoint where backend + frontend tests pass.

---

# Phase 5 — Client portal flow

Goal: clients can open a questionnaire from a deep-link in the email (or from their portal dashboard), see all visible fields, save answers as they go (autosave on blur), submit, then re-edit until the band locks the instance. End of phase: a Dusk-free integration test that simulates a full client flow passes.

## Task 25: PortalQuestionnaireController + Show endpoint (TDD)

**Files:**
- Create: `app/Http/Controllers/Contact/PortalQuestionnaireController.php`
- Modify: `routes/contact.php` (add three routes)
- Create: `tests/Feature/Questionnaires/PortalQuestionnaireTest.php`

This task implements the **show** action only. Save and submit follow in Tasks 26–27. We TDD the test class incrementally — start with show-related tests, add response/submit tests in subsequent tasks.

- [ ] **Step 1: Write the failing show tests**

Create `tests/Feature/Questionnaires/PortalQuestionnaireTest.php`:

```php
<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireInstanceFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private Bookings $booking;
    private Contacts $contact;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $this->instance = QuestionnaireInstances::factory()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
        ]);

        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'label' => 'Bride Name',
            'position' => 10,
        ]);
    }

    public function test_contact_can_view_questionnaire_via_portal(): void
    {
        $response = $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(200);
        $response->assertInertia(fn ($a) => $a
            ->component('Contact/Questionnaire/Show')
            ->has('instance')
            ->has('fields', 1));
    }

    public function test_non_booking_contact_cannot_view_questionnaire(): void
    {
        $other = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);

        $response = $this->actingAs($other, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(403);
    }

    public function test_first_open_stamps_first_opened_at(): void
    {
        $this->assertNull($this->instance->first_opened_at);

        $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $this->instance->refresh();
        $this->assertNotNull($this->instance->first_opened_at);
    }

    public function test_first_opened_at_is_not_overwritten_on_subsequent_views(): void
    {
        $original = now()->subHour();
        $this->instance->update(['first_opened_at' => $original]);

        $this->actingAs($this->contact, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $this->instance->refresh();
        $this->assertEquals($original->timestamp, $this->instance->first_opened_at->timestamp);
    }

    public function test_other_contact_on_booking_can_also_view(): void
    {
        $partner = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($partner);

        $response = $this->actingAs($partner, 'contact')->get(
            route('portal.booking.questionnaire.show', [$this->booking->id, $this->instance->id])
        );

        $response->assertStatus(200);
    }
}
```

- [ ] **Step 2: Run failing tests**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php
```

Expected: FAIL — route does not exist yet.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Contact/PortalQuestionnaireController.php`:

```php
<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Models\QuestionnaireInstances;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PortalQuestionnaireController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:contact');
    }

    public function show(Bookings $booking, QuestionnaireInstances $instance): Response
    {
        $this->authorizeAccess($booking, $instance);

        if ($instance->first_opened_at === null) {
            $instance->update(['first_opened_at' => now()]);
        }

        $fields = $instance->fields()->orderBy('position')->get();
        $responses = $instance->responses()->get()->mapWithKeys(
            fn ($r) => [$r->instance_field_id => $this->decodeValue($r->value)]
        );

        return Inertia::render('Contact/Questionnaire/Show', [
            'booking' => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date->format('M j, Y'),
                'band_name' => $booking->band->name,
            ],
            'instance' => [
                'id' => $instance->id,
                'name' => $instance->name,
                'description' => $instance->description,
                'status' => $instance->status,
                'submitted_at' => $instance->submitted_at?->format('M j, Y'),
                'is_locked' => $instance->isLocked(),
            ],
            'fields' => $fields,
            'responses' => $responses,
        ]);
    }

    /**
     * Auth check: contact must be on the booking, and the instance must belong to it.
     */
    private function authorizeAccess(Bookings $booking, QuestionnaireInstances $instance): void
    {
        $contact = Auth::guard('contact')->user();
        abort_if($instance->booking_id !== $booking->id, 404);
        abort_unless($booking->contacts->contains('id', $contact->id), 403);
    }

    /**
     * Multi-value responses are JSON-encoded arrays. Decode for Vue.
     */
    private function decodeValue(?string $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : $value;
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/contact.php`, inside the `Route::middleware('auth:contact')->group(...)` block, add:

```php
    Route::get('/booking/{booking}/questionnaire/{instance}', [\App\Http\Controllers\Contact\PortalQuestionnaireController::class, 'show'])
        ->name('portal.booking.questionnaire.show');
    Route::patch('/booking/{booking}/questionnaire/{instance}/responses', [\App\Http\Controllers\Contact\PortalQuestionnaireController::class, 'saveResponse'])
        ->name('portal.booking.questionnaire.respond');
    Route::post('/booking/{booking}/questionnaire/{instance}/submit', [\App\Http\Controllers\Contact\PortalQuestionnaireController::class, 'submit'])
        ->name('portal.booking.questionnaire.submit');
```

(`saveResponse` and `submit` controller methods come in Tasks 26–27. Defining the routes now is fine — they'll just 404 until the methods exist. The tests in Task 25 only exercise `show`.)

- [ ] **Step 5: Regenerate Ziggy**

```
docker-compose exec app php artisan ziggy:generate
```

- [ ] **Step 6: Create a placeholder Vue page**

Tests use `assertInertia(fn ($a) => $a->component('Contact/Questionnaire/Show'))`. Inertia's component check passes as long as the page name matches; a stub file is enough until Task 28 fleshes it out.

Create `resources/js/Pages/Contact/Questionnaire/Show.vue`:

```vue
<template>
  <div>
    <h1>{{ instance.name }}</h1>
  </div>
</template>

<script setup>
defineProps({
  booking: Object,
  instance: Object,
  fields: Array,
  responses: Object,
});
</script>
```

- [ ] **Step 7: Run tests to verify they pass**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php
```

Expected: PASS — 5 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Contact/PortalQuestionnaireController.php routes/contact.php resources/js/Pages/Contact/Questionnaire/Show.vue tests/Feature/Questionnaires/PortalQuestionnaireTest.php
git add -A # ziggy regen
git commit -m "$(cat <<'EOF'
Add portal questionnaire show endpoint

Renders the client-facing view of a sent questionnaire. Authenticated via
the existing contact guard. Auth gate: contact must be on the booking;
instance must belong to the booking. Stamps first_opened_at on first GET.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 26: Save-response endpoint with autosave-on-blur semantics (TDD)

**Files:**
- Create: `app/Http/Requests/SaveResponseRequest.php`
- Modify: `app/Http/Controllers/Contact/PortalQuestionnaireController.php` (add `saveResponse` method)
- Modify: `tests/Feature/Questionnaires/PortalQuestionnaireTest.php` (append save-response tests)

- [ ] **Step 1: Append failing tests to the existing file**

Open `tests/Feature/Questionnaires/PortalQuestionnaireTest.php`. Add these tests at the bottom of the class (just before the closing `}`):

```php
    public function test_response_save_upserts_response_row(): void
    {
        $field = $this->instance->fields()->first();

        $response = $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'Jane Smith']
            );

        $response->assertStatus(200);
        $this->assertDatabaseHas('questionnaire_responses', [
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane Smith',
        ]);

        // Second save with new value upserts (does not duplicate)
        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'Jane Doe']
            );

        $this->assertSame(
            1,
            \App\Models\QuestionnaireResponses::where('instance_field_id', $field->id)->count()
        );
        $this->assertDatabaseHas('questionnaire_responses', [
            'instance_field_id' => $field->id,
            'value' => 'Jane Doe',
        ]);
    }

    public function test_response_save_transitions_status_from_sent_to_in_progress(): void
    {
        $this->assertSame('sent', $this->instance->status);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'X']
            )
            ->assertStatus(200);

        $this->instance->refresh();
        $this->assertSame('in_progress', $this->instance->status);
    }

    public function test_response_save_does_not_change_status_when_already_submitted(): void
    {
        $this->instance->update(['status' => 'submitted', 'submitted_at' => now()]);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'updated']
            )
            ->assertStatus(200);

        $this->instance->refresh();
        $this->assertSame('submitted', $this->instance->status);
    }

    public function test_response_save_blocked_when_locked(): void
    {
        $this->instance->update(['status' => 'locked', 'locked_at' => now()]);
        $field = $this->instance->fields()->first();

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $field->id, 'value' => 'X']
            )
            ->assertStatus(403);
    }

    public function test_response_save_rejects_field_from_different_instance(): void
    {
        $otherInstance = QuestionnaireInstances::factory()->create();
        $foreignField = QuestionnaireInstanceFields::factory()->create(['instance_id' => $otherInstance->id]);

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $foreignField->id, 'value' => 'X']
            )
            ->assertStatus(422);
    }

    public function test_response_save_encodes_array_for_multi_value_field(): void
    {
        $multiField = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'multi_select',
            'position' => 20,
        ]);

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->patch(
                route('portal.booking.questionnaire.respond', [$this->booking->id, $this->instance->id]),
                ['instance_field_id' => $multiField->id, 'value' => ['rock', 'jazz']]
            )
            ->assertStatus(200);

        $stored = \App\Models\QuestionnaireResponses::where('instance_field_id', $multiField->id)->first();
        $this->assertSame(['rock', 'jazz'], json_decode($stored->value, true));
    }
```

- [ ] **Step 2: Run failing tests (only the new ones will fail)**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php
```

Expected: 5 of the new tests fail (route works because we registered it earlier, but the controller method doesn't exist yet → either 405 or NotFoundHttpException).

- [ ] **Step 3: Create SaveResponseRequest**

Create `app/Http/Requests/SaveResponseRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\QuestionnaireInstanceFields;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('contact') !== null;
    }

    public function rules(): array
    {
        return [
            'instance_field_id' => 'required|integer',
            'value' => 'nullable',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $instance = $this->route('instance');
            $fieldId = $this->input('instance_field_id');
            if (!$fieldId || !$instance) return;

            $exists = QuestionnaireInstanceFields::where('id', $fieldId)
                ->where('instance_id', $instance->id)
                ->exists();
            if (!$exists) {
                $v->errors()->add('instance_field_id', 'Field does not belong to this instance.');
            }
        });
    }
}
```

- [ ] **Step 4: Add the saveResponse method to PortalQuestionnaireController**

Open `app/Http/Controllers/Contact/PortalQuestionnaireController.php`. Add a `use` statement at the top:

```php
use App\Http\Requests\SaveResponseRequest;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireResponses;
```

Then add this method to the class (after `show`):

```php
    public function saveResponse(SaveResponseRequest $request, Bookings $booking, QuestionnaireInstances $instance): \Illuminate\Http\JsonResponse
    {
        $this->authorizeAccess($booking, $instance);
        abort_if($instance->isLocked(), 403, 'This questionnaire is locked.');

        $field = QuestionnaireInstanceFields::findOrFail($request->input('instance_field_id'));
        $value = $this->encodeValue($request->input('value'), $field->type);

        QuestionnaireResponses::updateOrCreate(
            [
                'instance_id' => $instance->id,
                'instance_field_id' => $field->id,
            ],
            ['value' => $value]
        );

        if ($instance->status === QuestionnaireInstances::STATUS_SENT) {
            $instance->update(['status' => QuestionnaireInstances::STATUS_IN_PROGRESS]);
        }

        return response()->json(['saved_at' => now()->toIso8601String()]);
    }

    /**
     * Multi-value field types (multi_select, checkbox_group) JSON-encode their array.
     * Other types coerce to string.
     */
    private function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }
        if (in_array($type, ['multi_select', 'checkbox_group'], true)) {
            return is_array($value) ? json_encode(array_values($value)) : json_encode([$value]);
        }
        return is_array($value) ? implode(',', $value) : (string) $value;
    }
```

- [ ] **Step 5: Run tests to verify they pass**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php
```

Expected: PASS — all tests (5 from Task 25 + 6 new = 11).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Contact/PortalQuestionnaireController.php app/Http/Requests/SaveResponseRequest.php tests/Feature/Questionnaires/PortalQuestionnaireTest.php
git commit -m "$(cat <<'EOF'
Add portal saveResponse endpoint with autosave semantics

Upserts a QuestionnaireResponses row keyed by (instance, field). Multi-value
field types JSON-encode their array. Transitions instance status from sent
to in_progress on first save. Returns 403 when locked.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 27: Submit endpoint with hidden-field wipe (TDD)

**Files:**
- Modify: `app/Http/Controllers/Contact/PortalQuestionnaireController.php` (add `submit` method)
- Modify: `tests/Feature/Questionnaires/PortalQuestionnaireTest.php` (append submit tests)

- [ ] **Step 1: Append failing submit tests**

Add these tests at the bottom of `PortalQuestionnaireTest.php`:

```php
    public function test_submit_transitions_status_to_submitted(): void
    {
        $field = $this->instance->fields()->first();
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);

        $this->instance->refresh();
        $this->assertSame('submitted', $this->instance->status);
        $this->assertNotNull($this->instance->submitted_at);
    }

    public function test_submit_validation_fails_when_required_field_missing(): void
    {
        $this->instance->fields()->first()->update(['required' => true]);

        $this->actingAs($this->contact, 'contact')
            ->withHeaders(['Accept' => 'application/json'])
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(422);

        $this->instance->refresh();
        $this->assertSame('sent', $this->instance->status);
    }

    public function test_submit_validation_succeeds_when_required_field_is_hidden_by_visibility_rule(): void
    {
        $controller = $this->instance->fields()->first();
        $controller->update(['type' => 'yes_no', 'label' => 'Have a wedding party?']);

        $hiddenRequired = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'label' => 'How many?',
            'required' => true,
            'position' => 20,
            'visibility_rule' => [
                'depends_on' => $controller->id,
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ]);

        // Controller answered 'no' → hiddenRequired is invisible, so its emptiness shouldn't block submit
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $controller->id,
            'value' => 'no',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);

        $this->instance->refresh();
        $this->assertSame('submitted', $this->instance->status);
    }

    public function test_submit_wipes_responses_for_hidden_fields(): void
    {
        $controller = $this->instance->fields()->first();
        $controller->update(['type' => 'yes_no']);

        $hidden = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'position' => 20,
            'visibility_rule' => [
                'depends_on' => $controller->id,
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ]);

        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $controller->id,
            'value' => 'no',
        ]);
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $hidden->id,
            'value' => 'stale data',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);

        $this->assertDatabaseMissing('questionnaire_responses', [
            'instance_field_id' => $hidden->id,
        ]);
    }

    public function test_submit_re_submit_updates_submitted_at(): void
    {
        $field = $this->instance->fields()->first();
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'a',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);
        $this->instance->refresh();
        $firstSubmittedAt = $this->instance->submitted_at;

        // Wait 1 second so the timestamp differs
        $this->travel(1)->seconds();

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);
        $this->instance->refresh();

        $this->assertGreaterThan($firstSubmittedAt->timestamp, $this->instance->submitted_at->timestamp);
    }

    public function test_submit_blocked_when_locked(): void
    {
        $this->instance->update(['status' => 'locked', 'locked_at' => now()]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(403);
    }

    public function test_submit_notifies_band_owner(): void
    {
        \Illuminate\Support\Facades\Notification::fake();

        $owner = \App\Models\User::factory()->create();
        $this->band->owners()->create(['user_id' => $owner->id]);

        $field = $this->instance->fields()->first();
        \App\Models\QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'a',
        ]);

        $this->actingAs($this->contact, 'contact')
            ->post(route('portal.booking.questionnaire.submit', [$this->booking->id, $this->instance->id]))
            ->assertStatus(302);

        \Illuminate\Support\Facades\Notification::assertSentTo($owner, \App\Notifications\QuestionnaireSubmitted::class);
    }
```

- [ ] **Step 2: Run failing tests**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php --filter=test_submit
```

Expected: all submit tests fail (route exists but controller method missing).

- [ ] **Step 3: Add the submit method to PortalQuestionnaireController**

In `app/Http/Controllers/Contact/PortalQuestionnaireController.php`, add these `use` statements at the top:

```php
use App\Notifications\QuestionnaireSubmitted;
use App\Services\QuestionnaireVisibilityEvaluator;
```

Then add this method to the class:

```php
    public function submit(Request $request, Bookings $booking, QuestionnaireInstances $instance, QuestionnaireVisibilityEvaluator $evaluator): RedirectResponse
    {
        $this->authorizeAccess($booking, $instance);
        abort_if($instance->isLocked(), 403, 'This questionnaire is locked.');

        $fields = $instance->fields()->orderBy('position')->get();
        $responses = $instance->responses()->get()->keyBy('instance_field_id');

        $fieldsArray = $fields->map(fn ($f) => [
            'id' => $f->id,
            'visibility_rule' => $f->visibility_rule,
        ])->all();
        $responsesArray = $responses->map(fn ($r) => $this->decodeValue($r->value))->all();

        // Wipe responses for hidden fields
        foreach ($fields as $f) {
            if (!$evaluator->isVisible($f->id, $fieldsArray, $responsesArray)) {
                $instance->responses()->where('instance_field_id', $f->id)->delete();
                unset($responsesArray[$f->id]);
            }
        }

        // Validate visible required fields
        $missing = [];
        foreach ($fields as $f) {
            if (!$f->required) continue;
            if (!$evaluator->isVisible($f->id, $fieldsArray, $responsesArray)) continue;

            $value = $responsesArray[$f->id] ?? null;
            if ($value === null || $value === '' || $value === [] || (is_array($value) && empty($value))) {
                $missing[] = $f->id;
            }
        }

        if (!empty($missing)) {
            return back()->withErrors(['fields' => $missing])->setStatusCode(422);
        }

        $wasAlreadySubmitted = $instance->status === QuestionnaireInstances::STATUS_SUBMITTED;

        $instance->update([
            'status' => QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);

        $this->notifyBandOwner($instance, $wasAlreadySubmitted);

        return redirect()->route('portal.dashboard')
            ->with('success', 'Thanks! Your answers have been saved.');
    }

    private function notifyBandOwner(QuestionnaireInstances $instance, bool $isUpdate): void
    {
        $owner = $instance->booking->band->owners()->orderBy('created_at')->first();
        if ($owner && $owner->user_id) {
            $user = \App\Models\User::find($owner->user_id);
            if ($user) {
                $user->notify(new QuestionnaireSubmitted($instance, $isUpdate));
            }
        }
    }
```

Note: the `submit` method needs `Request` injected and `QuestionnaireVisibilityEvaluator` (which Laravel will autowire). Also adjust the existing `use Illuminate\Http\Request;` import block if it's not already there.

- [ ] **Step 4: Run tests to verify they pass**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/PortalQuestionnaireTest.php
```

Expected: PASS — all tests (5 from Task 25 + 6 from Task 26 + 7 new = 18).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Contact/PortalQuestionnaireController.php tests/Feature/Questionnaires/PortalQuestionnaireTest.php
git commit -m "$(cat <<'EOF'
Add portal submit endpoint with hidden-field wipe and band notification

Wipes responses for fields hidden by visibility rules before validating
required fields. Re-submit updates submitted_at and re-notifies the band
owner with isUpdate=true. Returns 422 if a visible required field is empty.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 28: Build the client-facing Show.vue

**Files:**
- Modify (replace stub): `resources/js/Pages/Contact/Questionnaire/Show.vue`

This is the visible portal page. Uses `ContactLayout` (the existing thin shell), renders fields with conditional logic via the JS visibility evaluator (already built in part 1), autosaves on blur, submits via the existing endpoint.

- [ ] **Step 1: Replace `resources/js/Pages/Contact/Questionnaire/Show.vue`**

```vue
<template>
  <ContactLayout>
    <div class="max-w-2xl mx-auto py-8 px-4">
      <div class="bg-white dark:bg-slate-800 rounded-2xl shadow-xl p-6 md:p-8">
        <div class="mb-6">
          <p class="text-sm text-gray-500 mb-1">{{ booking.band_name }} — {{ booking.name }} · {{ booking.date }}</p>
          <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-50">{{ instance.name }}</h1>
          <p v-if="instance.description" class="text-gray-600 dark:text-gray-300 mt-2">
            {{ instance.description }}
          </p>
        </div>

        <div
          v-if="instance.is_locked"
          class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900"
        >
          This questionnaire has been locked by the band. Contact them if you need to make changes.
        </div>
        <div
          v-else-if="instance.status === 'submitted'"
          class="mb-6 p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900"
        >
          Submitted on {{ instance.submitted_at }}. You can still update your answers below.
        </div>
        <div
          v-else
          class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-900"
        >
          Save your answers as you go. Click <strong>Submit</strong> when finished.
        </div>

        <form @submit.prevent="submit">
          <template v-for="field in fields" :key="field.id">
            <div v-if="isVisible(field.id)" class="mb-5">
              <h3 v-if="field.type === 'header'" class="text-xl font-semibold mt-6 mb-2 border-b dark:border-slate-700 pb-1">
                {{ field.label }}
              </h3>
              <p v-else-if="field.type === 'instructions'" class="text-sm text-gray-600 dark:text-gray-300 italic">
                {{ field.label }}
              </p>
              <div v-else>
                <label class="block font-medium mb-1 dark:text-gray-100">
                  {{ field.label }}
                  <span v-if="field.required" class="text-red-600">*</span>
                </label>
                <p v-if="field.help_text" class="text-xs text-gray-500 dark:text-gray-400 mb-2">{{ field.help_text }}</p>

                <InputText
                  v-if="field.type === 'short_text'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <Textarea
                  v-else-if="field.type === 'long_text'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  rows="3"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'date'"
                  v-model="answers[field.id]"
                  type="date"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'time'"
                  v-model="answers[field.id]"
                  type="time"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'email'"
                  v-model="answers[field.id]"
                  type="email"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <InputText
                  v-else-if="field.type === 'phone'"
                  v-model="answers[field.id]"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @blur="saveField(field)"
                />
                <Select
                  v-else-if="field.type === 'dropdown'"
                  v-model="answers[field.id]"
                  :options="field.settings?.options ?? []"
                  option-label="label"
                  option-value="value"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @change="saveField(field)"
                />
                <MultiSelect
                  v-else-if="field.type === 'multi_select'"
                  v-model="answers[field.id]"
                  :options="field.settings?.options ?? []"
                  option-label="label"
                  option-value="value"
                  :disabled="instance.is_locked"
                  class="w-full"
                  @change="saveField(field)"
                />
                <div v-else-if="field.type === 'checkbox_group'" class="flex flex-col gap-2">
                  <div v-for="opt in (field.settings?.options ?? [])" :key="opt.value" class="flex items-center gap-2">
                    <Checkbox
                      v-model="answers[field.id]"
                      :value="opt.value"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    />
                    <label>{{ opt.label }}</label>
                  </div>
                </div>
                <div v-else-if="field.type === 'yes_no'" class="flex gap-4">
                  <div class="flex items-center gap-2">
                    <RadioButton
                      v-model="answers[field.id]"
                      value="yes"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    /><label>Yes</label>
                  </div>
                  <div class="flex items-center gap-2">
                    <RadioButton
                      v-model="answers[field.id]"
                      value="no"
                      :disabled="instance.is_locked"
                      @change="saveField(field)"
                    /><label>No</label>
                  </div>
                </div>

                <p
                  v-if="savedField === field.id"
                  class="text-xs text-emerald-600 mt-1 transition-opacity duration-500"
                >
                  Saved
                </p>
                <p
                  v-if="errors[field.id]"
                  class="text-xs text-red-600 mt-1"
                >
                  {{ errors[field.id] }}
                </p>
              </div>
            </div>
          </template>

          <div v-if="!instance.is_locked" class="mt-8 flex justify-end">
            <Button
              type="submit"
              :label="submitting ? (instance.status === 'submitted' ? 'Updating…' : 'Submitting…') : (instance.status === 'submitted' ? 'Update' : 'Submit')"
              :disabled="submitting"
              size="large"
            />
          </div>
        </form>
      </div>
    </div>
  </ContactLayout>
</template>

<script setup>
import { ref, reactive, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import ContactLayout from '@/Layouts/ContactLayout.vue'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Select from 'primevue/select'
import MultiSelect from 'primevue/multiselect'
import Checkbox from 'primevue/checkbox'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'
import { isFieldVisible } from './visibility.js'

const props = defineProps({
  booking: { type: Object, required: true },
  instance: { type: Object, required: true },
  fields: { type: Array, default: () => [] },
  responses: { type: Object, default: () => ({}) },
})

// Initialize answers from server-provided responses, defaulting types appropriately
const answers = reactive({})
props.fields.forEach((f) => {
  const fromServer = props.responses[f.id]
  if (fromServer !== undefined && fromServer !== null) {
    answers[f.id] = fromServer
  } else if (f.type === 'multi_select' || f.type === 'checkbox_group') {
    answers[f.id] = []
  } else {
    answers[f.id] = ''
  }
})

const submitting = ref(false)
const savedField = ref(null)
const errors = reactive({})

function isVisible(fieldId) {
  return isFieldVisible(fieldId, props.fields, answers)
}

async function saveField(field) {
  if (props.instance.is_locked) return
  errors[field.id] = null

  try {
    await axios.patch(
      route('portal.booking.questionnaire.respond', {
        booking: props.booking.id,
        instance: props.instance.id,
      }),
      {
        instance_field_id: field.id,
        value: answers[field.id],
      }
    )
    savedField.value = field.id
    setTimeout(() => {
      if (savedField.value === field.id) savedField.value = null
    }, 1500)
  } catch (err) {
    errors[field.id] = 'Could not save. We will retry on your next change.'
  }
}

function submit() {
  if (props.instance.is_locked) return
  submitting.value = true
  router.post(
    route('portal.booking.questionnaire.submit', {
      booking: props.booking.id,
      instance: props.instance.id,
    }),
    {},
    {
      onError: (e) => {
        submitting.value = false
        if (e.fields) {
          ;(Array.isArray(e.fields) ? e.fields : [e.fields]).forEach((id) => {
            errors[id] = 'This field is required.'
          })
          // Scroll to first error
          const firstId = Array.isArray(e.fields) ? e.fields[0] : e.fields
          const el = document.querySelector(`[data-field-id="${firstId}"]`)
          if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' })
        }
      },
      onSuccess: () => {
        submitting.value = false
      },
    }
  )
}
</script>
```

Note: the `visibility.js` import is the JS evaluator we built in part 1 Task 11. It lives at `resources/js/Pages/Contact/Questionnaire/visibility.js` — same directory as `Show.vue`.

- [ ] **Step 2: Build to confirm**

```
docker-compose exec app npm run build
```

Expected: clean build.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Contact/Questionnaire/Show.vue
git commit -m "$(cat <<'EOF'
Build client-facing questionnaire Show.vue

Renders all 12 field types with conditional-visibility evaluation through
the shared visibility.js evaluator. Autosaves on blur via axios PATCH; the
submit button hits the existing submit route. Lock and submitted states
adjust the banner and disable inputs.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 29: Surface questionnaires on the portal dashboard

**Files:**
- Modify: `app/Http/Controllers/Contact/ContactPortalController.php` (extend `dashboard()` payload)
- Modify: `resources/js/Pages/Contact/Dashboard.vue` (render inline questionnaire links)

- [ ] **Step 1: Extend ContactPortalController@dashboard**

In `app/Http/Controllers/Contact/ContactPortalController.php`, find the `dashboard()` method. The bookings collection is built around line 68 (`$bookings = $contact->bookings()...`). Inside the `->map(function ($booking) { ... })` callback that returns a booking summary array, add a `questionnaires` key. The mapping function returns something like `['id' => …, 'name' => …, …, 'payments' => …, 'contract' => …]` — append:

```php
                    'questionnaires' => $booking->questionnaireInstances()
                        ->whereIn('status', ['sent', 'in_progress', 'submitted'])
                        ->orderByDesc('sent_at')
                        ->get()
                        ->map(fn ($i) => [
                            'id' => $i->id,
                            'name' => $i->name,
                            'status' => $i->status,
                            'submitted_at' => $i->submitted_at?->format('M j, Y'),
                            'url' => route('portal.booking.questionnaire.show', [
                                'booking' => $booking->id,
                                'instance' => $i->id,
                            ]),
                        ])
                        ->values(),
```

Also eager-load to avoid N+1 — find the `->with([...])` array near the start of the bookings query (around line 70) and append `'questionnaireInstances'`.

- [ ] **Step 2: Render on the booking card**

Open `resources/js/Pages/Contact/Dashboard.vue`. Find where each booking is rendered (look for a v-for over bookings). For each booking card, where existing items show payment/contract info, insert a section for questionnaires:

```vue
<div v-if="booking.questionnaires?.length" class="mt-3 space-y-1">
  <h4 class="text-sm font-medium text-gray-700 dark:text-gray-200">Questionnaires</h4>
  <div
    v-for="q in booking.questionnaires"
    :key="q.id"
    class="flex items-center gap-2 text-sm"
  >
    <Link
      :href="q.url"
      class="text-indigo-600 dark:text-indigo-300 hover:underline"
    >📝 {{ q.name }}</Link>
    <span v-if="q.status === 'sent'" class="text-xs text-amber-600">— needs your answers</span>
    <span v-else-if="q.status === 'in_progress'" class="text-xs text-amber-600">— in progress</span>
    <span v-else-if="q.status === 'submitted'" class="text-xs text-emerald-600">— submitted, can still update</span>
  </div>
</div>
```

If the file uses `<script setup>` and doesn't already import `Link`, ensure `import { Link } from '@inertiajs/vue3'` is present.

- [ ] **Step 3: Build**

```
docker-compose exec app npm run build
```

Expected: clean.

- [ ] **Step 4: Manual smoke check** (optional — defer to Task 30 checkpoint)

If you want to test interactively:
- Log in as a contact
- Visit the portal dashboard
- A booking with a sent questionnaire should show a "📝 Wedding Day Questionnaire — needs your answers" link
- Clicking it should open Show.vue

If issues, fix and re-test. Otherwise, defer to the checkpoint.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Contact/ContactPortalController.php resources/js/Pages/Contact/Dashboard.vue
git commit -m "$(cat <<'EOF'
Surface questionnaires inline on portal dashboard booking cards

Each booking card lists its sent / in-progress / submitted questionnaires
with deep-links to Show.vue and a status badge. Locked instances are
omitted from the dashboard list.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 30: Phase 5 checkpoint

- [ ] **Step 1: Run full backend test suite**

```
docker-compose exec app php artisan test --parallel --processes=4
```

Expected: PASS (the pre-existing `test_can_upload_logo` parallel-flake may surface; passes in isolation).

- [ ] **Step 2: Run frontend tests**

```
docker-compose exec app npx vitest run
```

Expected: PASS.

- [ ] **Step 3: Manual smoke** (you should do this; subagents skip)

In a browser:
1. Log in as a band owner, send a questionnaire to a booking contact (using the existing send dialog from Phase 4)
2. Log out
3. Log in as the contact, visit the portal dashboard, click the questionnaire link
4. Fill out a few fields (blur each → "Saved" indicator appears)
5. Click Submit — should redirect back to dashboard with success flash, status updates to submitted
6. Re-open and edit answers — should still be editable
7. Have the band owner lock the instance, then re-open as contact — should show locked banner and disable inputs

If any step fails, debug and fix. Otherwise:

- [ ] **Step 4: Phase 5 done.**

---

# Phase 6 — Event integration

Goal: band-side mapping. Event editor gets a collapsible Questionnaires section showing each instance with answers, per-field "Apply" buttons for mapped fields, and a one-shot "Append all answers to notes" button.

## Task 31: QuestionnaireMappingService (TDD)

**Files:**
- Create: `app/Services/QuestionnaireMappingService.php`
- Create: `tests/Unit/Services/QuestionnaireMappingServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/QuestionnaireMappingServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Bookings;
use App\Models\Events;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use App\Services\QuestionnaireMappingRegistry;
use App\Services\QuestionnaireMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireMappingServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionnaireMappingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuestionnaireMappingService(new QuestionnaireMappingRegistry());
    }

    private function makeInstanceWithEvent(): array
    {
        $booking = Bookings::factory()->create();
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'additional_data' => ['wedding' => ['onsite' => 0, 'dances' => [
                ['title' => 'First Dance', 'data' => 'TBD'],
                ['title' => 'Father Daughter', 'data' => 'TBD'],
            ]]],
        ]);

        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);

        return [$instance, $event, $booking];
    }

    public function test_apply_response_writes_yes_no_answer_to_event_additional_data(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);
        $user = User::factory()->create();

        $this->service->applyResponse($response, $user);

        $event->refresh();
        $this->assertSame(true, data_get($event->additional_data, 'wedding.onsite'));
        $response->refresh();
        $this->assertNotNull($response->applied_to_event_at);
        $this->assertSame($user->id, $response->applied_by_user_id);
    }

    public function test_apply_response_writes_outside_path(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.outside',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $this->assertSame(true, data_get($event->additional_data, 'outside'));
    }

    public function test_apply_response_updates_first_dance_entry(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.first',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Evergreen — Yebba',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $dances = data_get($event->additional_data, 'wedding.dances');
        $this->assertSame('Evergreen — Yebba',
            collect($dances)->firstWhere('title', 'First Dance')['data']
        );
    }

    public function test_apply_response_inserts_dance_entry_when_missing(): void
    {
        $booking = Bookings::factory()->create();
        $event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $booking->id,
            'additional_data' => ['wedding' => ['dances' => []]],
        ]);

        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.money',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Gold Digger',
        ]);

        $this->service->applyResponse($response, User::factory()->create());

        $event->refresh();
        $dances = data_get($event->additional_data, 'wedding.dances');
        $this->assertSame('Gold Digger',
            collect($dances)->firstWhere('title', 'Money Dance')['data']
        );
    }

    public function test_apply_response_throws_when_field_has_no_mapping_target(): void
    {
        [$instance] = $this->makeInstanceWithEvent();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'mapping_target' => null,
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'X',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->applyResponse($response, User::factory()->create());
    }

    public function test_apply_response_throws_when_booking_has_no_event(): void
    {
        $booking = Bookings::factory()->create();
        $instance = QuestionnaireInstances::factory()->create(['booking_id' => $booking->id]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->service->applyResponse($response, User::factory()->create());
    }

    public function test_append_all_to_notes_appends_block_with_timestamp(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();
        $event->update(['notes' => '<p>existing notes</p>']);

        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'label' => "Bride's Name",
            'position' => 10,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->service->appendAllToNotes($instance, User::factory()->create());

        $event->refresh();
        $this->assertStringContainsString('existing notes', $event->notes);
        $this->assertStringContainsString("Bride's Name", $event->notes);
        $this->assertStringContainsString('Jane', $event->notes);
        $this->assertStringContainsString($instance->name, $event->notes);
    }

    public function test_append_all_to_notes_skips_instructions_and_renders_headers_as_h4(): void
    {
        [$instance, $event] = $this->makeInstanceWithEvent();

        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'header',
            'label' => 'Bride and Groom',
            'position' => 10,
        ]);
        QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'instructions',
            'label' => 'Some helper text',
            'position' => 20,
        ]);
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $instance->id,
            'type' => 'short_text',
            'label' => 'Name',
            'position' => 30,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->service->appendAllToNotes($instance, User::factory()->create());

        $event->refresh();
        $this->assertStringContainsString('<h4>Bride and Groom</h4>', $event->notes);
        $this->assertStringNotContainsString('Some helper text', $event->notes);
    }
}
```

- [ ] **Step 2: Run failing tests**

```
docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireMappingServiceTest.php
```

Expected: FAIL — service class missing.

- [ ] **Step 3: Create the service**

Create `app/Services/QuestionnaireMappingService.php`:

```php
<?php

namespace App\Services;

use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use RuntimeException;

class QuestionnaireMappingService
{
    public function __construct(private QuestionnaireMappingRegistry $registry)
    {
    }

    public function applyResponse(QuestionnaireResponses $response, User $appliedBy): Events
    {
        $field = $response->instanceField;
        if (!$field || !$field->mapping_target) {
            throw new RuntimeException('Field has no mapping target.');
        }

        $event = $this->resolveEvent($response->instance);
        $key = $field->mapping_target;

        if (!$this->registry->targetExists($key)) {
            throw new RuntimeException("Mapping target '{$key}' is no longer available.");
        }

        $kind = $this->registry->kind($key);

        if ($kind === QuestionnaireMappingRegistry::TYPE_BOOLEAN_PATH) {
            $this->writeBoolean($event, $this->registry->eventPath($key), $response->value);
        } elseif ($kind === QuestionnaireMappingRegistry::TYPE_DANCE_ENTRY) {
            $this->writeDance($event, $this->registry->danceTitle($key), (string) $response->value);
        }

        $event->save();

        $response->update([
            'applied_to_event_at' => now(),
            'applied_by_user_id' => $appliedBy->id,
        ]);

        return $event;
    }

    public function appendAllToNotes(QuestionnaireInstances $instance, User $appliedBy): Events
    {
        $event = $this->resolveEvent($instance);
        $event->notes = trim(($event->notes ?? '') . "\n\n" . $this->buildNotesBlock($instance));
        $event->save();

        return $event;
    }

    private function resolveEvent(QuestionnaireInstances $instance): Events
    {
        $event = $instance->booking->events()->orderBy('id')->first();
        if (!$event) {
            throw new RuntimeException('Booking has no event yet.');
        }
        return $event;
    }

    private function writeBoolean(Events $event, array $path, mixed $value): void
    {
        $bool = in_array(strtolower((string) $value), ['yes', 'true', '1', 'on'], true);
        $data = $event->additional_data ?? [];
        data_set($data, implode('.', $path), $bool);
        $event->additional_data = $data;
    }

    private function writeDance(Events $event, string $title, string $value): void
    {
        $data = $event->additional_data ?? [];
        $dances = data_get($data, 'wedding.dances', []);
        $dances = is_array($dances) ? $dances : [];

        $found = false;
        foreach ($dances as &$dance) {
            if (($dance['title'] ?? null) === $title) {
                $dance['data'] = $value;
                $found = true;
                break;
            }
        }
        unset($dance);

        if (!$found) {
            $dances[] = ['title' => $title, 'data' => $value];
        }

        data_set($data, 'wedding.dances', $dances);
        $event->additional_data = $data;
    }

    private function buildNotesBlock(QuestionnaireInstances $instance): string
    {
        $fields = $instance->fields()->orderBy('position')->get();
        $responses = $instance->responses()->get()->keyBy('instance_field_id');
        $date = now()->format('M j, Y');

        $html = "<hr>\n<p><strong>Customer submitted \"" . e($instance->name) . "\" on {$date}</strong></p>\n<ul>\n";

        foreach ($fields as $f) {
            if ($f->type === 'instructions') {
                continue;
            }
            if ($f->type === 'header') {
                $html .= "</ul>\n<h4>" . e($f->label) . "</h4>\n<ul>\n";
                continue;
            }
            $value = $responses->get($f->id)?->value;
            $value = $value !== null && $value !== '' ? $value : '(not answered)';
            // Decode JSON-encoded multi-values for display
            $decoded = json_decode((string) $value, true);
            if (is_array($decoded)) {
                $value = implode(', ', $decoded);
            }
            $html .= '<li><strong>' . e($f->label) . ':</strong> ' . e((string) $value) . "</li>\n";
        }

        $html .= '</ul>';
        return $html;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

```
docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireMappingServiceTest.php
```

Expected: PASS — 8 tests.

If `Events::factory()` doesn't exist (some legacy event creation patterns differ), look at how other event tests construct events (e.g., `BookingsControllerTest`) and adapt the helper. The factory should produce an event linked to a booking via the `eventable` polymorphic relationship.

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestionnaireMappingService.php tests/Unit/Services/QuestionnaireMappingServiceTest.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireMappingService

Two operations: applyResponse writes a single response to the curated event
target (boolean path or dance-entry-by-title); appendAllToNotes builds a
timestamped HTML block (header fields render as h4, instructions skip,
empty answers say "not answered") and appends it to event notes.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 32: EventQuestionnaireController + tests (TDD)

**Files:**
- Create: `app/Http/Controllers/EventQuestionnaireController.php`
- Modify: `routes/events.php` (add three routes)
- Create: `tests/Feature/Questionnaires/EventMappingTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Questionnaires/EventMappingTest.php`:

```php
<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Events;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventMappingTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private Events $event;
    private QuestionnaireInstances $instance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->event = Events::factory()->create([
            'eventable_type' => Bookings::class,
            'eventable_id' => $this->booking->id,
            'additional_data' => ['wedding' => ['onsite' => 0, 'dances' => [
                ['title' => 'First Dance', 'data' => 'TBD'],
            ]]],
        ]);

        $this->instance = QuestionnaireInstances::factory()->create(['booking_id' => $this->booking->id]);
    }

    public function test_apply_response_writes_yes_no_to_event(): void
    {
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.apply_response', [
                'event' => $this->event->id,
                'instance' => $this->instance->id,
                'response' => $response->id,
            ])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertSame(true, data_get($this->event->additional_data, 'wedding.onsite'));
    }

    public function test_apply_all_applies_every_unapplied_mapped_response(): void
    {
        $f1 = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $f2 = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'mapping_target' => 'wedding.dance.first',
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $f1->id,
            'value' => 'yes',
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $f2->id,
            'value' => 'Evergreen',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.apply_all', [$this->event->id, $this->instance->id])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertSame(true, data_get($this->event->additional_data, 'wedding.onsite'));
        $this->assertSame('Evergreen',
            collect(data_get($this->event->additional_data, 'wedding.dances'))
                ->firstWhere('title', 'First Dance')['data']
        );
    }

    public function test_append_all_to_notes_appends_block(): void
    {
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'short_text',
            'label' => 'Bride',
            'position' => 10,
        ]);
        QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'Jane',
        ]);

        $this->actingAs($this->owner)->post(
            route('events.questionnaires.append_to_notes', [$this->event->id, $this->instance->id])
        )->assertStatus(302);

        $this->event->refresh();
        $this->assertStringContainsString('Bride', $this->event->notes);
        $this->assertStringContainsString('Jane', $this->event->notes);
    }

    public function test_apply_requires_questionnaires_read_permission(): void
    {
        $stranger = User::factory()->create();
        $field = QuestionnaireInstanceFields::factory()->create([
            'instance_id' => $this->instance->id,
            'type' => 'yes_no',
            'mapping_target' => 'wedding.onsite',
        ]);
        $response = QuestionnaireResponses::create([
            'instance_id' => $this->instance->id,
            'instance_field_id' => $field->id,
            'value' => 'yes',
        ]);

        $this->actingAs($stranger)->post(
            route('events.questionnaires.apply_response', [
                'event' => $this->event->id,
                'instance' => $this->instance->id,
                'response' => $response->id,
            ])
        )->assertStatus(403);
    }
}
```

- [ ] **Step 2: Run failing tests**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/EventMappingTest.php
```

Expected: FAIL — routes/controller missing.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/EventQuestionnaireController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Models\Events;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use App\Services\QuestionnaireMappingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class EventQuestionnaireController extends Controller
{
    public function __construct(private QuestionnaireMappingService $mappingService)
    {
    }

    public function applyResponse(Events $event, QuestionnaireInstances $instance, QuestionnaireResponses $response): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);
        abort_if($response->instance_id !== $instance->id, 404);

        $this->mappingService->applyResponse($response, Auth::user());

        return back()->with('success', 'Answer applied to event.');
    }

    public function applyAll(Events $event, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);

        $instance->responses()
            ->whereHas('instanceField', fn ($q) => $q->whereNotNull('mapping_target'))
            ->whereNull('applied_to_event_at')
            ->each(fn ($r) => $this->mappingService->applyResponse($r, Auth::user()));

        return back()->with('success', 'All pending answers applied.');
    }

    public function appendToNotes(Events $event, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($event, $instance);

        $this->mappingService->appendAllToNotes($instance, Auth::user());

        return back()->with('success', 'Answers appended to event notes.');
    }

    private function authorizeAccess(Events $event, QuestionnaireInstances $instance): void
    {
        $user = Auth::user();
        $booking = $instance->booking;

        abort_if($instance->booking_id !== $event->eventable_id || $event->eventable_type !== \App\Models\Bookings::class, 404);
        abort_unless($user->canRead('questionnaires', $booking->band_id), 403);
        abort_unless($user->canWrite('events', $booking->band_id), 403);
    }
}
```

- [ ] **Step 4: Add routes**

In `routes/events.php`, find an existing `auth + verified` group with event routes. Inside that group, append:

```php
    Route::post('events/{event}/questionnaires/{instance}/responses/{response}/apply', [\App\Http\Controllers\EventQuestionnaireController::class, 'applyResponse'])
        ->name('events.questionnaires.apply_response');
    Route::post('events/{event}/questionnaires/{instance}/apply_all', [\App\Http\Controllers\EventQuestionnaireController::class, 'applyAll'])
        ->name('events.questionnaires.apply_all');
    Route::post('events/{event}/questionnaires/{instance}/append_to_notes', [\App\Http\Controllers\EventQuestionnaireController::class, 'appendToNotes'])
        ->name('events.questionnaires.append_to_notes');
```

Note: the existing event routes use a `{key}` (string) parameter for show/edit, but for `apply_response` we want `{event}` (id, default route-model binding). Both styles work; the `{event}` model binding here is fine because applies are POST-only and we don't need pretty URLs.

- [ ] **Step 5: Regenerate Ziggy**

```
docker-compose exec app php artisan ziggy:generate
```

- [ ] **Step 6: Run tests to verify they pass**

```
docker-compose exec app php artisan test tests/Feature/Questionnaires/EventMappingTest.php
```

Expected: PASS — 4 tests.

If the permission check test fails because the project's `events` permission seeder grants permissions differently, adjust to the real semantics — `User::canWrite('events', $bandId)` is the precondition; a user with no role on the band should fail.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/EventQuestionnaireController.php routes/events.php tests/Feature/Questionnaires/EventMappingTest.php
git add -A # ziggy regen
git commit -m "$(cat <<'EOF'
Add event-side questionnaire controller (apply, apply_all, append_to_notes)

Three POST endpoints gated by read:questionnaires + write:events on the
booking's band. apply / apply_all delegate to QuestionnaireMappingService;
append_to_notes hits the same service's notes-append builder.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 33: Build the event-side summary panel Vue component

**Files:**
- Create: `resources/js/Pages/Bookings/Components/EventEditor/QuestionnaireSection.vue`
- Modify: `resources/js/Pages/Bookings/Components/EventEditor.vue` (import and render the new section)
- Modify: `app/Http/Controllers/EventsController.php` (eager-load questionnaireInstances on the event payload)

- [ ] **Step 1: Inspect existing EventEditor structure**

Open `resources/js/Pages/Bookings/Components/EventEditor.vue` and look at how it imports its sub-components (BasicInfo, NotesSection, etc.) at lines 253–260. We'll add `QuestionnaireSection` to that pattern.

Also look at `EventsController.php` to see how it builds the payload to Vue. Find the method that returns the event editor view (probably `edit` or `show`) and understand the `event` shape it sends.

- [ ] **Step 2: Add server-side eager loading**

In `EventsController.php`, find the method that powers `EventEditor.vue`. The `event` returned to Inertia needs `questionnaireInstances` with `responses` and `fields` eager-loaded. Look for an existing `->load([...])` or `->with([...])` call. Add `'eventable.questionnaireInstances.fields'` and `'eventable.questionnaireInstances.responses'`.

If the controller uses ad-hoc serialization (mapping the event into an array before passing to Inertia), append a `questionnaire_instances` array to that map:

```php
'questionnaire_instances' => $event->eventable->questionnaireInstances()
    ->with(['fields' => fn ($q) => $q->orderBy('position'), 'responses', 'recipientContact'])
    ->get()
    ->map(fn ($i) => [
        'id' => $i->id,
        'name' => $i->name,
        'status' => $i->status,
        'sent_at' => $i->sent_at?->format('M j, Y'),
        'submitted_at' => $i->submitted_at?->format('M j, Y'),
        'recipient_name' => $i->recipientContact->name ?? 'Unknown',
        'fields' => $i->fields->map(fn ($f) => [
            'id' => $f->id,
            'type' => $f->type,
            'label' => $f->label,
            'position' => $f->position,
            'mapping_target' => $f->mapping_target,
            'mapping_label' => $f->mapping_target ? app(\App\Services\QuestionnaireMappingRegistry::class)->label($f->mapping_target) : null,
        ]),
        'responses' => $i->responses->mapWithKeys(fn ($r) => [
            $r->instance_field_id => [
                'value' => $r->value,
                'applied_to_event_at' => $r->applied_to_event_at?->toIso8601String(),
                'updated_at' => $r->updated_at->toIso8601String(),
                'response_id' => $r->id,
            ],
        ]),
    ]),
```

If the controller is too tangled to find the right spot, ask. Don't restructure the controller — add the minimum needed to surface the data.

- [ ] **Step 3: Create QuestionnaireSection.vue**

Create `resources/js/Pages/Bookings/Components/EventEditor/QuestionnaireSection.vue`:

```vue
<template>
  <div v-if="instances?.length" class="bg-white dark:bg-slate-800 rounded-xl shadow p-4 mb-4">
    <h3 class="text-lg font-semibold mb-3">Questionnaires</h3>

    <div
      v-for="instance in instances"
      :key="instance.id"
      class="border rounded-lg p-3 mb-3"
    >
      <div class="flex justify-between items-start mb-2">
        <div>
          <h4 class="font-medium">{{ instance.name }}</h4>
          <p class="text-xs text-gray-500">
            Sent to {{ instance.recipient_name }} on {{ instance.sent_at }}
            <span v-if="instance.submitted_at"> · Submitted {{ instance.submitted_at }}</span>
            <span class="ml-2 px-2 py-0.5 rounded text-xs uppercase"
              :class="{
                'bg-blue-100 text-blue-800': instance.status === 'sent',
                'bg-amber-100 text-amber-800': instance.status === 'in_progress',
                'bg-emerald-100 text-emerald-800': instance.status === 'submitted',
                'bg-gray-200 text-gray-800': instance.status === 'locked',
              }"
            >{{ instance.status }}</span>
          </p>
        </div>
        <div class="flex gap-2">
          <Button
            v-if="hasUnappliedMappings(instance)"
            label="Apply all pending"
            size="small"
            @click="applyAll(instance)"
          />
          <Button
            label="Append all to notes"
            size="small"
            outlined
            @click="appendToNotes(instance)"
          />
        </div>
      </div>

      <div v-for="field in instance.fields" :key="field.id" class="text-sm py-2 border-t dark:border-slate-700">
        <h5 v-if="field.type === 'header'" class="font-semibold text-base mt-2">{{ field.label }}</h5>
        <div v-else-if="field.type !== 'instructions'" class="flex justify-between items-start gap-3">
          <div class="flex-1 min-w-0">
            <p class="font-medium">{{ field.label }}</p>
            <p class="text-gray-700 dark:text-gray-200 break-words">{{ formatValue(instance.responses[field.id]?.value) || '(not answered)' }}</p>
            <p v-if="field.mapping_label" class="text-xs text-gray-500 italic mt-1">
              ↪ maps to: {{ field.mapping_label }}
            </p>
          </div>
          <div v-if="field.mapping_target && instance.responses[field.id]?.value">
            <Button
              v-if="needsApply(instance.responses[field.id])"
              label="Apply"
              size="small"
              @click="applyOne(instance, instance.responses[field.id].response_id)"
            />
            <span
              v-else
              class="text-xs text-emerald-600"
            >Applied</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { router } from '@inertiajs/vue3'
import Button from 'primevue/button'

const props = defineProps({
  eventId: { type: Number, required: true },
  instances: { type: Array, default: () => [] },
})

function formatValue(v) {
  if (v === null || v === undefined || v === '') return null
  const decoded = (() => {
    try { return JSON.parse(v) } catch { return null }
  })()
  if (Array.isArray(decoded)) return decoded.join(', ')
  return v
}

function needsApply(response) {
  if (!response.applied_to_event_at) return true
  return new Date(response.updated_at) > new Date(response.applied_to_event_at)
}

function hasUnappliedMappings(instance) {
  return instance.fields.some(
    (f) => f.mapping_target && instance.responses[f.id]?.value && needsApply(instance.responses[f.id])
  )
}

function applyOne(instance, responseId) {
  router.post(
    route('events.questionnaires.apply_response', {
      event: props.eventId,
      instance: instance.id,
      response: responseId,
    }),
    {},
    { preserveScroll: true }
  )
}

function applyAll(instance) {
  router.post(
    route('events.questionnaires.apply_all', {
      event: props.eventId,
      instance: instance.id,
    }),
    {},
    { preserveScroll: true }
  )
}

function appendToNotes(instance) {
  router.post(
    route('events.questionnaires.append_to_notes', {
      event: props.eventId,
      instance: instance.id,
    }),
    {},
    { preserveScroll: true }
  )
}
</script>
```

- [ ] **Step 4: Wire it into EventEditor.vue**

Open `resources/js/Pages/Bookings/Components/EventEditor.vue`. Add to the import block (around line 253):

```javascript
import QuestionnaireSection from "./EventEditor/QuestionnaireSection.vue";
```

Then in the template, somewhere in the editor's main column (a sensible place is below `NotesSection`), add:

```vue
<QuestionnaireSection
  :event-id="event.id"
  :instances="event.questionnaire_instances || []"
/>
```

If the existing layout uses collapsible sections (e.g., `<SectionCard>`), wrap the new component in one to match the convention.

- [ ] **Step 5: Build to confirm**

```
docker-compose exec app npm run build
```

Expected: clean build.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Bookings/Components/EventEditor/QuestionnaireSection.vue resources/js/Pages/Bookings/Components/EventEditor.vue app/Http/Controllers/EventsController.php
git commit -m "$(cat <<'EOF'
Add Questionnaires summary panel to the event editor

Renders each instance with header, status badge, and per-field rows. Mapped
fields show "Apply" button (or "Applied" indicator) and re-show as needs-apply
when the response updates after a previous apply. Top-level "Apply all
pending" and "Append all to notes" buttons.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 34: Phase 6 checkpoint and Final QA

- [ ] **Step 1: Run full backend test suite**

```
docker-compose exec app php artisan test --parallel --processes=4
```

Expected: PASS (with the same pre-existing logo-upload flake disclaimer).

- [ ] **Step 2: Run frontend tests**

```
docker-compose exec app npx vitest run
```

Expected: PASS.

- [ ] **Step 3: Build clean**

```
docker-compose exec app npm run build
```

Expected: clean.

- [ ] **Step 4: Manual end-to-end smoke**

In a browser, run the full flow:
1. As band owner: create a wedding-day questionnaire template (Index → New). Add fields including yes_no for "Onsite ceremony?" with mapping_target=wedding.onsite, short_text for "First dance song" with mapping_target=wedding.dance.first.
2. Save template. Open the band's booking. Send the questionnaire to the primary contact.
3. Log in as the contact. Open the questionnaire from the dashboard inline link. Fill out all fields. Hit Submit.
4. Log back in as band owner. Open the booking's event. Confirm the Questionnaires section appears with the answers. Click Apply on the onsite field; check the wedding section flip. Click Apply on first-dance; check the dances list update.
5. Click Append all to notes. Check the notes section gets a new HTML block with all answers.
6. Lock the instance from the booking page; verify the contact-portal view shows the locked banner.

If anything breaks, debug and fix. Otherwise:

- [ ] **Step 5: Phase 6 done.**

---

# Wrap-up

After Phase 6 lands, the questionnaires feature is fully functional end-to-end. To complete the development branch, invoke `superpowers:finishing-a-development-branch` to decide between merge, PR, or further cleanup.

Open work the spec mentioned but we deferred:
- Activity-log UI (Spatie data is captured by the existing logging traits but no UI surfaces it)
- Multi-event mapping (currently only the booking's first event is targeted)
- Song-picker field type (left as a future addition; the type-string-not-enum pattern means it can be added without migrations)
- Conditional-logic chains (single condition only)
- Magic-link email (uses existing portal auth instead)

These are all explicitly listed in the spec's Non-goals section. Adding them is a separate cycle.
