# Questionnaires — design

**Status:** approved during brainstorming, ready for implementation planning
**Date:** 2026-04-28

## Goal

Provide a way for bands to (1) build reusable questionnaire templates and (2) send those templates to clients, so clients can answer them through the existing contact portal. Submitted answers reflect on events through a curated mapping registry plus a notes-append action.

Replaces a stub feature from 2021 (`questionnairres` table, three field types, never used in production). The stub is removed entirely; this is a clean rewrite.

## Non-goals (v1)

- File upload as a field type
- Cross-band template copying
- Multi-condition (AND/OR) visibility rules — single condition only
- Multi-target mapping per field — 1:1 only
- Mapping to multiple events on a recurring booking — first event only
- Magic links / tokenized email access — uses existing portal auth
- Autosave on the builder side — bulk save only
- Undo on the per-field "Apply" action — Spatie Activitylog provides the audit trail

## Architecture overview

Four bounded modules with intentionally minimal coupling:

```
TEMPLATE MANAGEMENT (band side)
  Templates owned by bands. Soft-delete + archive flag.
  Field definitions: type, label, help, required, settings, visibility rule, mapping target.
  Bulk-save endpoint, preview view.
  Top-level nav "Questionnaires" with deep-link from band settings.
              │ snapshotted from at send time
              ▼
INSTANCE LIFECYCLE (booking side)
  Sending = snapshot template into instance + instance_fields, attach to booking.
  State machine: sent → in_progress → submitted ⇄ locked.
  Multiple instances per booking allowed, no uniqueness constraint.
              │ exposed to portal
              ▼
CLIENT PORTAL (contact side)
  Existing contact auth guard. Any contact on the booking can edit.
  Renders fields with conditional logic. Autosave per field on blur.
  Submit transitions state and notifies band owner.
              │ surfaces results
              ▼
EVENT INTEGRATION (band side)
  Read-only summary panel on event editor.
  Per-field "Apply" for mapped targets. "Append all to notes" one-shot.
  Lock/unlock action.
```

Dependencies: instance lifecycle reads templates but never writes back; portal reads instances + fields, writes only responses + status; event integration reads instance + responses, writes to `events.additional_data` and `events.notes` only on band action.

## Data model

Five new tables. Drop existing `questionnairres` and `questionnaire_components` (no production data).

### `questionnaires`

Band-owned reusable templates.

```
id                  bigint PK
band_id             bigint FK → bands.id (cascade)
name                varchar(120) NOT NULL
slug                varchar(140) NOT NULL
description         text NULL
archived_at         timestamp NULL
created_at, updated_at
deleted_at          timestamp NULL  (soft delete)

UNIQUE (band_id, slug)
INDEX (band_id, archived_at)
```

Slug generated from name via `Str::slug`, scoped to band — different bands can both use `wedding-day-questionnaire`. Conflicting slugs within a band get suffixed `-2`, `-3`, etc.

### `questionnaire_fields`

Fields belonging to a template.

```
id                  bigint PK
questionnaire_id    bigint FK → questionnaires.id (cascade)
type                varchar(40) NOT NULL
label               varchar(255) NOT NULL
help_text           text NULL
required            boolean DEFAULT false
position            int NOT NULL  (gapped: 10, 20, 30…)
settings            json NULL
visibility_rule     json NULL
mapping_target      varchar(60) NULL
created_at, updated_at

INDEX (questionnaire_id, position)
```

`type` is a string, not enum, so future types like `song_picker` can be added without migration. Settings shape varies by type and is validated at save time by `FieldSettingsValidator`. `mapping_target` is a registry key string (not an FK).

`visibility_rule` shape: `{"depends_on": <field_id>, "operator": "equals|not_equals|contains|empty|not_empty", "value": "<string>"}`. Forward references rejected at save time (`depends_on.position < this.position`).

### `questionnaire_instances`

Snapshot of a template attached to a booking.

```
id                    bigint PK
questionnaire_id      bigint NULL FK → questionnaires.id  ON DELETE SET NULL
booking_id            bigint FK → bookings.id (cascade)
recipient_contact_id  bigint FK → contacts.id
sent_by_user_id       bigint FK → users.id
name                  varchar(120) NOT NULL  (snapshotted)
description           text NULL              (snapshotted)
status                varchar(20) NOT NULL DEFAULT 'sent'
sent_at               timestamp NOT NULL
first_opened_at       timestamp NULL
submitted_at          timestamp NULL
locked_at             timestamp NULL
locked_by_user_id     bigint NULL FK → users.id
created_at, updated_at
deleted_at            timestamp NULL  (soft delete)

INDEX (booking_id, status)
INDEX (recipient_contact_id)
```

`questionnaire_id` is nullable so deleting a template doesn't cascade-destroy historical instances.

### `questionnaire_instance_fields`

Snapshotted fields on an instance.

```
id                  bigint PK
instance_id         bigint FK → questionnaire_instances.id (cascade)
source_field_id     bigint NULL  (reference only, no FK)
type, label, help_text, required, position,
settings, visibility_rule, mapping_target  — all snapshotted
created_at, updated_at

INDEX (instance_id, position)
```

`visibility_rule.depends_on` references **other instance_field ids on the same instance** — re-pointed during snapshot via the old-id-to-new-id map.

### `questionnaire_responses`

One row per (instance × field).

```
id                    bigint PK
instance_id           bigint FK → questionnaire_instances.id (cascade)
instance_field_id     bigint FK → questionnaire_instance_fields.id (cascade)
value                 text NULL  (string or JSON-encoded for multi-value)
applied_to_event_at   timestamp NULL
applied_by_user_id    bigint NULL FK → users.id
created_at, updated_at

UNIQUE (instance_id, instance_field_id)
INDEX (instance_id)
```

Autosave does an upsert; submit is a status change, not a response write. Multi-value fields (`multi_select`, `checkbox_group`) JSON-encode an array.

### `user_permissions` seed

Add `questionnaires` to the existing permission registry (alongside `events`, `proposals`, `bookings`, `charts`, `colors`, `invoices`).

### Existing tables touched

- Drop: `questionnairres`, `questionnaire_components` (in a new migration; no prod data lost).
- No schema changes to `bookings`, `events`, `contacts`, `booking_contacts`. Eloquent relationships added in PHP only.

## Field-type registry

Defined in `app/Services/QuestionnaireFieldTypeRegistry.php`:

```
short_text     — Short text          settings: placeholder
long_text      — Long text           settings: placeholder
date           — Date                settings: none
time           — Time                settings: none
email          — Email               settings: placeholder
phone          — Phone               settings: placeholder
dropdown       — Dropdown            settings: options[{value, label}]
multi_select   — Multi-select        settings: options[{value, label}]
checkbox_group — Checkboxes          settings: options[{value, label}]
yes_no         — Yes / No            settings: none
header         — Section header      settings: none, is_input: false
instructions   — Instruction text    settings: none, is_input: false
```

`is_input: false` types skip required, visibility, and mapping settings. The registry is the single source of truth — frontend reads via Vuex on builder mount.

## Mapping registry

Defined in `app/Services/QuestionnaireMappingRegistry.php`. Curated targets only — adding a new target requires code change.

```
wedding.onsite              → additional_data.wedding.onsite       (yes_no → bool)
wedding.outside             → additional_data.outside              (yes_no → bool)
wedding.dance.first         → additional_data.wedding.dances[title="First Dance"].data
wedding.dance.father_daughter → …dances[title="Father Daughter"].data
wedding.dance.mother_son    → …dances[title="Mother Son"].data
wedding.dance.money         → …dances[title="Money Dance"].data
wedding.dance.bouquet_garter → …dances[title="Bouquet/Garter"].data
```

Each entry exports: `label`, `compatible_field_types[]`, `event_path` (or special-case for dances), `transform` callable, `describe_target` callable for "currently on event" rendering.

## Builder UX (band side)

Routes are band-scoped to match other band-scoped resources:

```
GET    /bands/{band}/questionnaires
POST   /bands/{band}/questionnaires
GET    /bands/{band}/questionnaires/{questionnaire:slug}/edit
PUT    /bands/{band}/questionnaires/{questionnaire:slug}
GET    /bands/{band}/questionnaires/{questionnaire:slug}/preview
POST   /bands/{band}/questionnaires/{questionnaire:slug}/archive
POST   /bands/{band}/questionnaires/{questionnaire:slug}/restore
DELETE /bands/{band}/questionnaires/{questionnaire:slug}
```

Layout: three-pane Google-Forms style. Click a field to expand its settings inline; floating toolbar to the right of the selected field for add/drag/duplicate/delete. Drag-and-drop via `vuedraggable@4.1.0` (already installed). Position values are gapped (10, 20, 30) and recomputed with consistent gaps on save.

Save model: bulk save. Builder loads full template; all edits live in Vue state; one PUT to `update`. Server diffs existing fields vs payload by id, upserts present, soft-deletes missing, then re-points `visibility_rule.depends_on` from temporary client ids to new permanent ids in a second pass — all in one DB transaction.

Builder controls visible per field:
- Label
- Help text
- Required toggle
- "Show if" — dropdown of fields earlier in order, operator, value
- "Maps to event" — dropdown filtered by registry's `compatible_field_types` for this field type

Index page: DataTable with name, last edited, # times sent, archived flag. "+ New" button opens a small dialog (name + description). Row actions: edit, preview, archive/restore, delete. Delete only available for templates that have never been sent (avoids orphaning instances; soft-delete still works otherwise).

Top-level nav adds a "Questionnaires" item. Band settings page adds a deep-link button.

Permissions: `read` for index/edit/preview; `write` for store/update/archive/restore/destroy. Enforced by `QuestionnairePolicy`.

## Sending and instance lifecycle

Routes added under `bands/{band}/booking/{booking}/questionnaires`:

```
POST   /                                      send
POST   /{instance}/lock                       lock
POST   /{instance}/unlock                     unlock
DELETE /{instance}                            destroy (soft delete)
```

`BookingQuestionnaireController@send`:

1. Validate via `SendQuestionnaireRequest` (`questionnaire_id`, `recipient_contact_id`).
2. Authorize: user has `write` permission on `questionnaires` for the band.
3. Custom rules: questionnaire belongs to booking's band; contact is on booking; template not archived.
4. Check `recipient_contact->can_login`. If false, surface inline error with a button to enable portal access via existing `enableContactPortalAccess` route — explicit step so the band knows credentials are about to be emailed.
5. `QuestionnaireSnapshotService::snapshot($template, $booking, $contact, $user)` — creates instance, copies all fields, builds old-to-new id map, second pass rewrites visibility-rule depends_on references. All in one DB transaction.
6. Dispatch queued `QuestionnaireSent` notification to recipient.
7. Redirect with flash message.

State machine:

```
sent ──► in_progress ──► submitted ──┬─► submitted (re-edit, updates submitted_at)
                                      └─► locked
sent ──► locked
in_progress ──► locked
```

| State | Client can edit? | Triggered by |
|-------|------------------|--------------|
| sent | yes | initial send |
| in_progress | yes | first response upsert from portal |
| submitted | yes | client clicks Submit |
| locked | no | band clicks Lock |

`first_opened_at` set on first portal GET. `submitted_at` updated on each (re-)submit. Unlock returns to `submitted` (if any responses exist) or `sent`.

Booking page UI: new collapsible "Questionnaires" section listing each instance with status, "View answers" link (jumps to event editor's summary panel), "Lock", "Resend email", "Delete". "+ Send" opens a dialog with template + recipient pickers (primary contact pre-selected).

"Resend email" re-fires `QuestionnaireSent` for an existing instance — does not create a new instance.

Notifications use the band's brand: `MailMessage->from($band->email, $band->name)`. Pattern follows existing notifications (`MediaUploadedNotification`-style).

`recipient_contact_id` is the email recipient only. Any contact on the booking (via `booking->contacts`) can view and edit the same instance from their portal.

## Client portal

Routes added inside `auth:contact` middleware group:

```
GET   /booking/{booking}/questionnaire/{instance}                   show
PATCH /booking/{booking}/questionnaire/{instance}/responses         saveResponse
POST  /booking/{booking}/questionnaire/{instance}/submit            submit
```

Authorization (per-route):
1. Authenticated contact is on `booking->contacts`.
2. Instance belongs to booking.
3. For mutations: `instance.status !== 'locked'`.

Show page renders all instance fields in `position` order. Existing responses pre-populated. Conditional logic evaluated client-side via `resources/js/Pages/Contact/Questionnaire/visibility.js`. Hidden fields are visually omitted from the DOM.

Status banner:
- `sent`/`in_progress`: "Save your answers as you go. Click Submit when finished."
- `submitted`: "Submitted on {date}. You can still update your answers."
- `locked`: read-only, "This questionnaire has been locked by the band. Contact them if you need to make changes."

Submit button is "Submit" before first submit, "Update" afterward.

On first GET, server stamps `first_opened_at` if null.

### Autosave (per-field on blur)

Each input fires PATCH `saveResponse` on blur if the value changed since last save:
- Server validates field belongs to instance, type-coerces value (registry-driven), upserts response row, transitions `instance.status` from `sent` → `in_progress`.
- Returns `{saved_at}`. Frontend renders a small fading "Saved" indicator.
- Failure: keep local value, subtle error toast, retry on next blur. No optimistic-rollback complexity.

Multi-value fields JSON-encode an array.

### Submit

1. Client-side validate visible required fields are filled.
2. POST `submit`. Server runs visibility evaluator on full response set, deletes responses for hidden fields (idempotent wipe), then validates each visible required field has a non-empty response.
3. Pass: `instance.status = 'submitted'`, `submitted_at = now()`. Re-submits update `submitted_at`.
4. Notify band owner: `$instance->booking->band->owner()->first()->user->notify(new QuestionnaireSubmitted($instance, isUpdate: $wasAlreadySubmitted))`. Subject differs based on isUpdate.
5. Redirect with flash.

### Portal dashboard surface

`ContactPortalController@dashboard` extends each booking's serialization to include `questionnaires` (filtered to `sent`/`in_progress`/`submitted`, excluding `locked`):

```php
'questionnaires' => $booking->questionnaireInstances()
    ->whereIn('status', ['sent', 'in_progress', 'submitted'])
    ->get()
    ->map(...)
```

`Contact/Dashboard.vue` renders inline links on each booking card (e.g., "Wedding Day Questionnaire — needs your answers"). No new top-level nav item.

`QuestionnaireSent` email button deep-links to `route('portal.booking.questionnaire.show', [...])`. Existing portal middleware redirects unauthenticated users to login then back.

## Event integration

Routes added under events:

```
POST /events/{event}/questionnaires/{instance}/responses/{response}/apply
POST /events/{event}/questionnaires/{instance}/apply_all
POST /events/{event}/questionnaires/{instance}/append_to_notes
```

Permission: `write` on `events` AND `read` on `questionnaires`.

### Summary panel on event editor

New collapsible section in `EventEditor.vue` (alongside Notes, Performance, etc.):

For each instance attached to the booking, panel shows:
- Header: name, sent-to/sent-on, status, edited-on. Action buttons: Lock, Append all to notes, Resend.
- All fields with their answers, grouped by header fields (visual grouping only — sections aren't a first-class concept; headers are field types).
- Mapped fields display: question, answer, "↪ maps to {target.label}", "Currently on event: {target.describe_target($event)}", and either `[Apply]` (if unapplied OR response updated since last apply) or `[Already applied]`.
- Bottom: `[Apply all pending mappings]` button.

### Per-field Apply

`QuestionnaireMappingService::applyResponse($response, $user)`:

1. Look up registry target by `field.mapping_target`.
2. Run `transform` on `response.value`.
3. Write to event:
   - Simple paths: `data_set($event->additional_data, $path, $value)`, then `$event->save()`.
   - Dance entries: find array entry by `title`, update its `data` field; if missing, insert. Save event.
4. Stamp `response.applied_to_event_at = now()`, `response.applied_by_user_id = $user->id`.

If client edits an applied response later, the panel shows it as re-appliable (compare `response.updated_at > response.applied_to_event_at`).

### Append all to notes

`QuestionnaireMappingService::appendAllToNotes($instance, $user)` builds an HTML block:

```html
<hr>
<p><strong>Customer submitted "{instance.name}" on {date}</strong></p>
<ul>
  <li><strong>{label}:</strong> {answer}</li>
  ...
</ul>
```

Header fields render as `<h4>`. Instructions skipped. Empty answers render as "(not answered)". Block appended to `event.notes` (preserves existing content). Each invocation appends a *new* block with current timestamp — preserves history.

### Edge cases

- **Booking has no event yet:** summary panel still shows answers; mapping actions disabled with tooltip "Create the event first to apply answers."
- **Booking has multiple events** (recurring): use `$booking->events()->first()`. v1 only — multi-event mapping deferred.
- **Mapping target removed from registry**: snapshot stored the key. If key disappears, Apply button disables with "Mapping target no longer available." Answer still displays.

## Conditional logic

### Rule shape

Stored on both `questionnaire_fields.visibility_rule` and `questionnaire_instance_fields.visibility_rule`:

```json
{
  "depends_on": <field_id>,
  "operator": "equals|not_equals|contains|empty|not_empty",
  "value": "<string>"
}
```

`null` rule = always visible. Single condition only — no AND/OR chains. Forward references rejected at save (the `depends_on` field's `position` must be less than this field's `position`).

### Operator semantics

| Operator | Multi-value | Single-value |
|----------|-------------|---------------|
| equals | array contains value | string equality |
| not_equals | array does NOT contain value | string inequality |
| contains | array contains substring in any | string substring |
| empty | array empty/null | null/empty string |
| not_empty | array non-empty | non-null/non-empty |

`value` ignored for `empty`/`not_empty`.

### Evaluator (mirrored in PHP and JS)

```js
// resources/js/Pages/Contact/Questionnaire/visibility.js
export function isFieldVisible(field, allFields, responses) {
  const rule = field.visibility_rule
  if (!rule) return true
  const target = allFields.find(f => f.id === rule.depends_on)
  if (!target || !isFieldVisible(target, allFields, responses)) return false
  return evaluate(rule, responses[rule.depends_on])
}
```

PHP equivalent in `app/Services/QuestionnaireVisibilityEvaluator.php` — identical semantics. If a controller field is itself hidden, all its dependents are also hidden (transitive).

### Hidden-field handling on submit

Server-side `submit` handler:
1. Run evaluator over full response set.
2. Delete `QuestionnaireResponses` rows for fields that are not visible (idempotent wipe).
3. For each visible field, validate `required` constraint.

Hidden required fields don't block submit.

## Validation

### Bulk save (`UpdateQuestionnaireRequest`)

```
name                              required string max:120
description                       nullable string
fields                            required array
fields.*.id                       nullable integer
fields.*.client_id                required string  (for visibility resolution)
fields.*.type                     required, in:registry.knownTypes()
fields.*.label                    required string max:255
fields.*.help_text                nullable string
fields.*.required                 boolean
fields.*.position                 required integer
fields.*.settings                 nullable array
fields.*.visibility_rule          nullable array
fields.*.visibility_rule.depends_on required_with:fields.*.visibility_rule, string  (client_id reference)
fields.*.visibility_rule.operator   required_with, in:[equals,not_equals,contains,empty,not_empty]
fields.*.visibility_rule.value      nullable string
fields.*.mapping_target           nullable, in:registry.targets().keys
```

Plus `FieldSettingsValidator` per-type:
- `dropdown`/`multi_select`/`checkbox_group`: `settings.options` required, array of `{value, label}`, min 1.
- `header`/`instructions`: settings ignored.

Plus mapping-target compatibility custom rule: if `mapping_target` set, field's `type` must be in `registry->compatibleFieldTypes($key)`.

### Send (`SendQuestionnaireRequest`)

```
questionnaire_id       required, exists:questionnaires,id, custom: belongs to band, not archived
recipient_contact_id   required, exists:contacts,id, custom: on booking
```

### Portal save-response (`SaveResponseRequest`)

```
instance_field_id   required integer
value               nullable
```

Per-type coercion in controller:
- `date` parses Y-m-d
- `time` parses H:i
- `email` format check (only if non-empty)
- `phone` minimal format
- `dropdown`/`yes_no` value in allowed set
- `multi_select`/`checkbox_group` array of allowed values, JSON-encoded on save

### Portal submit (`SubmitQuestionnaireRequest`)

Server runs evaluator, then validates each visible required field has a non-empty response. 422 with field-id list on failure.

## Permissions

New `questionnaires` permission added to `user_permissions` registry alongside `events`, `proposals`, `bookings`, `charts`, `colors`, `invoices`.

| Action | Required permission |
|--------|---------------------|
| Index, edit, preview templates | `read` on `questionnaires` |
| Create, update, archive, restore, delete templates | `write` on `questionnaires` |
| Send, lock, unlock, delete instances | `write` on `questionnaires` |
| Apply response, apply-all, append-to-notes | `write` on `events` AND `read` on `questionnaires` |

Enforced by `QuestionnairePolicy` and per-route policy checks.

## Notifications

All extend `Notification` with `ShouldQueue`, `via` returns `['mail', 'database']`, follow `MediaUploadedNotification` pattern. `MailMessage->from($band->email, $band->name)` for branded sending.

| Notification | Recipient | Subject |
|--------------|-----------|---------|
| `QuestionnaireSent` | recipient contact | "{band.name}: Please complete the {questionnaire.name}" |
| `QuestionnaireSubmitted` (isUpdate=false) | band owner (via `band->owner()->first()->user`) | "{client.name} submitted the {questionnaire.name}" |
| `QuestionnaireSubmitted` (isUpdate=true) | band owner | "{client.name} updated their {questionnaire.name}" |

## Activity logging (Spatie)

Log name `'questionnaires'` on:
- `Questionnaires` (template create/update/archive/destroy)
- `QuestionnaireInstances` (status transitions, lock/unlock)
- `QuestionnaireResponses` (`logOnlyDirty()` so only actual value changes log)

Surfaced through existing `ActivityHistoryModal.vue` if needed.

## Files

### New

```
app/Models/
  Questionnaires.php
  QuestionnaireFields.php
  QuestionnaireInstances.php
  QuestionnaireInstanceFields.php
  QuestionnaireResponses.php

app/Services/
  QuestionnaireSnapshotService.php
  QuestionnaireMappingRegistry.php
  QuestionnaireMappingService.php
  QuestionnaireFieldTypeRegistry.php
  QuestionnaireVisibilityEvaluator.php
  FieldSettingsValidator.php

app/Http/Controllers/
  QuestionnairesController.php             (rewrite, plural)
  BookingQuestionnaireController.php
  EventQuestionnaireController.php
  Contact/PortalQuestionnaireController.php

app/Http/Requests/
  StoreQuestionnaireRequest.php
  UpdateQuestionnaireRequest.php
  SendQuestionnaireRequest.php
  SaveResponseRequest.php
  SubmitQuestionnaireRequest.php

app/Notifications/
  QuestionnaireSent.php
  QuestionnaireSubmitted.php

app/Policies/
  QuestionnairePolicy.php

resources/js/Pages/Questionnaires/
  Index.vue
  Edit.vue
  Preview.vue

resources/js/Pages/Questionnaires/Components/
  FieldEditor.vue
  FieldSettings.vue
  FieldTypePicker.vue
  VisibilityRuleEditor.vue
  MappingTargetPicker.vue

resources/js/Pages/Bookings/Components/QuestionnaireSection.vue
resources/js/Pages/Bookings/Components/SendQuestionnaireDialog.vue
resources/js/Pages/Bookings/Components/EventQuestionnairePanel.vue
resources/js/Pages/Contact/Questionnaire/
  Show.vue
  visibility.js

database/migrations/
  YYYY_MM_DD_create_questionnaires_tables.php
  YYYY_MM_DD_drop_old_questionnaire_tables.php
  YYYY_MM_DD_add_questionnaires_to_user_permissions.php
```

### Modified

```
app/Models/User.php                        — questionnaires() relationship rewrite
app/Models/Bands.php                       — questionnaires() relationship rewrite
app/Models/Bookings.php                    — add questionnaireInstances() relationship
routes/questionnaire.php                   — full rewrite (band-scoped)
routes/booking.php                         — add send/lock/unlock/destroy routes
routes/contact.php                         — add portal questionnaire routes
routes/events.php                          — add apply/apply_all/append_to_notes routes
resources/js/Layouts/Authenticated.vue     — add Questionnaires nav item
resources/js/Pages/Bookings/Components/EventEditor.vue — add summary panel section
resources/js/Pages/Contact/Dashboard.vue   — surface questionnaires inline on booking cards
resources/js/Store/questionnaire.js        — replace stub with field-type registry, builder state
```

### Deleted

```
app/Models/Questionnairres.php
app/Models/QuestionnaireComponents.php
app/Services/QuestionnaireServices.php
app/Observers/QuestionnaireComponentObserver.php
app/Http/Controllers/QuestionnaireController.php  (replaced by plural rewrite)
resources/js/Pages/Questionnaire/Index.vue
resources/js/Pages/Questionnaire/Edit.vue
```

## Testing strategy

PHPUnit with `test_` prefix (per project memory: never `it_`, never `/** @test */`, never `#[Test]`).

### Unit

`tests/Unit/Services/QuestionnaireSnapshotServiceTest.php`
- test_snapshot_copies_template_fields_to_instance
- test_snapshot_rewrites_visibility_rule_depends_on_to_new_field_ids
- test_snapshot_handles_template_with_no_fields
- test_snapshot_runs_in_transaction_and_rolls_back_on_failure

`tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php`
- test_equals_operator_for_single_value_field
- test_equals_operator_for_multi_value_field
- (one test per operator × value-cardinality)
- test_field_is_hidden_when_controller_is_hidden_transitively
- test_field_is_visible_when_no_rule_set

`tests/Unit/Services/QuestionnaireMappingServiceTest.php`
- test_apply_response_writes_yes_no_to_event_additional_data
- test_apply_response_updates_specific_dance_entry_in_dances_array
- test_apply_response_stamps_applied_to_event_at
- test_apply_response_throws_when_field_has_no_mapping_target
- test_append_all_to_notes_appends_block_with_timestamp_and_answers
- test_append_all_to_notes_preserves_existing_notes

`tests/Unit/Services/QuestionnaireMappingRegistryTest.php`
- test_registry_returns_all_known_targets
- test_compatible_field_types_filters_by_field_type
- test_target_exists_returns_false_for_unknown_key

### Feature

`tests/Feature/Questionnaires/TemplateBuilderTest.php`
- test_band_owner_can_create_questionnaire_template
- test_band_owner_can_bulk_save_template_with_fields
- test_bulk_save_resolves_visibility_rule_client_ids_to_field_ids
- test_bulk_save_rejects_forward_visibility_references
- test_bulk_save_rejects_incompatible_mapping_target
- test_bulk_save_rejects_dropdown_with_no_options
- test_user_without_questionnaires_write_permission_cannot_save
- test_archive_marks_template_archived_at
- test_destroy_blocked_when_template_has_been_sent
- test_slug_uniqueness_scoped_to_band

`tests/Feature/Questionnaires/SendQuestionnaireTest.php`
- test_band_user_can_send_questionnaire_to_booking_contact
- test_send_creates_instance_with_snapshotted_fields
- test_send_dispatches_email_notification_to_recipient
- test_send_fails_when_contact_lacks_portal_access
- test_send_fails_when_contact_not_on_booking
- test_send_fails_when_questionnaire_belongs_to_different_band
- test_send_fails_when_template_archived
- test_user_can_resend_email_for_existing_instance_without_creating_duplicate
- test_user_can_lock_and_unlock_instance

`tests/Feature/Questionnaires/PortalQuestionnaireTest.php`
- test_contact_can_view_questionnaire_via_portal
- test_non_booking_contact_cannot_view_questionnaire
- test_first_open_stamps_first_opened_at
- test_response_save_upserts_response_row
- test_response_save_transitions_status_from_sent_to_in_progress
- test_submit_transitions_status_to_submitted
- test_submit_re_submit_updates_submitted_at_and_notifies_band_with_update_subject
- test_submit_validation_fails_when_required_field_missing
- test_submit_validation_succeeds_when_required_field_is_hidden_by_visibility_rule
- test_submit_wipes_responses_for_hidden_fields
- test_locked_instance_rejects_response_save_and_submit
- test_other_booking_contact_can_also_edit_responses

`tests/Feature/Questionnaires/EventMappingTest.php`
- test_apply_response_writes_yes_no_answer_to_event_outside
- test_apply_response_writes_first_dance_to_event_dances_array
- test_apply_all_applies_every_unapplied_mapped_response
- test_apply_re_shows_when_response_updated_after_apply
- test_append_all_to_notes_appends_formatted_block
- test_apply_disabled_when_booking_has_no_event
- test_apply_requires_events_write_permission

### Vitest

`resources/js/Pages/Contact/Questionnaire/__tests__/visibility.spec.js`
- One test per operator
- Transitive hide test
- No-rule case

PHP and JS evaluators implement the same semantics. A shared fixture file is over-engineering for v1 — both implementations are small enough to hand-mirror, and a feature-test spot-check covers parity in a couple of cases.

## Approach decisions (from brainstorming)

| Decision | Choice | Rationale |
|----------|--------|-----------|
| Existing scaffolding | Wipe and rewrite | Stub never used in production; clean slate |
| Field types in v1 | JotForm coverage + multi-select/checkbox/yes_no | Covers wedding form + general questionnaires |
| Sections | Header field type, no sections table | Matches Google Forms; keeps reordering as one list |
| Versioning | Snapshot on send | Self-contained instances; edits to template don't disturb in-flight |
| Cardinality | Many instances per booking, no uniqueness | Flexible without schema rigor |
| Re-edit after submit | Editable until band locks | Wedding planning evolves over weeks/months |
| Recipients | Band picks, but any booking contact can edit | Multi-author without merge complexity |
| Required + validation | Required + per-type validation + help text + conditional logic | Helpful for clients; powerful enough for non-trivial forms |
| Template UI location | Top-level nav + send-from-booking + settings deep-link | Matches existing patterns |
| Builder layout | Three-pane Google Forms style | Familiar; minimal cognitive load |
| Storage shape | Fully normalized (separate fields and responses tables) | Audit trail per row; queryable; future-proof |
| Save model | Bulk save | Simple; templates aren't edited often |
| Conditional logic eval | Both client and server | UX needs client; correctness needs server |
| Mapping | Per-field "Apply" + "Append all to notes" | Semi-automatic; band stays in control |
| Mapping target list | Curated PHP registry | Safe; small set; easy to extend |
| Notifications | Email band owner only | Avoid notification fatigue |
| Branding | Band's name/email/from-address | Matches existing portal emails |
| Deletions | Soft delete templates and instances | Recoverable |
| Slug uniqueness | Per band | Friendlier URLs; matches charts/colorways |

## Open questions

None. Ready for plan-writing.
