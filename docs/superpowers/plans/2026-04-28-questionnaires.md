# Questionnaires Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build a JotForm-style questionnaire system that lets bands create reusable templates, snapshot-send them to clients via the contact portal, and semi-automatically map answers onto events.

**Architecture:** Five new tables (`questionnaires`, `questionnaire_fields`, `questionnaire_instances`, `questionnaire_instance_fields`, `questionnaire_responses`) replacing two stub tables from 2021. Four bounded modules: template management, instance lifecycle, client portal, event integration. PHP-side registries for field types and mapping targets. Conditional logic evaluated identically in PHP and JS.

**Tech Stack:** Laravel 12, Inertia v1, Vue 3, PrimeVue, Tailwind 3, vuedraggable 4.1.0, Spatie Permission, Spatie Activitylog, PHPUnit 11, Vitest.

**Spec:** `docs/superpowers/specs/2026-04-28-questionnaires-design.md`

---

## Critical project rules (read before starting)

1. **All shell commands run inside the Docker container** via `docker-compose exec app …` for PHP/Composer/artisan or `docker-compose exec node …` for npm/Vitest. Never run on the host.
2. **Test method names use the `test_` prefix** — never `it_`, never `/** @test */` doc-comment, never `#[Test]` attribute.
3. **All migrations are generated via `php artisan make:migration`**, then edited. Never write migration files from scratch.
4. **Never alter previously deployed migrations.** Fix forward with new migrations only.
5. **Never edit `database/schema/mysql-schema.sql` or `mysql-test-schema.sql`** directly.
6. **Never run `php artisan schema:dump` on this branch.** Schema dump only happens on master after a production deploy.
7. **Run tests with parallel execution by default**: `php artisan test --parallel --processes=4`. Sequential for debugging only.
8. **Always run `php artisan ziggy:generate` after adding/changing routes** so Vue's `route()` helper sees them.
9. **Commit after each step that ends with "Commit"** using HEREDOC format with `Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>`.

## Phase overview

- **Phase 1:** Foundation — drop old tables, add new tables, models, factories, permission seeding (~7 tasks)
- **Phase 2:** Field-type registry, mapping registry, visibility evaluator (PHP + JS) (~5 tasks)
- **Phase 3:** Template builder backend + Vue editor (~9 tasks)
- **Phase 4:** Sending + instance lifecycle + notifications (~7 tasks)
- **Phase 5:** Client portal flow (~6 tasks)
- **Phase 6:** Event integration — summary panel + mapping + notes append (~6 tasks)

Each phase ends with a checkpoint where tests pass and the code merges cleanly into the rest of the system.

---

# Phase 1 — Foundation

Goal: drop old questionnaire scaffolding, create the new schema, models, factories, and Spatie permission, then verify the test database can boot from a clean slate.

## Task 1: Remove old questionnaire scaffolding

**Files:**
- Delete: `app/Models/Questionnairres.php`
- Delete: `app/Models/QuestionnaireComponents.php`
- Delete: `app/Services/QuestionnaireServices.php`
- Delete: `app/Observers/QuestionnaireComponentObserver.php`
- Delete: `app/Http/Controllers/QuestionnaireController.php`
- Delete: `resources/js/Pages/Questionnaire/Index.vue`
- Delete: `resources/js/Pages/Questionnaire/Edit.vue`
- Delete: `resources/js/Pages/Questionnaire/` (the directory itself, after files)
- Delete: `resources/js/Components/Questionnaire/` (any stale stubs there)
- Modify: `app/Models/User.php` — replace the `questionnaires()` method (we'll re-add a correct one in Task 5)
- Modify: `routes/questionnaire.php` — empty the file (we'll repopulate in Phase 3)

- [ ] **Step 1: List every file currently referencing the old classes**

Run: `docker-compose exec app grep -rE "Questionnairres|QuestionnaireComponents|QuestionnaireServices" app/ resources/ routes/ tests/ --include="*.php" --include="*.vue" --include="*.js" -l`

Expected: lists at least `User.php`, `routes/questionnaire.php`, the four old PHP files, and the two old Vue files. No tests reference them.

- [ ] **Step 2: Delete the obsolete files**

Run from project root (host shell is fine for `rm`):
```bash
rm app/Models/Questionnairres.php
rm app/Models/QuestionnaireComponents.php
rm app/Services/QuestionnaireServices.php
rm app/Observers/QuestionnaireComponentObserver.php
rm app/Http/Controllers/QuestionnaireController.php
rm resources/js/Pages/Questionnaire/Index.vue
rm resources/js/Pages/Questionnaire/Edit.vue
rmdir resources/js/Pages/Questionnaire 2>/dev/null || rm -rf resources/js/Pages/Questionnaire
rm -rf resources/js/Components/Questionnaire 2>/dev/null || true
```

- [ ] **Step 3: Strip the `questionnaires()` method out of User.php**

In `app/Models/User.php`, delete the entire method block from line 152 through its closing brace (the one that calls `Questionnairres::whereIn(...)`). We'll add a corrected one in Task 5. Also remove any `use App\Models\Questionnairres;` import at the top of the file.

- [ ] **Step 4: Empty the routes file**

Replace the contents of `routes/questionnaire.php` with:

```php
<?php

// Questionnaire routes are registered here in Phase 3.
```

- [ ] **Step 5: Verify nothing else references the deleted classes**

Run: `docker-compose exec app grep -rE "Questionnairres|QuestionnaireComponents|QuestionnaireServices|QuestionnaireComponentObserver" app/ resources/ routes/ tests/ --include="*.php" --include="*.vue" --include="*.js"`

Expected: no output (zero matches). If anything still references them, fix the file (likely by deleting the import or the line).

- [ ] **Step 6: Run the full backend test suite to confirm nothing is broken**

Run: `docker-compose exec app php artisan test --parallel --processes=4`

Expected: PASS. Only confirms the deletions don't break existing tests; the questionnaire feature itself has no tests yet.

- [ ] **Step 7: Commit**

```bash
git add -A
git commit -m "$(cat <<'EOF'
Remove obsolete questionnaire scaffolding

Stub feature from 2021 (typo'd table name, three field types, never used in
production). Clean slate for the new questionnaire system per the design spec.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 2: Create the migration that drops the old tables

**Files:**
- Create: `database/migrations/<timestamp>_drop_old_questionnaire_tables.php`

- [ ] **Step 1: Generate the migration**

Run: `docker-compose exec app php artisan make:migration drop_old_questionnaire_tables --no-interaction`

Expected: a new file appears under `database/migrations/`, e.g. `2026_04_28_120000_drop_old_questionnaire_tables.php`. Note the path it printed; use that path for the next step.

- [ ] **Step 2: Replace the migration body**

Open the new migration file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('questionnaire_components');
        Schema::dropIfExists('questionnairres');
    }

    public function down(): void
    {
        // Intentionally empty: the old tables are dead and not coming back.
        // If a rollback is ever needed for this migration, recreate them by
        // running an older migration snapshot from history.
    }
};
```

- [ ] **Step 3: Run migrate to apply, then check tables are gone**

Run: `docker-compose exec app php artisan migrate --no-interaction`

Then verify: `docker-compose exec app php artisan tinker --execute="echo Schema::hasTable('questionnairres') ? 'STILL EXISTS' : 'DROPPED';"`

Expected: `DROPPED`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "$(cat <<'EOF'
Drop legacy questionnairres and questionnaire_components tables

These were stubs from 2021. No production data was ever written to them.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 3: Create the new questionnaires tables

**Files:**
- Create: `database/migrations/<timestamp>_create_questionnaires_tables.php`

- [ ] **Step 1: Generate the migration**

Run: `docker-compose exec app php artisan make:migration create_questionnaires_tables --no-interaction`

- [ ] **Step 2: Replace the migration body**

Open the new file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->foreignId('band_id')->constrained('bands')->onDelete('cascade');
            $table->string('name', 120);
            $table->string('slug', 140);
            $table->text('description')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['band_id', 'slug']);
            $table->index(['band_id', 'archived_at']);
        });

        Schema::create('questionnaire_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained('questionnaires')->onDelete('cascade');
            $table->string('type', 40);
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('position');
            $table->json('settings')->nullable();
            $table->json('visibility_rule')->nullable();
            $table->string('mapping_target', 60)->nullable();
            $table->timestamps();

            $table->index(['questionnaire_id', 'position']);
        });

        Schema::create('questionnaire_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->nullable()->constrained('questionnaires')->nullOnDelete();
            $table->foreignId('booking_id')->constrained('bookings')->onDelete('cascade');
            $table->foreignId('recipient_contact_id')->constrained('contacts');
            $table->foreignId('sent_by_user_id')->constrained('users');
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('sent');
            $table->timestamp('sent_at');
            $table->timestamp('first_opened_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->foreignId('locked_by_user_id')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['booking_id', 'status']);
            $table->index('recipient_contact_id');
        });

        Schema::create('questionnaire_instance_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('questionnaire_instances')->onDelete('cascade');
            $table->unsignedBigInteger('source_field_id')->nullable(); // reference only; no FK
            $table->string('type', 40);
            $table->string('label', 255);
            $table->text('help_text')->nullable();
            $table->boolean('required')->default(false);
            $table->integer('position');
            $table->json('settings')->nullable();
            $table->json('visibility_rule')->nullable();
            $table->string('mapping_target', 60)->nullable();
            $table->timestamps();

            $table->index(['instance_id', 'position']);
        });

        Schema::create('questionnaire_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('instance_id')->constrained('questionnaire_instances')->onDelete('cascade');
            $table->foreignId('instance_field_id')->constrained('questionnaire_instance_fields')->onDelete('cascade');
            $table->text('value')->nullable();
            $table->timestamp('applied_to_event_at')->nullable();
            $table->foreignId('applied_by_user_id')->nullable()->constrained('users');
            $table->timestamps();

            $table->unique(['instance_id', 'instance_field_id']);
            $table->index('instance_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_responses');
        Schema::dropIfExists('questionnaire_instance_fields');
        Schema::dropIfExists('questionnaire_instances');
        Schema::dropIfExists('questionnaire_fields');
        Schema::dropIfExists('questionnaires');
    }
};
```

- [ ] **Step 3: Run migrate**

Run: `docker-compose exec app php artisan migrate --no-interaction`

Expected: all five tables created. Verify: `docker-compose exec app php artisan tinker --execute="echo collect(['questionnaires','questionnaire_fields','questionnaire_instances','questionnaire_instance_fields','questionnaire_responses'])->map(fn(\$t) => \$t.'='.(\Schema::hasTable(\$t)?'OK':'MISSING'))->implode(', ');"`

Expected: `questionnaires=OK, questionnaire_fields=OK, questionnaire_instances=OK, questionnaire_instance_fields=OK, questionnaire_responses=OK`

- [ ] **Step 4: Commit**

```bash
git add database/migrations/
git commit -m "$(cat <<'EOF'
Add questionnaires schema (templates, fields, instances, responses)

Five new tables backing the new questionnaire system. Schema follows the
design spec: nullable template FK on instances (preserves history when a
template is deleted), JSON settings/visibility_rule for type-specific shape,
mapping_target as registry key string.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 4: Add `questionnaires` to Spatie permission registry

**Files:**
- Create: `database/migrations/<timestamp>_add_questionnaires_permission.php`
- Modify: `app/Enums/BandResource.php`

- [ ] **Step 1: Generate the migration**

Run: `docker-compose exec app php artisan make:migration add_questionnaires_permission --no-interaction`

- [ ] **Step 2: Replace the migration body**

Open the new file and replace its contents with:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'read:questionnaires', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'write:questionnaires', 'guard_name' => 'web']);

        $ownerRole = Role::where('name', 'band-owner')->where('guard_name', 'web')->first();
        if ($ownerRole) {
            $ownerRole->givePermissionTo(['read:questionnaires', 'write:questionnaires']);
        }

        $memberRole = Role::where('name', 'band-member')->where('guard_name', 'web')->first();
        if ($memberRole) {
            $memberRole->givePermissionTo('read:questionnaires');
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        Permission::where('name', 'read:questionnaires')->where('guard_name', 'web')->get()->each->delete();
        Permission::where('name', 'write:questionnaires')->where('guard_name', 'web')->get()->each->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
```

- [ ] **Step 3: Add the enum case**

Open `app/Enums/BandResource.php`. After the `case Songs = 'songs';` line, add:

```php
    case Questionnaires = 'questionnaires';
```

The full enum should now look like:

```php
<?php

namespace App\Enums;

enum BandResource: string
{
    case Events = 'events';
    case Proposals = 'proposals';
    case Invoices = 'invoices';
    case Colors = 'colors';
    case Charts = 'charts';
    case Bookings = 'bookings';
    case Rehearsals = 'rehearsals';
    case Media = 'media';
    case Songs = 'songs';
    case Questionnaires = 'questionnaires';

    public function readPermission(): string
    {
        return 'read:' . $this->value;
    }

    public function writePermission(): string
    {
        return 'write:' . $this->value;
    }

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
```

- [ ] **Step 4: Run migrate**

Run: `docker-compose exec app php artisan migrate --no-interaction`

Verify: `docker-compose exec app php artisan tinker --execute="echo Spatie\Permission\Models\Permission::where('name','read:questionnaires')->exists()?'OK':'MISSING';"`

Expected: `OK`

- [ ] **Step 5: Commit**

```bash
git add app/Enums/BandResource.php database/migrations/
git commit -m "$(cat <<'EOF'
Add questionnaires permission to Spatie registry

Mirrors the songs-permission migration pattern. Band owners get full access
by default; band members get read-only and can be granted write via the UI.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 5: Create Eloquent models with relationships

**Files:**
- Create: `app/Models/Questionnaires.php`
- Create: `app/Models/QuestionnaireFields.php`
- Create: `app/Models/QuestionnaireInstances.php`
- Create: `app/Models/QuestionnaireInstanceFields.php`
- Create: `app/Models/QuestionnaireResponses.php`
- Modify: `app/Models/User.php` (add new `questionnaires()` relationship)
- Modify: `app/Models/Bands.php` (add `questionnaires()` relationship)
- Modify: `app/Models/Bookings.php` (add `questionnaireInstances()` relationship)

- [ ] **Step 1: Create `app/Models/Questionnaires.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Questionnaires extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    protected $table = 'questionnaires';

    protected $fillable = [
        'band_id',
        'name',
        'slug',
        'description',
        'archived_at',
    ];

    protected $casts = [
        'archived_at' => 'datetime',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(QuestionnaireFields::class, 'questionnaire_id')->orderBy('position');
    }

    public function instances(): HasMany
    {
        return $this->hasMany(QuestionnaireInstances::class, 'questionnaire_id');
    }

    public function setNameAttribute(string $value): void
    {
        $this->attributes['name'] = $value;
        if (empty($this->attributes['slug']) || $this->isDirty('name')) {
            $this->attributes['slug'] = $this->generateUniqueSlugForBand($value);
        }
    }

    private function generateUniqueSlugForBand(string $name): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $counter = 2;

        while (
            static::query()
                ->where('band_id', $this->band_id)
                ->where('slug', $slug)
                ->where('id', '!=', $this->id ?? 0)
                ->exists()
        ) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    public function isArchived(): bool
    {
        return $this->archived_at !== null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['band_id', 'name', 'description', 'archived_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('questionnaires');
    }
}
```

- [ ] **Step 2: Create `app/Models/QuestionnaireFields.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionnaireFields extends Model
{
    use HasFactory;

    protected $table = 'questionnaire_fields';

    protected $fillable = [
        'questionnaire_id',
        'type',
        'label',
        'help_text',
        'required',
        'position',
        'settings',
        'visibility_rule',
        'mapping_target',
    ];

    protected $casts = [
        'required' => 'boolean',
        'settings' => 'array',
        'visibility_rule' => 'array',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaires::class, 'questionnaire_id');
    }
}
```

- [ ] **Step 3: Create `app/Models/QuestionnaireInstances.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuestionnaireInstances extends Model
{
    use HasFactory;
    use SoftDeletes;
    use LogsActivity;

    public const STATUS_SENT = 'sent';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_LOCKED = 'locked';

    protected $table = 'questionnaire_instances';

    protected $fillable = [
        'questionnaire_id',
        'booking_id',
        'recipient_contact_id',
        'sent_by_user_id',
        'name',
        'description',
        'status',
        'sent_at',
        'first_opened_at',
        'submitted_at',
        'locked_at',
        'locked_by_user_id',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'first_opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'locked_at' => 'datetime',
    ];

    public function questionnaire(): BelongsTo
    {
        return $this->belongsTo(Questionnaires::class, 'questionnaire_id');
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }

    public function recipientContact(): BelongsTo
    {
        return $this->belongsTo(Contacts::class, 'recipient_contact_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function lockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'locked_by_user_id');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(QuestionnaireInstanceFields::class, 'instance_id')->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(QuestionnaireResponses::class, 'instance_id');
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'submitted_at', 'locked_at', 'locked_by_user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('questionnaires');
    }
}
```

- [ ] **Step 4: Create `app/Models/QuestionnaireInstanceFields.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class QuestionnaireInstanceFields extends Model
{
    use HasFactory;

    protected $table = 'questionnaire_instance_fields';

    protected $fillable = [
        'instance_id',
        'source_field_id',
        'type',
        'label',
        'help_text',
        'required',
        'position',
        'settings',
        'visibility_rule',
        'mapping_target',
    ];

    protected $casts = [
        'required' => 'boolean',
        'settings' => 'array',
        'visibility_rule' => 'array',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstances::class, 'instance_id');
    }

    public function response(): HasOne
    {
        return $this->hasOne(QuestionnaireResponses::class, 'instance_field_id');
    }
}
```

- [ ] **Step 5: Create `app/Models/QuestionnaireResponses.php`**

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QuestionnaireResponses extends Model
{
    use HasFactory;
    use LogsActivity;

    protected $table = 'questionnaire_responses';

    protected $fillable = [
        'instance_id',
        'instance_field_id',
        'value',
        'applied_to_event_at',
        'applied_by_user_id',
    ];

    protected $casts = [
        'applied_to_event_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstances::class, 'instance_id');
    }

    public function instanceField(): BelongsTo
    {
        return $this->belongsTo(QuestionnaireInstanceFields::class, 'instance_field_id');
    }

    public function appliedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applied_by_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['value', 'applied_to_event_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('questionnaires');
    }
}
```

- [ ] **Step 6: Add `questionnaires()` to User model**

In `app/Models/User.php`, add this method (replacing whatever is left from Task 1's deletion). Place it next to the `charts()` method:

```php
    public function questionnaires()
    {
        $bandIds = $this->allBands()->pluck('id')->toArray();
        return \App\Models\Questionnaires::whereIn('band_id', $bandIds)
            ->whereNull('archived_at')
            ->orderBy('name')
            ->get();
    }
```

- [ ] **Step 7: Add `questionnaires()` to Bands model**

In `app/Models/Bands.php`, add the relationship near the existing relationships:

```php
    public function questionnaires()
    {
        return $this->hasMany(Questionnaires::class, 'band_id');
    }
```

- [ ] **Step 8: Add `questionnaireInstances()` to Bookings model**

In `app/Models/Bookings.php`, add the relationship:

```php
    public function questionnaireInstances()
    {
        return $this->hasMany(QuestionnaireInstances::class, 'booking_id');
    }
```

- [ ] **Step 9: Smoke-test that the models load**

Run: `docker-compose exec app php artisan tinker --execute="echo class_exists('App\\Models\\Questionnaires').','.class_exists('App\\Models\\QuestionnaireFields').','.class_exists('App\\Models\\QuestionnaireInstances').','.class_exists('App\\Models\\QuestionnaireInstanceFields').','.class_exists('App\\Models\\QuestionnaireResponses');"`

Expected: `1,1,1,1,1`

- [ ] **Step 10: Commit**

```bash
git add app/Models/
git commit -m "$(cat <<'EOF'
Add Eloquent models for questionnaires

Five new models with relationships, casts, soft-delete on Questionnaires and
Instances, Spatie Activitylog on the three top-level models. Slug generation
on Questionnaires is band-scoped.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 6: Create model factories

**Files:**
- Create: `database/factories/QuestionnairesFactory.php`
- Create: `database/factories/QuestionnaireFieldsFactory.php`
- Create: `database/factories/QuestionnaireInstancesFactory.php`
- Create: `database/factories/QuestionnaireInstanceFieldsFactory.php`
- Create: `database/factories/QuestionnaireResponsesFactory.php`

- [ ] **Step 1: Create QuestionnairesFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Bands;
use App\Models\Questionnaires;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnairesFactory extends Factory
{
    protected $model = Questionnaires::class;

    public function definition(): array
    {
        return [
            'band_id' => Bands::factory(),
            'name' => $this->faker->words(3, true),
            'description' => $this->faker->sentence,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => ['archived_at' => now()]);
    }
}
```

- [ ] **Step 2: Create QuestionnaireFieldsFactory**

```php
<?php

namespace Database\Factories;

use App\Models\QuestionnaireFields;
use App\Models\Questionnaires;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireFieldsFactory extends Factory
{
    protected $model = QuestionnaireFields::class;

    public function definition(): array
    {
        return [
            'questionnaire_id' => Questionnaires::factory(),
            'type' => 'short_text',
            'label' => $this->faker->sentence(4),
            'help_text' => null,
            'required' => false,
            'position' => 10,
            'settings' => null,
            'visibility_rule' => null,
            'mapping_target' => null,
        ];
    }

    public function ofType(string $type, array $settings = null): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'settings' => $settings,
        ]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['required' => true]);
    }
}
```

- [ ] **Step 3: Create QuestionnaireInstancesFactory**

```php
<?php

namespace Database\Factories;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Questionnaires;
use App\Models\QuestionnaireInstances;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireInstancesFactory extends Factory
{
    protected $model = QuestionnaireInstances::class;

    public function definition(): array
    {
        $template = Questionnaires::factory()->create();
        return [
            'questionnaire_id' => $template->id,
            'booking_id' => Bookings::factory(),
            'recipient_contact_id' => Contacts::factory(),
            'sent_by_user_id' => User::factory(),
            'name' => $template->name,
            'description' => $template->description,
            'status' => QuestionnaireInstances::STATUS_SENT,
            'sent_at' => now(),
        ];
    }

    public function inProgress(): static
    {
        return $this->state(fn () => ['status' => QuestionnaireInstances::STATUS_IN_PROGRESS]);
    }

    public function submitted(): static
    {
        return $this->state(fn () => [
            'status' => QuestionnaireInstances::STATUS_SUBMITTED,
            'submitted_at' => now(),
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => QuestionnaireInstances::STATUS_LOCKED,
            'locked_at' => now(),
        ]);
    }
}
```

- [ ] **Step 4: Create QuestionnaireInstanceFieldsFactory**

```php
<?php

namespace Database\Factories;

use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireInstanceFieldsFactory extends Factory
{
    protected $model = QuestionnaireInstanceFields::class;

    public function definition(): array
    {
        return [
            'instance_id' => QuestionnaireInstances::factory(),
            'source_field_id' => null,
            'type' => 'short_text',
            'label' => $this->faker->sentence(4),
            'required' => false,
            'position' => 10,
            'settings' => null,
            'visibility_rule' => null,
            'mapping_target' => null,
        ];
    }
}
```

- [ ] **Step 5: Create QuestionnaireResponsesFactory**

```php
<?php

namespace Database\Factories;

use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\QuestionnaireResponses;
use Illuminate\Database\Eloquent\Factories\Factory;

class QuestionnaireResponsesFactory extends Factory
{
    protected $model = QuestionnaireResponses::class;

    public function definition(): array
    {
        $field = QuestionnaireInstanceFields::factory()->create();
        return [
            'instance_id' => $field->instance_id,
            'instance_field_id' => $field->id,
            'value' => $this->faker->sentence,
        ];
    }
}
```

- [ ] **Step 6: Smoke-test the factories**

Run: `docker-compose exec app php artisan tinker --execute="DB::beginTransaction(); echo \App\Models\Questionnaires::factory()->create()->id ? 'OK' : 'FAIL'; DB::rollBack();"`

Expected: `OK`

- [ ] **Step 7: Commit**

```bash
git add database/factories/
git commit -m "$(cat <<'EOF'
Add factories for questionnaire models

Factories for the five new models with helper states (archived, inProgress,
submitted, locked, ofType, required). Used by feature/unit tests in later
phases.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 7: Phase 1 checkpoint — full test suite + lint

- [ ] **Step 1: Run the full backend test suite**

Run: `docker-compose exec app php artisan test --parallel --processes=4`

Expected: PASS — no regressions from the structural changes.

- [ ] **Step 2: Run the frontend tests** (no questionnaire frontend yet, but make sure nothing else broke)

Run: `docker-compose exec node npm run test:run`

Expected: PASS

- [ ] **Step 3: Confirm no orphaned references**

Run: `docker-compose exec app grep -rE "Questionnairres|QuestionnaireComponents|QuestionnaireServices" app/ resources/ routes/ tests/ --include="*.php" --include="*.vue" --include="*.js"`

Expected: zero matches.

- [ ] **Step 4: Phase 1 done. No commit needed (everything's already committed). Move on to Phase 2.**

---

# Phase 2 — Registries and visibility evaluator

Goal: build the field-type registry, mapping registry, and visibility evaluator (PHP + JS), all with unit tests. Pure logic; no HTTP, no DB writes beyond what tests need.

## Task 8: Field-type registry

**Files:**
- Create: `app/Services/QuestionnaireFieldTypeRegistry.php`
- Create: `tests/Unit/Services/QuestionnaireFieldTypeRegistryTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/QuestionnaireFieldTypeRegistryTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireFieldTypeRegistry;
use PHPUnit\Framework\TestCase;

class QuestionnaireFieldTypeRegistryTest extends TestCase
{
    private QuestionnaireFieldTypeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new QuestionnaireFieldTypeRegistry();
    }

    public function test_known_types_returns_all_twelve_field_types(): void
    {
        $types = $this->registry->knownTypes();

        $this->assertCount(12, $types);
        $this->assertContains('short_text', $types);
        $this->assertContains('long_text', $types);
        $this->assertContains('date', $types);
        $this->assertContains('time', $types);
        $this->assertContains('email', $types);
        $this->assertContains('phone', $types);
        $this->assertContains('dropdown', $types);
        $this->assertContains('multi_select', $types);
        $this->assertContains('checkbox_group', $types);
        $this->assertContains('yes_no', $types);
        $this->assertContains('header', $types);
        $this->assertContains('instructions', $types);
    }

    public function test_is_known_type_returns_true_for_registered_types(): void
    {
        $this->assertTrue($this->registry->isKnownType('short_text'));
        $this->assertTrue($this->registry->isKnownType('header'));
    }

    public function test_is_known_type_returns_false_for_unknown_type(): void
    {
        $this->assertFalse($this->registry->isKnownType('song_picker'));
        $this->assertFalse($this->registry->isKnownType(''));
    }

    public function test_is_input_type_returns_false_for_header_and_instructions(): void
    {
        $this->assertFalse($this->registry->isInputType('header'));
        $this->assertFalse($this->registry->isInputType('instructions'));
    }

    public function test_is_input_type_returns_true_for_actual_inputs(): void
    {
        $this->assertTrue($this->registry->isInputType('short_text'));
        $this->assertTrue($this->registry->isInputType('dropdown'));
        $this->assertTrue($this->registry->isInputType('yes_no'));
    }

    public function test_definitions_includes_label_for_every_type(): void
    {
        foreach ($this->registry->knownTypes() as $type) {
            $def = $this->registry->definition($type);
            $this->assertArrayHasKey('label', $def);
            $this->assertNotEmpty($def['label']);
        }
    }

    public function test_definition_throws_for_unknown_type(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->definition('nonexistent');
    }

    public function test_dropdown_definition_marks_options_as_required_setting(): void
    {
        $def = $this->registry->definition('dropdown');
        $this->assertContains('options', $def['required_settings'] ?? []);
    }
}
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireFieldTypeRegistryTest.php`

Expected: FAIL — `Class "App\Services\QuestionnaireFieldTypeRegistry" not found`.

- [ ] **Step 3: Create the registry**

Create `app/Services/QuestionnaireFieldTypeRegistry.php`:

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class QuestionnaireFieldTypeRegistry
{
    /**
     * @return array<string, array{label: string, is_input: bool, required_settings?: array<string>}>
     */
    private function definitions(): array
    {
        return [
            'short_text'     => ['label' => 'Short text',      'is_input' => true],
            'long_text'      => ['label' => 'Long text',       'is_input' => true],
            'date'           => ['label' => 'Date',            'is_input' => true],
            'time'           => ['label' => 'Time',            'is_input' => true],
            'email'          => ['label' => 'Email',           'is_input' => true],
            'phone'          => ['label' => 'Phone',           'is_input' => true],
            'dropdown'       => ['label' => 'Dropdown',        'is_input' => true, 'required_settings' => ['options']],
            'multi_select'   => ['label' => 'Multi-select',    'is_input' => true, 'required_settings' => ['options']],
            'checkbox_group' => ['label' => 'Checkboxes',      'is_input' => true, 'required_settings' => ['options']],
            'yes_no'         => ['label' => 'Yes / No',        'is_input' => true],
            'header'         => ['label' => 'Section header',  'is_input' => false],
            'instructions'   => ['label' => 'Instruction text', 'is_input' => false],
        ];
    }

    /**
     * @return array<string>
     */
    public function knownTypes(): array
    {
        return array_keys($this->definitions());
    }

    public function isKnownType(string $type): bool
    {
        return array_key_exists($type, $this->definitions());
    }

    public function isInputType(string $type): bool
    {
        if (!$this->isKnownType($type)) {
            return false;
        }
        return (bool) $this->definitions()[$type]['is_input'];
    }

    /**
     * @return array{label: string, is_input: bool, required_settings?: array<string>}
     */
    public function definition(string $type): array
    {
        if (!$this->isKnownType($type)) {
            throw new InvalidArgumentException("Unknown field type: {$type}");
        }
        return $this->definitions()[$type];
    }

    /**
     * Returns the full type catalog suitable for shipping to the Vue layer
     * (used by Vuex on builder mount).
     *
     * @return array<int, array{type: string, label: string, is_input: bool, required_settings: array<string>}>
     */
    public function catalog(): array
    {
        return collect($this->definitions())->map(fn ($def, $type) => [
            'type' => $type,
            'label' => $def['label'],
            'is_input' => $def['is_input'],
            'required_settings' => $def['required_settings'] ?? [],
        ])->values()->all();
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireFieldTypeRegistryTest.php`

Expected: PASS — 8 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestionnaireFieldTypeRegistry.php tests/Unit/Services/QuestionnaireFieldTypeRegistryTest.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireFieldTypeRegistry

Single source of truth for the 12 supported field types: label, input vs.
non-input distinction, and required-settings declaration. Used by validation,
builder UX, and other registries.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 9: Mapping registry

**Files:**
- Create: `app/Services/QuestionnaireMappingRegistry.php`
- Create: `tests/Unit/Services/QuestionnaireMappingRegistryTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/QuestionnaireMappingRegistryTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireMappingRegistry;
use Tests\TestCase;

class QuestionnaireMappingRegistryTest extends TestCase
{
    private QuestionnaireMappingRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new QuestionnaireMappingRegistry();
    }

    public function test_keys_returns_all_seven_curated_targets(): void
    {
        $keys = $this->registry->keys();

        $this->assertCount(7, $keys);
        $this->assertContains('wedding.onsite', $keys);
        $this->assertContains('wedding.outside', $keys);
        $this->assertContains('wedding.dance.first', $keys);
        $this->assertContains('wedding.dance.father_daughter', $keys);
        $this->assertContains('wedding.dance.mother_son', $keys);
        $this->assertContains('wedding.dance.money', $keys);
        $this->assertContains('wedding.dance.bouquet_garter', $keys);
    }

    public function test_target_exists_returns_true_for_registered_key(): void
    {
        $this->assertTrue($this->registry->targetExists('wedding.onsite'));
    }

    public function test_target_exists_returns_false_for_unknown_key(): void
    {
        $this->assertFalse($this->registry->targetExists('wedding.nonexistent'));
    }

    public function test_compatible_field_types_for_yes_no_targets(): void
    {
        $this->assertSame(['yes_no'], $this->registry->compatibleFieldTypes('wedding.onsite'));
        $this->assertSame(['yes_no'], $this->registry->compatibleFieldTypes('wedding.outside'));
    }

    public function test_compatible_field_types_for_dance_targets(): void
    {
        $this->assertSame(['short_text'], $this->registry->compatibleFieldTypes('wedding.dance.first'));
    }

    public function test_compatible_field_types_throws_for_unknown_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->compatibleFieldTypes('wedding.nonexistent');
    }

    public function test_label_returns_human_readable_target_name(): void
    {
        $this->assertSame('Wedding · Onsite Ceremony', $this->registry->label('wedding.onsite'));
        $this->assertSame('Wedding · First Dance', $this->registry->label('wedding.dance.first'));
    }

    public function test_dance_title_returns_array_title_for_dance_targets(): void
    {
        $this->assertSame('First Dance', $this->registry->danceTitle('wedding.dance.first'));
        $this->assertSame('Father Daughter', $this->registry->danceTitle('wedding.dance.father_daughter'));
        $this->assertSame('Mother Son', $this->registry->danceTitle('wedding.dance.mother_son'));
        $this->assertSame('Money Dance', $this->registry->danceTitle('wedding.dance.money'));
        $this->assertSame('Bouquet/Garter', $this->registry->danceTitle('wedding.dance.bouquet_garter'));
    }

    public function test_dance_title_returns_null_for_non_dance_targets(): void
    {
        $this->assertNull($this->registry->danceTitle('wedding.onsite'));
    }
}
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireMappingRegistryTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Create the registry**

Create `app/Services/QuestionnaireMappingRegistry.php`:

```php
<?php

namespace App\Services;

use InvalidArgumentException;

class QuestionnaireMappingRegistry
{
    public const TYPE_BOOLEAN_PATH = 'boolean_path';
    public const TYPE_DANCE_ENTRY = 'dance_entry';

    /**
     * @return array<string, array{
     *     label: string,
     *     compatible_field_types: array<string>,
     *     kind: string,
     *     event_path?: array<string>,
     *     dance_title?: string,
     * }>
     */
    private function targets(): array
    {
        return [
            'wedding.onsite' => [
                'label' => 'Wedding · Onsite Ceremony',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'wedding', 'onsite'],
            ],
            'wedding.outside' => [
                'label' => 'Event · Outside Event',
                'compatible_field_types' => ['yes_no'],
                'kind' => self::TYPE_BOOLEAN_PATH,
                'event_path' => ['additional_data', 'outside'],
            ],
            'wedding.dance.first' => [
                'label' => 'Wedding · First Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'First Dance',
            ],
            'wedding.dance.father_daughter' => [
                'label' => 'Wedding · Father-Daughter Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Father Daughter',
            ],
            'wedding.dance.mother_son' => [
                'label' => 'Wedding · Mother-Son Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Mother Son',
            ],
            'wedding.dance.money' => [
                'label' => 'Wedding · Money Dance',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Money Dance',
            ],
            'wedding.dance.bouquet_garter' => [
                'label' => 'Wedding · Bouquet/Garter',
                'compatible_field_types' => ['short_text'],
                'kind' => self::TYPE_DANCE_ENTRY,
                'dance_title' => 'Bouquet/Garter',
            ],
        ];
    }

    /**
     * @return array<string>
     */
    public function keys(): array
    {
        return array_keys($this->targets());
    }

    public function targetExists(string $key): bool
    {
        return array_key_exists($key, $this->targets());
    }

    /**
     * @return array<string>
     */
    public function compatibleFieldTypes(string $key): array
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['compatible_field_types'];
    }

    public function label(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['label'];
    }

    public function kind(string $key): string
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['kind'];
    }

    /**
     * @return array<string>
     */
    public function eventPath(string $key): array
    {
        $this->assertTargetExists($key);
        return $this->targets()[$key]['event_path'] ?? [];
    }

    public function danceTitle(string $key): ?string
    {
        if (!$this->targetExists($key)) {
            return null;
        }
        return $this->targets()[$key]['dance_title'] ?? null;
    }

    /**
     * Catalog suitable for sending to the builder UX. Each entry includes
     * a key, label, and compatible_field_types so the dropdown can filter
     * by selected field type.
     *
     * @return array<int, array{key: string, label: string, compatible_field_types: array<string>}>
     */
    public function catalog(): array
    {
        return collect($this->targets())->map(fn ($t, $key) => [
            'key' => $key,
            'label' => $t['label'],
            'compatible_field_types' => $t['compatible_field_types'],
        ])->values()->all();
    }

    private function assertTargetExists(string $key): void
    {
        if (!$this->targetExists($key)) {
            throw new InvalidArgumentException("Unknown mapping target: {$key}");
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireMappingRegistryTest.php`

Expected: PASS — 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestionnaireMappingRegistry.php tests/Unit/Services/QuestionnaireMappingRegistryTest.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireMappingRegistry with seven curated targets

Two boolean-path targets (onsite, outside) and five dance-entry targets
(first, father-daughter, mother-son, money, bouquet/garter). Adding new
targets is a code change here — no DB migration needed.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 10: Visibility evaluator (PHP)

**Files:**
- Create: `app/Services/QuestionnaireVisibilityEvaluator.php`
- Create: `tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\QuestionnaireVisibilityEvaluator;
use PHPUnit\Framework\TestCase;

class QuestionnaireVisibilityEvaluatorTest extends TestCase
{
    private QuestionnaireVisibilityEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new QuestionnaireVisibilityEvaluator();
    }

    /** Builds a flat field array with given fields each as an associative array */
    private function fields(array $fields): array
    {
        return $fields;
    }

    public function test_field_is_visible_when_no_rule_set(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
        ]);

        $this->assertTrue($this->evaluator->isVisible(1, $fields, []));
    }

    public function test_equals_operator_for_single_value_field(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'yes']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'yes']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'no']));
    }

    public function test_equals_operator_for_multi_value_field(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'rock']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['rock', 'jazz']]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['pop']]));
    }

    public function test_not_equals_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'not_equals', 'value' => 'no']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'yes']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'no']));
    }

    public function test_contains_operator_for_string(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'contains', 'value' => 'wedding']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'a wedding event']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'birthday party']));
    }

    public function test_contains_operator_for_array(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'contains', 'value' => 'cake']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['I want cake', 'plus drinks']]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['just drinks']]));
    }

    public function test_empty_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'empty', 'value' => null]],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => '']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => null]));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => []]));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => 'something']));
        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => ['x']]));
    }

    public function test_not_empty_operator(): void
    {
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'not_empty', 'value' => null]],
        ]);

        $this->assertFalse($this->evaluator->isVisible(2, $fields, [1 => '']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => 'something']));
        $this->assertTrue($this->evaluator->isVisible(2, $fields, [1 => ['x']]));
    }

    public function test_field_is_hidden_when_controller_is_hidden_transitively(): void
    {
        // 1 is always visible. 2 depends on 1=hide. 3 depends on 2=anything.
        // When 1='show', 2 becomes hidden, so 3 must be hidden too.
        $fields = $this->fields([
            ['id' => 1, 'visibility_rule' => null],
            ['id' => 2, 'visibility_rule' => ['depends_on' => 1, 'operator' => 'equals', 'value' => 'hide']],
            ['id' => 3, 'visibility_rule' => ['depends_on' => 2, 'operator' => 'not_empty', 'value' => null]],
        ]);

        // 1 = 'show' so 2 is hidden → 3 cascades hidden
        $this->assertFalse($this->evaluator->isVisible(3, $fields, [1 => 'show', 2 => 'anything']));

        // 1 = 'hide' so 2 visible → 3 follows its own rule
        $this->assertTrue($this->evaluator->isVisible(3, $fields, [1 => 'hide', 2 => 'anything']));
        $this->assertFalse($this->evaluator->isVisible(3, $fields, [1 => 'hide', 2 => '']));
    }

    public function test_returns_true_when_target_field_does_not_exist(): void
    {
        // Defensive: missing target field shouldn't crash the evaluator.
        // Treat as "always visible" since we can't satisfy the rule.
        $fields = $this->fields([
            ['id' => 2, 'visibility_rule' => ['depends_on' => 999, 'operator' => 'equals', 'value' => 'x']],
        ]);

        $this->assertTrue($this->evaluator->isVisible(2, $fields, []));
    }
}
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Create the evaluator**

Create `app/Services/QuestionnaireVisibilityEvaluator.php`:

```php
<?php

namespace App\Services;

class QuestionnaireVisibilityEvaluator
{
    /**
     * @param int $fieldId  The field whose visibility we're evaluating
     * @param array<int, array{id: int, visibility_rule: array|null}> $allFields
     * @param array<int, mixed> $responses  Keyed by field id
     */
    public function isVisible(int $fieldId, array $allFields, array $responses): bool
    {
        $field = $this->findField($fieldId, $allFields);
        if ($field === null) {
            return true;
        }
        return $this->fieldIsVisible($field, $allFields, $responses);
    }

    private function fieldIsVisible(array $field, array $allFields, array $responses): bool
    {
        $rule = $field['visibility_rule'] ?? null;
        if (empty($rule)) {
            return true;
        }

        $targetId = $rule['depends_on'] ?? null;
        $target = $this->findField($targetId, $allFields);
        if ($target === null) {
            return true;
        }

        if (!$this->fieldIsVisible($target, $allFields, $responses)) {
            return false;
        }

        $value = $responses[$targetId] ?? null;
        return $this->evaluate($rule, $value);
    }

    private function findField(?int $id, array $allFields): ?array
    {
        foreach ($allFields as $f) {
            if (($f['id'] ?? null) === $id) {
                return $f;
            }
        }
        return null;
    }

    /**
     * @param array{operator: string, value: mixed} $rule
     */
    private function evaluate(array $rule, mixed $value): bool
    {
        $operator = $rule['operator'];
        $expected = $rule['value'] ?? null;

        return match ($operator) {
            'equals' => $this->valueEquals($value, $expected),
            'not_equals' => !$this->valueEquals($value, $expected),
            'contains' => $this->valueContains($value, $expected),
            'empty' => $this->valueIsEmpty($value),
            'not_empty' => !$this->valueIsEmpty($value),
            default => false,
        };
    }

    private function valueEquals(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            return in_array($expected, $value, true);
        }
        return (string) $value === (string) $expected;
    }

    private function valueContains(mixed $value, mixed $expected): bool
    {
        if (is_array($value)) {
            foreach ($value as $item) {
                if (is_string($item) && str_contains($item, (string) $expected)) {
                    return true;
                }
            }
            return false;
        }
        return is_string($value) && str_contains($value, (string) $expected);
    }

    private function valueIsEmpty(mixed $value): bool
    {
        if (is_array($value)) {
            return empty($value);
        }
        return $value === null || $value === '';
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php`

Expected: PASS — 10 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestionnaireVisibilityEvaluator.php tests/Unit/Services/QuestionnaireVisibilityEvaluatorTest.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireVisibilityEvaluator (PHP)

Single-condition visibility rule evaluator. Supports equals, not_equals,
contains, empty, not_empty operators across both single-value and
multi-value fields. Transitive hiding cascades — if a controller field is
hidden, its dependents are also hidden.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 11: Visibility evaluator (JS) — mirror semantics

**Files:**
- Create: `resources/js/Pages/Contact/Questionnaire/visibility.js`
- Create: `resources/js/Pages/Contact/Questionnaire/__tests__/visibility.spec.js`

- [ ] **Step 1: Write the failing tests**

Create `resources/js/Pages/Contact/Questionnaire/__tests__/visibility.spec.js`:

```javascript
import { describe, it, expect } from 'vitest'
import { isFieldVisible } from '../visibility.js'

describe('visibility evaluator (JS)', () => {
  function fields(arr) {
    return arr
  }

  it('field is visible when no rule set', () => {
    const f = fields([{ id: 1, visibility_rule: null }])
    expect(isFieldVisible(1, f, {})).toBe(true)
  })

  it('equals operator for single-value field', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'yes' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'yes' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'no' })).toBe(false)
  })

  it('equals operator for multi-value field', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'rock' } },
    ])
    expect(isFieldVisible(2, f, { 1: ['rock', 'jazz'] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: ['pop'] })).toBe(false)
  })

  it('not_equals operator', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'not_equals', value: 'no' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'yes' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'no' })).toBe(false)
  })

  it('contains for string and array', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'contains', value: 'cake' } },
    ])
    expect(isFieldVisible(2, f, { 1: 'I want cake' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'just drinks' })).toBe(false)
    expect(isFieldVisible(2, f, { 1: ['I want cake', 'plus drinks'] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: ['just drinks'] })).toBe(false)
  })

  it('empty and not_empty operators', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'empty', value: null } },
      { id: 3, visibility_rule: { depends_on: 1, operator: 'not_empty', value: null } },
    ])
    expect(isFieldVisible(2, f, { 1: '' })).toBe(true)
    expect(isFieldVisible(2, f, { 1: null })).toBe(true)
    expect(isFieldVisible(2, f, { 1: [] })).toBe(true)
    expect(isFieldVisible(2, f, { 1: 'x' })).toBe(false)
    expect(isFieldVisible(3, f, { 1: 'x' })).toBe(true)
  })

  it('field is hidden when controller is hidden transitively', () => {
    const f = fields([
      { id: 1, visibility_rule: null },
      { id: 2, visibility_rule: { depends_on: 1, operator: 'equals', value: 'hide' } },
      { id: 3, visibility_rule: { depends_on: 2, operator: 'not_empty', value: null } },
    ])
    expect(isFieldVisible(3, f, { 1: 'show', 2: 'anything' })).toBe(false)
    expect(isFieldVisible(3, f, { 1: 'hide', 2: 'anything' })).toBe(true)
    expect(isFieldVisible(3, f, { 1: 'hide', 2: '' })).toBe(false)
  })

  it('returns true when target field does not exist', () => {
    const f = fields([
      { id: 2, visibility_rule: { depends_on: 999, operator: 'equals', value: 'x' } },
    ])
    expect(isFieldVisible(2, f, {})).toBe(true)
  })
})
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec node npx vitest run resources/js/Pages/Contact/Questionnaire/__tests__/visibility.spec.js`

Expected: FAIL — module not found.

- [ ] **Step 3: Create the evaluator**

Create `resources/js/Pages/Contact/Questionnaire/visibility.js`:

```javascript
/**
 * Mirrors app/Services/QuestionnaireVisibilityEvaluator.php exactly.
 * Any change here MUST be made in the PHP file too.
 *
 * @param {number} fieldId  The field whose visibility we're evaluating
 * @param {Array<{id:number,visibility_rule:object|null}>} allFields
 * @param {Object<number,*>} responses  Keyed by field id
 */
export function isFieldVisible(fieldId, allFields, responses) {
  const field = findField(fieldId, allFields)
  if (!field) return true
  return fieldIsVisible(field, allFields, responses)
}

function fieldIsVisible(field, allFields, responses) {
  const rule = field.visibility_rule
  if (!rule) return true

  const targetId = rule.depends_on
  const target = findField(targetId, allFields)
  if (!target) return true

  if (!fieldIsVisible(target, allFields, responses)) return false

  const value = responses[targetId] ?? null
  return evaluate(rule, value)
}

function findField(id, allFields) {
  return allFields.find(f => f.id === id) ?? null
}

function evaluate(rule, value) {
  const expected = rule.value ?? null
  switch (rule.operator) {
    case 'equals': return valueEquals(value, expected)
    case 'not_equals': return !valueEquals(value, expected)
    case 'contains': return valueContains(value, expected)
    case 'empty': return valueIsEmpty(value)
    case 'not_empty': return !valueIsEmpty(value)
    default: return false
  }
}

function valueEquals(value, expected) {
  if (Array.isArray(value)) return value.includes(expected)
  return String(value) === String(expected)
}

function valueContains(value, expected) {
  const needle = String(expected)
  if (Array.isArray(value)) {
    return value.some(item => typeof item === 'string' && item.includes(needle))
  }
  return typeof value === 'string' && value.includes(needle)
}

function valueIsEmpty(value) {
  if (Array.isArray(value)) return value.length === 0
  return value === null || value === undefined || value === ''
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec node npx vitest run resources/js/Pages/Contact/Questionnaire/__tests__/visibility.spec.js`

Expected: PASS — 8 tests (multi-assertion).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Contact/Questionnaire/
git commit -m "$(cat <<'EOF'
Add visibility evaluator (JS) mirroring PHP semantics

Identical match-on-operator logic to the PHP evaluator. Comments warn that
both files must be updated in lockstep. Tests cover the same cases as the
PHP unit test for parity.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 12: Field-settings validator

**Files:**
- Create: `app/Services/FieldSettingsValidator.php`
- Create: `tests/Unit/Services/FieldSettingsValidatorTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/FieldSettingsValidatorTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Services\FieldSettingsValidator;
use App\Services\QuestionnaireFieldTypeRegistry;
use PHPUnit\Framework\TestCase;

class FieldSettingsValidatorTest extends TestCase
{
    private FieldSettingsValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new FieldSettingsValidator(new QuestionnaireFieldTypeRegistry());
    }

    public function test_short_text_accepts_null_settings(): void
    {
        $errors = $this->validator->validate('short_text', null);
        $this->assertEmpty($errors);
    }

    public function test_short_text_accepts_empty_array_settings(): void
    {
        $errors = $this->validator->validate('short_text', []);
        $this->assertEmpty($errors);
    }

    public function test_dropdown_rejects_null_settings(): void
    {
        $errors = $this->validator->validate('dropdown', null);
        $this->assertNotEmpty($errors);
        $this->assertStringContainsString('options', strtolower(implode(' ', $errors)));
    }

    public function test_dropdown_rejects_empty_options_array(): void
    {
        $errors = $this->validator->validate('dropdown', ['options' => []]);
        $this->assertNotEmpty($errors);
    }

    public function test_dropdown_accepts_valid_options(): void
    {
        $errors = $this->validator->validate('dropdown', [
            'options' => [
                ['value' => 'a', 'label' => 'Option A'],
                ['value' => 'b', 'label' => 'Option B'],
            ],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_dropdown_rejects_options_missing_value_or_label(): void
    {
        $errors = $this->validator->validate('dropdown', [
            'options' => [['value' => 'a']],
        ]);
        $this->assertNotEmpty($errors);
    }

    public function test_multi_select_validates_same_as_dropdown(): void
    {
        $errors = $this->validator->validate('multi_select', null);
        $this->assertNotEmpty($errors);

        $errors = $this->validator->validate('multi_select', [
            'options' => [['value' => 'x', 'label' => 'X']],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_checkbox_group_validates_same_as_dropdown(): void
    {
        $errors = $this->validator->validate('checkbox_group', null);
        $this->assertNotEmpty($errors);

        $errors = $this->validator->validate('checkbox_group', [
            'options' => [['value' => 'x', 'label' => 'X']],
        ]);
        $this->assertEmpty($errors);
    }

    public function test_unknown_type_returns_error(): void
    {
        $errors = $this->validator->validate('mystery_type', null);
        $this->assertNotEmpty($errors);
    }
}
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec app php artisan test tests/Unit/Services/FieldSettingsValidatorTest.php`

Expected: FAIL — class not found.

- [ ] **Step 3: Create the validator**

Create `app/Services/FieldSettingsValidator.php`:

```php
<?php

namespace App\Services;

class FieldSettingsValidator
{
    public function __construct(private QuestionnaireFieldTypeRegistry $registry)
    {
    }

    /**
     * Validates the settings array shape for a given field type.
     * Returns an array of human-readable error strings; empty array means OK.
     *
     * @return array<string>
     */
    public function validate(string $type, ?array $settings): array
    {
        if (!$this->registry->isKnownType($type)) {
            return ["Unknown field type: {$type}"];
        }

        $required = $this->registry->definition($type)['required_settings'] ?? [];

        if (in_array('options', $required, true)) {
            return $this->validateOptions($settings);
        }

        return [];
    }

    private function validateOptions(?array $settings): array
    {
        if (!is_array($settings) || !isset($settings['options']) || !is_array($settings['options'])) {
            return ['settings.options is required and must be an array'];
        }

        $options = $settings['options'];
        if (count($options) < 1) {
            return ['settings.options must contain at least one entry'];
        }

        $errors = [];
        foreach ($options as $i => $option) {
            if (!is_array($option)) {
                $errors[] = "settings.options[{$i}] must be an object with value and label";
                continue;
            }
            if (!isset($option['value']) || !is_string($option['value']) || $option['value'] === '') {
                $errors[] = "settings.options[{$i}].value is required and must be a non-empty string";
            }
            if (!isset($option['label']) || !is_string($option['label']) || $option['label'] === '') {
                $errors[] = "settings.options[{$i}].label is required and must be a non-empty string";
            }
        }
        return $errors;
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Unit/Services/FieldSettingsValidatorTest.php`

Expected: PASS — 9 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/FieldSettingsValidator.php tests/Unit/Services/FieldSettingsValidatorTest.php
git commit -m "$(cat <<'EOF'
Add FieldSettingsValidator

Per-type settings validation. Currently enforces the options shape on
dropdown/multi_select/checkbox_group; other types accept any settings.
Returns string error array (empty = valid) so callers can compose into
422 responses.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 13: Phase 2 checkpoint

- [ ] **Step 1: Run all unit tests in the new namespace**

Run: `docker-compose exec app php artisan test --testsuite=Unit --filter=Questionnaire`

Expected: PASS — 4 test classes, ~36 tests total.

- [ ] **Step 2: Run all frontend tests**

Run: `docker-compose exec node npm run test:run`

Expected: PASS — including the new visibility.spec.js.

- [ ] **Step 3: Phase 2 complete. No commit needed.**

---

# Phase 3 — Template builder

Goal: build a working template CRUD with bulk-save backend and a Vue editor that can drag-and-drop fields, edit settings inline, and persist. End of phase: a band owner can build a wedding-day questionnaire end-to-end.

## Task 14: QuestionnairePolicy

**Files:**
- Create: `app/Policies/QuestionnairePolicy.php`
- Modify: `app/Providers/AuthServiceProvider.php` (register policy)

- [ ] **Step 1: Create the policy**

```php
<?php

namespace App\Policies;

use App\Models\Bands;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class QuestionnairePolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user, Bands $band): bool
    {
        return $user->canRead('questionnaires', $band->id);
    }

    public function view(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canRead('questionnaires', $questionnaire->band_id);
    }

    public function create(User $user, Bands $band): bool
    {
        return $user->canWrite('questionnaires', $band->id);
    }

    public function update(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canWrite('questionnaires', $questionnaire->band_id);
    }

    public function delete(User $user, Questionnaires $questionnaire): bool
    {
        return $user->canWrite('questionnaires', $questionnaire->band_id);
    }
}
```

- [ ] **Step 2: Register the policy**

In `app/Providers/AuthServiceProvider.php`, add to the `$policies` array:

```php
\App\Models\Questionnaires::class => \App\Policies\QuestionnairePolicy::class,
```

- [ ] **Step 3: Smoke-test policy resolution**

Run: `docker-compose exec app php artisan tinker --execute="echo Gate::getPolicyFor(\App\Models\Questionnaires::class) ? 'OK' : 'MISSING';"`

Expected: `OK`

- [ ] **Step 4: Commit**

```bash
git add app/Policies/QuestionnairePolicy.php app/Providers/AuthServiceProvider.php
git commit -m "$(cat <<'EOF'
Add QuestionnairePolicy

Gates view/update/delete on questionnaires by the band's read/write
permissions. Mirrors the EventsPolicy + canWrite pattern.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 15: StoreQuestionnaireRequest + UpdateQuestionnaireRequest

**Files:**
- Create: `app/Http/Requests/StoreQuestionnaireRequest.php`
- Create: `app/Http/Requests/UpdateQuestionnaireRequest.php`

- [ ] **Step 1: Create StoreQuestionnaireRequest**

Create `app/Http/Requests/StoreQuestionnaireRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $band = $this->route('band');
        return $this->user()->canWrite('questionnaires', $band->id);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
        ];
    }
}
```

- [ ] **Step 2: Create UpdateQuestionnaireRequest**

Create `app/Http/Requests/UpdateQuestionnaireRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuestionnaireRequest extends FormRequest
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        $questionnaire = $this->route('questionnaire');
        return $this->user()->canWrite('questionnaires', $questionnaire->band_id);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:120',
            'description' => 'nullable|string',
            'fields' => 'present|array',
            'fields.*.id' => 'nullable|integer',
            'fields.*.client_id' => 'required|string',
            'fields.*.type' => ['required', Rule::in($this->typeRegistry->knownTypes())],
            'fields.*.label' => 'required|string|max:255',
            'fields.*.help_text' => 'nullable|string',
            'fields.*.required' => 'boolean',
            'fields.*.position' => 'required|integer|min:0',
            'fields.*.settings' => 'nullable|array',
            'fields.*.visibility_rule' => 'nullable|array',
            'fields.*.visibility_rule.depends_on' => 'required_with:fields.*.visibility_rule|string',
            'fields.*.visibility_rule.operator' => [
                'required_with:fields.*.visibility_rule',
                Rule::in(['equals', 'not_equals', 'contains', 'empty', 'not_empty']),
            ],
            'fields.*.visibility_rule.value' => 'nullable',
            'fields.*.mapping_target' => ['nullable', Rule::in($this->mappingRegistry->keys())],
        ];
    }
}
```

- [ ] **Step 3: No standalone test for these — they'll be exercised by the controller's feature tests in Task 17. Just confirm they instantiate.**

Run: `docker-compose exec app php artisan tinker --execute="echo class_exists('App\\Http\\Requests\\StoreQuestionnaireRequest').','.class_exists('App\\Http\\Requests\\UpdateQuestionnaireRequest');"`

Expected: `1,1`

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/StoreQuestionnaireRequest.php app/Http/Requests/UpdateQuestionnaireRequest.php
git commit -m "$(cat <<'EOF'
Add Store/Update FormRequests for questionnaires

StoreQuestionnaireRequest validates name + description for create-from-dialog.
UpdateQuestionnaireRequest validates the bulk-save payload including nested
field validation and registry-backed type/mapping_target whitelists.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 16: QuestionnairesController (TDD)

**Files:**
- Create: `app/Http/Controllers/QuestionnairesController.php`
- Create: `routes/questionnaire.php` (replace stub)
- Create: `tests/Feature/Questionnaires/TemplateBuilderTest.php`

- [ ] **Step 1: Write the failing tests — Part 1 of test class**

Create `tests/Feature/Questionnaires/TemplateBuilderTest.php`:

```php
<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private User $member;
    private User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->outsider = User::factory()->create();

        $this->band->owners()->create(['user_id' => $this->owner->id]);
        $this->band->members()->create(['user_id' => $this->member->id]);
    }

    public function test_band_owner_can_view_index(): void
    {
        Questionnaires::factory()->count(2)->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)
            ->get(route('questionnaires.index', $this->band));

        $response->assertStatus(200);
        $response->assertInertia(fn ($a) => $a
            ->component('Questionnaires/Index')
            ->has('questionnaires', 2));
    }

    public function test_outsider_cannot_view_index(): void
    {
        $response = $this->actingAs($this->outsider)
            ->get(route('questionnaires.index', $this->band));

        $response->assertStatus(403);
    }

    public function test_band_owner_can_create_questionnaire(): void
    {
        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day', 'description' => 'Wedding details']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'name' => 'Wedding Day',
            'slug' => 'wedding-day',
        ]);
    }

    public function test_member_without_write_permission_cannot_create(): void
    {
        $response = $this->actingAs($this->member)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(403);
    }

    public function test_slug_uniqueness_scoped_to_band(): void
    {
        $otherBand = Bands::factory()->create();
        Questionnaires::factory()->create(['band_id' => $otherBand->id, 'name' => 'Wedding Day']);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'slug' => 'wedding-day',
        ]);
    }

    public function test_slug_uniqueness_within_same_band_appends_suffix(): void
    {
        Questionnaires::factory()->create(['band_id' => $this->band->id, 'name' => 'Wedding Day']);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.store', $this->band),
            ['name' => 'Wedding Day']
        );

        $response->assertStatus(302);
        $this->assertDatabaseHas('questionnaires', [
            'band_id' => $this->band->id,
            'slug' => 'wedding-day-2',
        ]);
    }

    public function test_band_owner_can_bulk_save_template_with_fields(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => 'Wedding Day Questionnaire',
            'description' => 'Updated',
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp-1',
                    'type' => 'header',
                    'label' => 'Bride and Groom',
                    'help_text' => null,
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'tmp-2',
                    'type' => 'short_text',
                    'label' => "Bride's Name",
                    'help_text' => 'Full name with spelling',
                    'required' => true,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(302);
        $this->assertSame(2, $template->fields()->count());
        $this->assertDatabaseHas('questionnaire_fields', [
            'questionnaire_id' => $template->id,
            'label' => "Bride's Name",
            'required' => true,
        ]);
    }

    public function test_bulk_save_resolves_visibility_rule_client_ids_to_field_ids(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'description' => $template->description,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'parent',
                    'type' => 'yes_no',
                    'label' => 'Wedding party?',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'child',
                    'type' => 'short_text',
                    'label' => 'How many?',
                    'required' => false,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => [
                        'depends_on' => 'parent', // client id reference
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(302);

        $parent = $template->fields()->where('label', 'Wedding party?')->first();
        $child = $template->fields()->where('label', 'How many?')->first();

        $this->assertNotNull($parent);
        $this->assertNotNull($child);
        $this->assertSame($parent->id, $child->visibility_rule['depends_on']);
    }

    public function test_bulk_save_rejects_forward_visibility_reference(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'description' => null,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'first',
                    'type' => 'short_text',
                    'label' => 'Refers ahead',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => [
                        'depends_on' => 'second', // forward reference
                        'operator' => 'equals',
                        'value' => 'yes',
                    ],
                    'mapping_target' => null,
                ],
                [
                    'id' => null,
                    'client_id' => 'second',
                    'type' => 'yes_no',
                    'label' => 'After',
                    'required' => false,
                    'position' => 20,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(422);
    }

    public function test_bulk_save_rejects_dropdown_with_no_options(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp',
                    'type' => 'dropdown',
                    'label' => 'Pick one',
                    'required' => false,
                    'position' => 10,
                    'settings' => null, // missing options
                    'visibility_rule' => null,
                    'mapping_target' => null,
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(422);
    }

    public function test_bulk_save_rejects_incompatible_mapping_target(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $payload = [
            'name' => $template->name,
            'fields' => [
                [
                    'id' => null,
                    'client_id' => 'tmp',
                    'type' => 'short_text', // wedding.onsite needs yes_no
                    'label' => 'Onsite?',
                    'required' => false,
                    'position' => 10,
                    'settings' => null,
                    'visibility_rule' => null,
                    'mapping_target' => 'wedding.onsite',
                ],
            ],
        ];

        $response = $this->actingAs($this->owner)->put(
            route('questionnaires.update', [$this->band, $template]),
            $payload
        );

        $response->assertStatus(422);
    }

    public function test_archive_marks_archived_at(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.archive', [$this->band, $template])
        );

        $response->assertStatus(302);
        $template->refresh();
        $this->assertNotNull($template->archived_at);
    }

    public function test_restore_clears_archived_at(): void
    {
        $template = Questionnaires::factory()->create([
            'band_id' => $this->band->id,
            'archived_at' => now(),
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('questionnaires.restore', [$this->band, $template])
        );

        $response->assertStatus(302);
        $template->refresh();
        $this->assertNull($template->archived_at);
    }

    public function test_destroy_blocked_when_template_has_been_sent(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
        \App\Models\QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $template->id,
        ]);

        $response = $this->actingAs($this->owner)->delete(
            route('questionnaires.destroy', [$this->band, $template])
        );

        $response->assertStatus(409);
        $this->assertDatabaseHas('questionnaires', ['id' => $template->id, 'deleted_at' => null]);
    }

    public function test_destroy_succeeds_for_unsent_template(): void
    {
        $template = Questionnaires::factory()->create(['band_id' => $this->band->id]);

        $response = $this->actingAs($this->owner)->delete(
            route('questionnaires.destroy', [$this->band, $template])
        );

        $response->assertStatus(302);
        $this->assertSoftDeleted('questionnaires', ['id' => $template->id]);
    }
}
```

- [ ] **Step 2: Run the failing tests**

Run: `docker-compose exec app php artisan test tests/Feature/Questionnaires/TemplateBuilderTest.php`

Expected: FAIL — controller/routes don't exist yet.

- [ ] **Step 3: Build the controller**

Create `app/Http/Controllers/QuestionnairesController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuestionnaireRequest;
use App\Http\Requests\UpdateQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Questionnaires;
use App\Services\FieldSettingsValidator;
use App\Services\QuestionnaireFieldTypeRegistry;
use App\Services\QuestionnaireMappingRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class QuestionnairesController extends Controller
{
    public function __construct(
        private QuestionnaireFieldTypeRegistry $typeRegistry,
        private QuestionnaireMappingRegistry $mappingRegistry,
        private FieldSettingsValidator $settingsValidator,
    ) {
    }

    public function index(Bands $band): Response
    {
        $this->authorize('viewAny', [Questionnaires::class, $band]);

        $questionnaires = $band->questionnaires()
            ->orderBy('archived_at')
            ->orderBy('name')
            ->withCount('instances')
            ->get();

        return Inertia::render('Questionnaires/Index', [
            'band' => $band->only(['id', 'name', 'site_name']),
            'questionnaires' => $questionnaires,
        ]);
    }

    public function store(StoreQuestionnaireRequest $request, Bands $band): RedirectResponse
    {
        $questionnaire = new Questionnaires([
            'description' => $request->input('description'),
        ]);
        $questionnaire->band_id = $band->id;
        $questionnaire->name = $request->input('name'); // triggers slug generation
        $questionnaire->save();

        return redirect()->route('questionnaires.edit', [$band, $questionnaire]);
    }

    public function edit(Bands $band, Questionnaires $questionnaire): Response
    {
        $this->authorize('view', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        return Inertia::render('Questionnaires/Edit', [
            'band' => $band->only(['id', 'name', 'site_name']),
            'questionnaire' => $questionnaire->only(['id', 'name', 'slug', 'description', 'archived_at']),
            'fields' => $questionnaire->fields,
            'fieldTypeCatalog' => $this->typeRegistry->catalog(),
            'mappingTargetCatalog' => $this->mappingRegistry->catalog(),
        ]);
    }

    public function update(UpdateQuestionnaireRequest $request, Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        abort_if($questionnaire->band_id !== $band->id, 404);
        $this->validateBulkSavePayload($request, $questionnaire);

        DB::transaction(function () use ($request, $questionnaire) {
            $questionnaire->name = $request->input('name');
            $questionnaire->description = $request->input('description');
            $questionnaire->save();

            $this->upsertFields($request->input('fields', []), $questionnaire);
        });

        return redirect()->route('questionnaires.edit', [$band, $questionnaire])
            ->with('success', 'Questionnaire saved.');
    }

    public function preview(Bands $band, Questionnaires $questionnaire): Response
    {
        $this->authorize('view', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        return Inertia::render('Questionnaires/Preview', [
            'band' => $band->only(['id', 'name']),
            'questionnaire' => $questionnaire,
            'fields' => $questionnaire->fields,
        ]);
    }

    public function archive(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('update', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        $questionnaire->update(['archived_at' => now()]);
        return back()->with('success', 'Archived.');
    }

    public function restore(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('update', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        $questionnaire->update(['archived_at' => null]);
        return back()->with('success', 'Restored.');
    }

    public function destroy(Bands $band, Questionnaires $questionnaire): RedirectResponse
    {
        $this->authorize('delete', $questionnaire);
        abort_if($questionnaire->band_id !== $band->id, 404);

        if ($questionnaire->instances()->exists()) {
            return back()->with('error', 'Cannot delete a template that has been sent. Archive it instead.')
                ->setStatusCode(409);
        }

        $questionnaire->delete();
        return redirect()->route('questionnaires.index', $band)->with('success', 'Deleted.');
    }

    /**
     * Combined custom validation: per-type settings, mapping-target compatibility,
     * forward-visibility check.
     */
    private function validateBulkSavePayload(Request $request, Questionnaires $questionnaire): void
    {
        $errors = [];
        $fields = $request->input('fields', []);

        // Position-by-client_id map for forward-reference detection
        $positionByClientId = [];
        foreach ($fields as $f) {
            $positionByClientId[$f['client_id']] = $f['position'] ?? PHP_INT_MAX;
        }

        foreach ($fields as $i => $f) {
            $type = $f['type'] ?? null;
            $settings = $f['settings'] ?? null;
            $rule = $f['visibility_rule'] ?? null;
            $mapping = $f['mapping_target'] ?? null;

            // Per-type settings shape
            $settingsErrors = $this->settingsValidator->validate($type, $settings);
            foreach ($settingsErrors as $err) {
                $errors["fields.{$i}.settings"][] = $err;
            }

            // Mapping-target compatibility
            if (!empty($mapping)) {
                $compatible = $this->mappingRegistry->compatibleFieldTypes($mapping);
                if (!in_array($type, $compatible, true)) {
                    $errors["fields.{$i}.mapping_target"][] =
                        "Field type '{$type}' is not compatible with mapping target '{$mapping}'.";
                }
            }

            // Forward-reference check
            if (!empty($rule['depends_on'])) {
                $thisPos = $f['position'] ?? PHP_INT_MAX;
                $depPos = $positionByClientId[$rule['depends_on']] ?? null;
                if ($depPos === null) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule references unknown field '{$rule['depends_on']}'.";
                } elseif ($depPos >= $thisPos) {
                    $errors["fields.{$i}.visibility_rule.depends_on"][] =
                        "Visibility rule must reference a field that comes earlier in the questionnaire.";
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Diff existing fields vs payload by id, upsert present, delete missing.
     * Two-pass: first upsert, then rewrite visibility_rule depends_on to use
     * permanent ids resolved from client_ids.
     */
    private function upsertFields(array $payloadFields, Questionnaires $questionnaire): void
    {
        $payloadIds = collect($payloadFields)->pluck('id')->filter()->all();
        $questionnaire->fields()->whereNotIn('id', $payloadIds)->delete();

        $clientIdToPersistedId = [];

        foreach ($payloadFields as $f) {
            $attributes = [
                'questionnaire_id' => $questionnaire->id,
                'type' => $f['type'],
                'label' => $f['label'],
                'help_text' => $f['help_text'] ?? null,
                'required' => $f['required'] ?? false,
                'position' => $f['position'],
                'settings' => $f['settings'] ?? null,
                'mapping_target' => $f['mapping_target'] ?? null,
                // visibility_rule rewritten in second pass
                'visibility_rule' => null,
            ];

            if (!empty($f['id'])) {
                $field = $questionnaire->fields()->find($f['id']);
                if ($field) {
                    $field->update($attributes);
                    $clientIdToPersistedId[$f['client_id']] = $field->id;
                    continue;
                }
            }

            $created = $questionnaire->fields()->create($attributes);
            $clientIdToPersistedId[$f['client_id']] = $created->id;
        }

        // Second pass: rewrite visibility_rule.depends_on
        foreach ($payloadFields as $f) {
            if (empty($f['visibility_rule']['depends_on'])) {
                continue;
            }
            $persistedId = $clientIdToPersistedId[$f['client_id']];
            $depClientId = $f['visibility_rule']['depends_on'];
            $depPersistedId = $clientIdToPersistedId[$depClientId] ?? null;
            if ($depPersistedId === null) {
                continue;
            }

            $rewritten = [
                'depends_on' => $depPersistedId,
                'operator' => $f['visibility_rule']['operator'],
                'value' => $f['visibility_rule']['value'] ?? null,
            ];
            $questionnaire->fields()->where('id', $persistedId)->update([
                'visibility_rule' => json_encode($rewritten),
            ]);
        }
    }
}
```

- [ ] **Step 4: Replace `routes/questionnaire.php`**

Replace the empty stub with:

```php
<?php

use App\Http\Controllers\QuestionnairesController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('bands/{band}/questionnaires')->group(function () {
    Route::get('/', [QuestionnairesController::class, 'index'])->name('questionnaires.index');
    Route::post('/', [QuestionnairesController::class, 'store'])->name('questionnaires.store');
    Route::get('/{questionnaire:slug}/edit', [QuestionnairesController::class, 'edit'])->name('questionnaires.edit');
    Route::put('/{questionnaire:slug}', [QuestionnairesController::class, 'update'])->name('questionnaires.update');
    Route::get('/{questionnaire:slug}/preview', [QuestionnairesController::class, 'preview'])->name('questionnaires.preview');
    Route::post('/{questionnaire:slug}/archive', [QuestionnairesController::class, 'archive'])->name('questionnaires.archive');
    Route::post('/{questionnaire:slug}/restore', [QuestionnairesController::class, 'restore'])->name('questionnaires.restore');
    Route::delete('/{questionnaire:slug}', [QuestionnairesController::class, 'destroy'])->name('questionnaires.destroy');
});
```

- [ ] **Step 5: Regenerate Ziggy**

Run: `docker-compose exec app php artisan ziggy:generate`

- [ ] **Step 6: Run the failing tests again**

Run: `docker-compose exec app php artisan test tests/Feature/Questionnaires/TemplateBuilderTest.php`

Expected: PASS — all 13 tests.

If a test fails, fix the controller/policy and re-run. Common issues:
- Missing `withCount('instances')` on the model — add the relationship if needed
- Policy authorization order — `authorize` is called before `update()` body
- The "destroy_blocked_when_sent" test relies on `QuestionnaireInstances::factory()->create()` filling a `booking_id`/`recipient_contact_id`/`sent_by_user_id` via cascading factories. If it errors on missing FK, ensure the factories from Phase 1 are correct.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/QuestionnairesController.php routes/questionnaire.php tests/Feature/Questionnaires/ public/build 2>/dev/null
git add -A
git commit -m "$(cat <<'EOF'
Add QuestionnairesController with CRUD, archive, and bulk-save

Bulk save handles the entire fields array in one transaction: diff against
existing rows, upsert by id, two-pass visibility_rule resolution from
client_id to persisted id. Per-type settings, mapping-target compatibility,
and forward-reference checks are validated before the DB write.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 17: Builder UX scaffolding — Index page

**Files:**
- Create: `resources/js/Pages/Questionnaires/Index.vue`

- [ ] **Step 1: Create Index.vue**

Create `resources/js/Pages/Questionnaires/Index.vue`:

```vue
<template>
  <Layout>
    <template #header>
      <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50 leading-tight">
        Questionnaires — {{ band.name }}
      </h2>
    </template>

    <Container>
      <div class="card bg-white dark:bg-slate-800 rounded-xl shadow p-4">
        <Toolbar class="p-mb-4 border-b-2 border-gray-100 dark:border-slate-700">
          <template #start>
            <Button
              icon="pi pi-plus"
              label="New Questionnaire"
              class="mr-2"
              severity="secondary"
              @click="dialogOpen = true"
            />
          </template>
        </Toolbar>

        <DataTable
          :value="questionnaires"
          striped-rows
          row-hover
          responsive-layout="scroll"
          @row-click="(e) => visitEditor(e.data)"
        >
          <Column field="name" header="Name" sortable />
          <Column field="instances_count" header="Times sent" sortable />
          <Column header="Status">
            <template #body="{ data }">
              <span v-if="data.archived_at" class="text-xs uppercase text-gray-500">Archived</span>
              <span v-else class="text-xs uppercase text-emerald-600">Active</span>
            </template>
          </Column>
          <Column header="Actions">
            <template #body="{ data }">
              <Button
                icon="pi pi-pencil"
                text
                @click.stop="visitEditor(data)"
              />
              <Button
                v-if="!data.archived_at"
                icon="pi pi-inbox"
                text
                @click.stop="archive(data)"
              />
              <Button
                v-else
                icon="pi pi-undo"
                text
                @click.stop="restore(data)"
              />
            </template>
          </Column>
          <template #empty>
            No questionnaires yet. Click "New Questionnaire" to create your first one.
          </template>
        </DataTable>
      </div>

      <Dialog
        v-model:visible="dialogOpen"
        :style="{ width: '450px' }"
        header="New Questionnaire"
        modal
      >
        <div class="p-fluid space-y-3">
          <div>
            <label for="name" class="block text-sm">Name</label>
            <InputText id="name" v-model="form.name" autofocus />
            <small v-if="errors.name" class="text-red-600">{{ errors.name }}</small>
          </div>
          <div>
            <label for="description" class="block text-sm">Description (optional)</label>
            <Textarea id="description" v-model="form.description" rows="3" />
          </div>
        </div>
        <template #footer>
          <Button label="Cancel" text @click="dialogOpen = false" />
          <Button :label="saving ? 'Creating…' : 'Create'" :disabled="saving" @click="save" />
        </template>
      </Dialog>
    </Container>
  </Layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import Toolbar from 'primevue/toolbar'
import DataTable from 'primevue/datatable'
import Column from 'primevue/column'
import Dialog from 'primevue/dialog'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'

const props = defineProps({
  band: { type: Object, required: true },
  questionnaires: { type: Array, default: () => [] },
})

const dialogOpen = ref(false)
const saving = ref(false)
const errors = reactive({})
const form = reactive({ name: '', description: '' })

function visitEditor(data) {
  router.visit(route('questionnaires.edit', { band: props.band.id, questionnaire: data.slug }))
}

function save() {
  saving.value = true
  router.post(
    route('questionnaires.store', props.band.id),
    { name: form.name, description: form.description },
    {
      preserveState: true,
      onError: (e) => {
        Object.assign(errors, e)
        saving.value = false
      },
      onSuccess: () => {
        dialogOpen.value = false
        saving.value = false
        form.name = ''
        form.description = ''
      },
    }
  )
}

function archive(data) {
  router.post(route('questionnaires.archive', { band: props.band.id, questionnaire: data.slug }))
}

function restore(data) {
  router.post(route('questionnaires.restore', { band: props.band.id, questionnaire: data.slug }))
}
</script>
```

- [ ] **Step 2: Confirm Vite picks up the new file**

Run: `docker-compose exec node npm run build`

Expected: build completes without errors. (Run dev mode in your normal workflow; this is just a sanity check.)

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Questionnaires/Index.vue
git commit -m "$(cat <<'EOF'
Add Questionnaires/Index.vue (template list + create dialog)

DataTable lists templates with name, sent count, archived status, and
inline actions (edit, archive/restore). New-template dialog calls
questionnaires.store with name + description.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 18: Builder UX — Edit page (drag-and-drop, settings panel)

**Files:**
- Create: `resources/js/Pages/Questionnaires/Edit.vue`
- Create: `resources/js/Pages/Questionnaires/Components/FieldEditor.vue`

This is the heaviest UI task. Test by manual interaction at the end of the task — automated Vue testing for drag-and-drop is not in scope.

- [ ] **Step 1: Create FieldEditor.vue**

Create `resources/js/Pages/Questionnaires/Components/FieldEditor.vue`:

```vue
<template>
  <div
    class="border rounded-lg p-3 mb-2 bg-white dark:bg-slate-800 transition-shadow"
    :class="{ 'shadow-md ring-2 ring-blue-400': selected }"
  >
    <div class="flex justify-between items-start gap-3">
      <div class="flex-1 min-w-0">
        <Dropdown
          v-if="isInputType"
          v-model="local.type"
          :options="typeOptions"
          option-label="label"
          option-value="type"
          placeholder="Field type"
          class="mb-2 text-sm"
          @change="emitChange"
        />
        <InputText
          v-model="local.label"
          :placeholder="isInputType ? 'Question label' : 'Header text'"
          class="w-full mb-1"
          @input="emitChange"
        />
        <InputText
          v-if="isInputType"
          v-model="local.help_text"
          placeholder="Help text (optional)"
          class="w-full text-sm text-gray-500 mb-2"
          @input="emitChange"
        />
        <div v-if="local.type === 'short_text'" class="text-xs text-gray-400 italic">Short answer (preview)</div>
        <div v-else-if="local.type === 'long_text'" class="text-xs text-gray-400 italic">Long answer (preview)</div>
        <div v-else-if="local.type === 'date'" class="text-xs text-gray-400 italic">Date picker (preview)</div>
        <div v-else-if="local.type === 'time'" class="text-xs text-gray-400 italic">Time picker (preview)</div>
        <div v-else-if="local.type === 'email'" class="text-xs text-gray-400 italic">Email (preview)</div>
        <div v-else-if="local.type === 'phone'" class="text-xs text-gray-400 italic">Phone (preview)</div>
        <div v-else-if="local.type === 'yes_no'" class="text-xs text-gray-400 italic">Yes / No (preview)</div>
        <div v-else-if="['dropdown','multi_select','checkbox_group'].includes(local.type)">
          <div class="text-xs text-gray-500 mb-1">Options</div>
          <div
            v-for="(opt, i) in localOptions"
            :key="i"
            class="flex gap-2 mb-1"
          >
            <InputText v-model="opt.label" placeholder="Label" class="text-sm" @input="syncOptions" />
            <InputText v-model="opt.value" placeholder="Value" class="text-sm" @input="syncOptions" />
            <Button icon="pi pi-times" text @click="removeOption(i)" />
          </div>
          <Button icon="pi pi-plus" label="Add option" text @click="addOption" />
        </div>

        <div v-if="selected && isInputType" class="mt-3 pt-3 border-t border-gray-100 dark:border-slate-700 space-y-2">
          <div class="flex items-center gap-2">
            <Checkbox v-model="local.required" binary @change="emitChange" />
            <label class="text-sm">Required</label>
          </div>

          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1">Show this field if…</label>
            <div class="flex gap-2 items-center">
              <Dropdown
                v-model="visibilityDependsOn"
                :options="earlierFieldOptions"
                option-label="label"
                option-value="client_id"
                placeholder="(always show)"
                class="text-sm flex-1"
                show-clear
                @change="updateVisibility"
              />
              <Dropdown
                v-if="visibilityDependsOn"
                v-model="visibilityOperator"
                :options="operatorOptions"
                option-label="label"
                option-value="value"
                class="text-sm w-32"
                @change="updateVisibility"
              />
              <InputText
                v-if="visibilityDependsOn && needsValue"
                v-model="visibilityValue"
                placeholder="value"
                class="text-sm w-32"
                @input="updateVisibility"
              />
            </div>
          </div>

          <div>
            <label class="block text-xs text-gray-500 uppercase mb-1">Maps to event</label>
            <Dropdown
              v-model="local.mapping_target"
              :options="filteredMappingOptions"
              option-label="label"
              option-value="key"
              placeholder="(no mapping)"
              show-clear
              class="text-sm"
              @change="emitChange"
            />
          </div>
        </div>
      </div>

      <div class="flex flex-col gap-1 flex-shrink-0">
        <Button icon="pi pi-bars" text class="cursor-grab handle" />
        <Button icon="pi pi-copy" text @click="$emit('duplicate')" />
        <Button icon="pi pi-trash" text severity="danger" @click="$emit('delete')" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, reactive } from 'vue'
import InputText from 'primevue/inputtext'
import Dropdown from 'primevue/dropdown'
import Button from 'primevue/button'
import Checkbox from 'primevue/checkbox'

const props = defineProps({
  modelValue: { type: Object, required: true },
  selected: { type: Boolean, default: false },
  earlierFields: { type: Array, default: () => [] },
  fieldTypeCatalog: { type: Array, required: true },
  mappingTargetCatalog: { type: Array, required: true },
})
const emit = defineEmits(['update:modelValue', 'duplicate', 'delete'])

const local = reactive({ ...props.modelValue })
const localOptions = ref(local.settings?.options ? [...local.settings.options] : [])

const operatorOptions = [
  { label: 'equals', value: 'equals' },
  { label: 'does not equal', value: 'not_equals' },
  { label: 'contains', value: 'contains' },
  { label: 'is empty', value: 'empty' },
  { label: 'is not empty', value: 'not_empty' },
]

const visibilityDependsOn = ref(local.visibility_rule?.depends_on ?? null)
const visibilityOperator = ref(local.visibility_rule?.operator ?? 'equals')
const visibilityValue = ref(local.visibility_rule?.value ?? '')

const isInputType = computed(() => {
  const def = props.fieldTypeCatalog.find(t => t.type === local.type)
  return def?.is_input ?? true
})

const typeOptions = computed(() => props.fieldTypeCatalog)

const earlierFieldOptions = computed(() =>
  props.earlierFields.map(f => ({ client_id: f.client_id, label: f.label || '(unnamed)' }))
)

const filteredMappingOptions = computed(() =>
  props.mappingTargetCatalog.filter(m => m.compatible_field_types.includes(local.type))
)

const needsValue = computed(() => !['empty', 'not_empty'].includes(visibilityOperator.value))

function emitChange() {
  emit('update:modelValue', { ...local })
}

function syncOptions() {
  local.settings = { ...(local.settings || {}), options: [...localOptions.value] }
  emitChange()
}

function addOption() {
  localOptions.value.push({ value: '', label: '' })
  syncOptions()
}

function removeOption(i) {
  localOptions.value.splice(i, 1)
  syncOptions()
}

function updateVisibility() {
  if (!visibilityDependsOn.value) {
    local.visibility_rule = null
  } else {
    local.visibility_rule = {
      depends_on: visibilityDependsOn.value,
      operator: visibilityOperator.value,
      value: needsValue.value ? visibilityValue.value : null,
    }
  }
  emitChange()
}

watch(() => props.modelValue, (val) => {
  Object.assign(local, val)
  if (val.settings?.options) {
    localOptions.value = [...val.settings.options]
  }
}, { deep: true })
</script>
```

- [ ] **Step 2: Create Edit.vue**

Create `resources/js/Pages/Questionnaires/Edit.vue`:

```vue
<template>
  <Layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50">
          Edit: {{ form.name }}
        </h2>
        <div class="flex gap-2">
          <Link :href="route('questionnaires.preview', { band: band.id, questionnaire: questionnaire.slug })">
            <Button label="Preview" outlined icon="pi pi-eye" />
          </Link>
          <Button
            :label="saving ? 'Saving…' : 'Save'"
            :disabled="saving || !isDirty"
            icon="pi pi-save"
            @click="save"
          />
        </div>
      </div>
    </template>

    <Container>
      <div class="card bg-white dark:bg-slate-800 rounded-xl shadow p-4 mb-4">
        <label class="block text-sm uppercase text-gray-500 mb-1">Name</label>
        <InputText v-model="form.name" class="w-full mb-3" @input="markDirty" />
        <label class="block text-sm uppercase text-gray-500 mb-1">Description</label>
        <Textarea v-model="form.description" rows="2" class="w-full" @input="markDirty" />
      </div>

      <div class="card bg-white dark:bg-slate-800 rounded-xl shadow p-4">
        <h3 class="text-lg font-semibold mb-3">Fields</h3>
        <draggable
          v-model="form.fields"
          item-key="client_id"
          handle=".handle"
          @end="markDirty"
        >
          <template #item="{ element, index }">
            <FieldEditor
              :model-value="element"
              :selected="selectedIdx === index"
              :earlier-fields="form.fields.slice(0, index)"
              :field-type-catalog="fieldTypeCatalog"
              :mapping-target-catalog="mappingTargetCatalog"
              @update:model-value="updateField(index, $event)"
              @duplicate="duplicateField(index)"
              @delete="deleteField(index)"
              @click="selectedIdx = index"
            />
          </template>
        </draggable>

        <Button
          label="Add field"
          icon="pi pi-plus"
          outlined
          class="mt-3"
          @click="addField"
        />
      </div>
    </Container>
  </Layout>
</template>

<script setup>
import { ref, reactive, computed, onBeforeMount } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import draggable from 'vuedraggable'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Button from 'primevue/button'
import FieldEditor from './Components/FieldEditor.vue'

const props = defineProps({
  band: Object,
  questionnaire: Object,
  fields: { type: Array, default: () => [] },
  fieldTypeCatalog: { type: Array, required: true },
  mappingTargetCatalog: { type: Array, required: true },
})

const form = reactive({
  name: props.questionnaire.name,
  description: props.questionnaire.description ?? '',
  fields: props.fields.map((f, i) => ({
    ...f,
    client_id: `id-${f.id}`,
    position: (i + 1) * 10,
  })),
})

const selectedIdx = ref(null)
const saving = ref(false)
const isDirty = ref(false)

function markDirty() {
  isDirty.value = true
}

function nextClientId() {
  return `tmp-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`
}

function addField() {
  form.fields.push({
    id: null,
    client_id: nextClientId(),
    type: 'short_text',
    label: '',
    help_text: '',
    required: false,
    position: (form.fields.length + 1) * 10,
    settings: null,
    visibility_rule: null,
    mapping_target: null,
  })
  selectedIdx.value = form.fields.length - 1
  markDirty()
}

function updateField(idx, value) {
  form.fields[idx] = value
  markDirty()
}

function duplicateField(idx) {
  const copy = JSON.parse(JSON.stringify(form.fields[idx]))
  copy.id = null
  copy.client_id = nextClientId()
  form.fields.splice(idx + 1, 0, copy)
  markDirty()
}

function deleteField(idx) {
  form.fields.splice(idx, 1)
  if (selectedIdx.value === idx) selectedIdx.value = null
  markDirty()
}

function save() {
  saving.value = true
  // recompute positions with gaps
  form.fields.forEach((f, i) => { f.position = (i + 1) * 10 })

  router.put(
    route('questionnaires.update', { band: props.band.id, questionnaire: props.questionnaire.slug }),
    {
      name: form.name,
      description: form.description,
      fields: form.fields,
    },
    {
      preserveScroll: true,
      onSuccess: () => {
        saving.value = false
        isDirty.value = false
      },
      onError: () => { saving.value = false },
    }
  )
}

onBeforeMount(() => {
  window.addEventListener('beforeunload', (e) => {
    if (isDirty.value) {
      e.preventDefault()
      e.returnValue = ''
    }
  })
})
</script>
```

- [ ] **Step 3: Build the frontend**

Run: `docker-compose exec node npm run build`

Expected: PASS without errors.

- [ ] **Step 4: Manual smoke test**

Run dev server in another terminal: `docker-compose exec node npm run dev`

Then in a browser:
1. Sign in as a band owner.
2. Navigate to `/bands/{your-band-id}/questionnaires`.
3. Click "New Questionnaire", give it a name, click Create. Should redirect to the editor.
4. Click "Add field". Should appear at the bottom.
5. Click a field — settings should expand inline.
6. Change type to "Dropdown". Add 2 options.
7. Add another field, set it to "Short text", click "Show this field if…", pick the dropdown, set operator to "equals" and value to one of the options.
8. Drag fields to reorder.
9. Click Save. Should round-trip with no errors. Refresh — fields should remain in order, with the visibility rule preserved (depends_on now resolves to a numeric id).

If anything's broken, fix the Vue components and continue. The backend tests in Task 16 should still pass.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Questionnaires/
git commit -m "$(cat <<'EOF'
Add Questionnaires/Edit.vue with drag-and-drop builder

Three-pane Google-Forms-style editor: inline settings expand on selection,
floating toolbar (drag/duplicate/delete), bulk-save on click. Visibility
rule editor only lists fields earlier in order. Mapping-target dropdown
filtered by compatible field types from the registry.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 19: Preview view + nav integration

**Files:**
- Create: `resources/js/Pages/Questionnaires/Preview.vue`
- Modify: `resources/js/config/navigation.js`

- [ ] **Step 1: Create Preview.vue**

Create `resources/js/Pages/Questionnaires/Preview.vue`:

```vue
<template>
  <Layout>
    <template #header>
      <div class="flex justify-between items-center">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-50">
          Preview: {{ questionnaire.name }}
        </h2>
        <Link :href="route('questionnaires.edit', { band: band.id, questionnaire: questionnaire.slug })">
          <Button label="Back to editor" outlined icon="pi pi-arrow-left" />
        </Link>
      </div>
    </template>

    <Container>
      <div class="max-w-2xl mx-auto bg-white dark:bg-slate-800 rounded-xl shadow p-6">
        <h2 class="text-2xl font-bold mb-2">{{ questionnaire.name }}</h2>
        <p v-if="questionnaire.description" class="text-gray-600 dark:text-gray-300 mb-6">
          {{ questionnaire.description }}
        </p>

        <div v-for="field in fields" :key="field.id" class="mb-5">
          <h3 v-if="field.type === 'header'" class="text-xl font-semibold mt-6 mb-2 border-b pb-1">
            {{ field.label }}
          </h3>
          <p v-else-if="field.type === 'instructions'" class="text-sm text-gray-600 italic">
            {{ field.label }}
          </p>
          <div v-else>
            <label class="block font-medium mb-1">
              {{ field.label }}
              <span v-if="field.required" class="text-red-600">*</span>
            </label>
            <p v-if="field.help_text" class="text-xs text-gray-500 mb-1">{{ field.help_text }}</p>
            <InputText v-if="field.type === 'short_text'" disabled placeholder="Short answer" class="w-full" />
            <Textarea v-else-if="field.type === 'long_text'" disabled placeholder="Long answer" rows="3" class="w-full" />
            <InputText v-else-if="field.type === 'date'" type="date" disabled class="w-full" />
            <InputText v-else-if="field.type === 'time'" type="time" disabled class="w-full" />
            <InputText v-else-if="field.type === 'email'" type="email" disabled placeholder="email@example.com" class="w-full" />
            <InputText v-else-if="field.type === 'phone'" disabled placeholder="555-0123" class="w-full" />
            <Dropdown v-else-if="field.type === 'dropdown'" disabled :options="field.settings?.options ?? []" option-label="label" class="w-full" />
            <MultiSelect v-else-if="field.type === 'multi_select'" disabled :options="field.settings?.options ?? []" option-label="label" class="w-full" />
            <div v-else-if="field.type === 'checkbox_group'">
              <div v-for="opt in (field.settings?.options ?? [])" :key="opt.value" class="flex items-center gap-2">
                <Checkbox disabled binary />
                <label>{{ opt.label }}</label>
              </div>
            </div>
            <div v-else-if="field.type === 'yes_no'" class="flex gap-3">
              <RadioButton disabled value="yes" /><label>Yes</label>
              <RadioButton disabled value="no" /><label>No</label>
            </div>
          </div>
        </div>
      </div>
    </Container>
  </Layout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import InputText from 'primevue/inputtext'
import Textarea from 'primevue/textarea'
import Dropdown from 'primevue/dropdown'
import MultiSelect from 'primevue/multiselect'
import Checkbox from 'primevue/checkbox'
import RadioButton from 'primevue/radiobutton'
import Button from 'primevue/button'

defineProps({
  band: Object,
  questionnaire: Object,
  fields: { type: Array, default: () => [] },
})
</script>
```

- [ ] **Step 2: Add nav entry**

In `resources/js/config/navigation.js`, find the `assets` group (or create a new group if it makes more sense). Add:

```javascript
{
  label: 'Questionnaires',
  routeName: 'questionnaires.index',
  permission: 'Questionnaires',
  activeMatch: (route) => route.includes('questionnaires'),
  needsBand: true, // (if other routes that need bandId use this convention; otherwise drop)
},
```

If the `needsBand: true` convention isn't used elsewhere, replicate whatever pattern existing band-scoped nav items use. Look at how "Charts" (in `assets`) is wired up — copy that pattern. Permission key is `Questionnaires` (matching `BandResource::Questionnaires->label()`).

- [ ] **Step 3: Build and manual-test**

Run: `docker-compose exec node npm run build`

Visit `/bands/{id}/questionnaires/{slug}/preview` in a browser. Confirm the form renders read-only with all field types visible.

Confirm the nav item appears in the appropriate group and links to the index page.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Questionnaires/Preview.vue resources/js/config/navigation.js
git commit -m "$(cat <<'EOF'
Add Questionnaires/Preview.vue and nav entry

Preview renders a read-only client-eye view of the template (all 12 field
types). Nav entry added so band owners can reach the index from the global
menu.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 20: Phase 3 checkpoint

- [ ] **Step 1: Run all backend tests**

Run: `docker-compose exec app php artisan test --parallel --processes=4`

Expected: PASS — all tests including the 13 in `TemplateBuilderTest`.

- [ ] **Step 2: Run all frontend tests**

Run: `docker-compose exec node npm run test:run`

Expected: PASS

- [ ] **Step 3: Confirm Vite builds clean**

Run: `docker-compose exec node npm run build`

Expected: PASS

- [ ] **Step 4: Phase 3 done.**

---

# Phase 4 — Sending and instance lifecycle

Goal: snapshot a template into an instance, attach it to a booking, email the recipient. Lock/unlock and resend. End of phase: a band owner can send a template to a client and a feature test confirms a working notification fires.

## Task 21: QuestionnaireSnapshotService (TDD)

**Files:**
- Create: `app/Services/QuestionnaireSnapshotService.php`
- Create: `tests/Unit/Services/QuestionnaireSnapshotServiceTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Services/QuestionnaireSnapshotServiceTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireFields;
use App\Models\Questionnaires;
use App\Models\User;
use App\Services\QuestionnaireSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuestionnaireSnapshotServiceTest extends TestCase
{
    use RefreshDatabase;

    private QuestionnaireSnapshotService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new QuestionnaireSnapshotService();
    }

    public function test_snapshot_copies_template_fields_to_instance(): void
    {
        $template = Questionnaires::factory()->create(['name' => 'Wedding']);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'header',
            'label' => 'Section A',
            'position' => 10,
        ]);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => "Bride's Name",
            'required' => true,
            'position' => 20,
        ]);

        $booking = Bookings::factory()->create();
        $contact = Contacts::factory()->create();
        $user = User::factory()->create();

        $instance = $this->service->snapshot($template, $booking, $contact, $user);

        $this->assertSame('Wedding', $instance->name);
        $this->assertSame($booking->id, $instance->booking_id);
        $this->assertSame($contact->id, $instance->recipient_contact_id);
        $this->assertSame($user->id, $instance->sent_by_user_id);
        $this->assertSame('sent', $instance->status);
        $this->assertSame(2, $instance->fields()->count());

        $brideField = $instance->fields()->where('label', "Bride's Name")->first();
        $this->assertSame('short_text', $brideField->type);
        $this->assertTrue($brideField->required);
    }

    public function test_snapshot_rewrites_visibility_rule_to_new_field_ids(): void
    {
        $template = Questionnaires::factory()->create();

        $parent = QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'yes_no',
            'label' => 'Have a wedding party?',
            'position' => 10,
        ]);
        QuestionnaireFields::factory()->create([
            'questionnaire_id' => $template->id,
            'type' => 'short_text',
            'label' => 'How many people?',
            'position' => 20,
            'visibility_rule' => [
                'depends_on' => $parent->id,
                'operator' => 'equals',
                'value' => 'yes',
            ],
        ]);

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $newParent = $instance->fields()->where('label', 'Have a wedding party?')->first();
        $newChild = $instance->fields()->where('label', 'How many people?')->first();

        $this->assertNotSame($parent->id, $newParent->id, 'Snapshot should produce new ids');
        $this->assertSame($newParent->id, $newChild->visibility_rule['depends_on']);
    }

    public function test_snapshot_preserves_position_order(): void
    {
        $template = Questionnaires::factory()->create();
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 30, 'label' => 'Third']);
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 10, 'label' => 'First']);
        QuestionnaireFields::factory()->create(['questionnaire_id' => $template->id, 'position' => 20, 'label' => 'Second']);

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $labels = $instance->fields()->orderBy('position')->pluck('label')->all();
        $this->assertSame(['First', 'Second', 'Third'], $labels);
    }

    public function test_snapshot_handles_template_with_no_fields(): void
    {
        $template = Questionnaires::factory()->create();

        $instance = $this->service->snapshot(
            $template,
            Bookings::factory()->create(),
            Contacts::factory()->create(),
            User::factory()->create()
        );

        $this->assertSame(0, $instance->fields()->count());
    }
}
```

- [ ] **Step 2: Run failing tests**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireSnapshotServiceTest.php`

Expected: FAIL — service class missing.

- [ ] **Step 3: Create the service**

Create `app/Services/QuestionnaireSnapshotService.php`:

```php
<?php

namespace App\Services;

use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstanceFields;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuestionnaireSnapshotService
{
    public function snapshot(
        Questionnaires $template,
        Bookings $booking,
        Contacts $recipient,
        User $sentByUser,
    ): QuestionnaireInstances {
        return DB::transaction(function () use ($template, $booking, $recipient, $sentByUser) {
            $instance = QuestionnaireInstances::create([
                'questionnaire_id' => $template->id,
                'booking_id' => $booking->id,
                'recipient_contact_id' => $recipient->id,
                'sent_by_user_id' => $sentByUser->id,
                'name' => $template->name,
                'description' => $template->description,
                'status' => QuestionnaireInstances::STATUS_SENT,
                'sent_at' => now(),
            ]);

            $idMap = $this->copyFields($template, $instance);
            $this->rewriteVisibilityRules($instance, $idMap);

            return $instance->fresh('fields');
        });
    }

    /** @return array<int,int> oldFieldId => newFieldId */
    private function copyFields(Questionnaires $template, QuestionnaireInstances $instance): array
    {
        $idMap = [];
        foreach ($template->fields()->orderBy('position')->get() as $sourceField) {
            $copy = QuestionnaireInstanceFields::create([
                'instance_id' => $instance->id,
                'source_field_id' => $sourceField->id,
                'type' => $sourceField->type,
                'label' => $sourceField->label,
                'help_text' => $sourceField->help_text,
                'required' => $sourceField->required,
                'position' => $sourceField->position,
                'settings' => $sourceField->settings,
                'visibility_rule' => $sourceField->visibility_rule, // rewritten in second pass
                'mapping_target' => $sourceField->mapping_target,
            ]);
            $idMap[$sourceField->id] = $copy->id;
        }
        return $idMap;
    }

    private function rewriteVisibilityRules(QuestionnaireInstances $instance, array $idMap): void
    {
        foreach ($instance->fields()->whereNotNull('visibility_rule')->get() as $field) {
            $rule = $field->visibility_rule;
            $oldDep = $rule['depends_on'] ?? null;
            if ($oldDep === null || !isset($idMap[$oldDep])) {
                continue;
            }
            $rule['depends_on'] = $idMap[$oldDep];
            $field->visibility_rule = $rule;
            $field->save();
        }
    }
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Unit/Services/QuestionnaireSnapshotServiceTest.php`

Expected: PASS — 4 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/QuestionnaireSnapshotService.php tests/Unit/Services/QuestionnaireSnapshotServiceTest.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireSnapshotService

Snapshots a template into a new instance with copied fields and
re-pointed visibility rules. Wrapped in a DB transaction so partial
snapshots can't leak.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 22: Notifications

**Files:**
- Create: `app/Notifications/QuestionnaireSent.php`
- Create: `app/Notifications/QuestionnaireSubmitted.php`

- [ ] **Step 1: Create QuestionnaireSent**

Create `app/Notifications/QuestionnaireSent.php`:

```php
<?php

namespace App\Notifications;

use App\Models\QuestionnaireInstances;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestionnaireSent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public QuestionnaireInstances $instance)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $band = $this->instance->booking->band;
        $url = route('portal.booking.questionnaire.show', [
            'booking' => $this->instance->booking_id,
            'instance' => $this->instance->id,
        ]);

        $mail = (new MailMessage())
            ->subject($band->name . ': Please complete the ' . $this->instance->name)
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line($band->name . ' has sent you a questionnaire to complete: ' . $this->instance->name)
            ->action('Open Questionnaire', $url)
            ->line('You can save your progress as you go and return to finish later.');

        if ($band->email) {
            $mail->from($band->email, $band->name);
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'questionnaire_name' => $this->instance->name,
            'booking_id' => $this->instance->booking_id,
        ];
    }
}
```

- [ ] **Step 2: Create QuestionnaireSubmitted**

Create `app/Notifications/QuestionnaireSubmitted.php`:

```php
<?php

namespace App\Notifications;

use App\Models\QuestionnaireInstances;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class QuestionnaireSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public QuestionnaireInstances $instance,
        public bool $isUpdate = false,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $clientName = $this->instance->recipientContact->name ?? 'A client';
        $verb = $this->isUpdate ? 'updated' : 'submitted';

        $subject = "{$clientName} {$verb} the {$this->instance->name}";

        $mail = (new MailMessage())
            ->subject($subject)
            ->greeting('Heads up,')
            ->line("{$clientName} has {$verb} the {$this->instance->name} for booking {$this->instance->booking->name}.");

        $event = $this->instance->booking->events->first();
        if ($event && $event->key) {
            $mail->action('View on event', route('events.show', $event->key));
        }

        return $mail;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'instance_id' => $this->instance->id,
            'questionnaire_name' => $this->instance->name,
            'is_update' => $this->isUpdate,
        ];
    }
}
```

- [ ] **Step 3: Smoke-test**

Run: `docker-compose exec app php artisan tinker --execute="echo class_exists('App\\Notifications\\QuestionnaireSent').','.class_exists('App\\Notifications\\QuestionnaireSubmitted');"`

Expected: `1,1`

- [ ] **Step 4: Commit**

```bash
git add app/Notifications/QuestionnaireSent.php app/Notifications/QuestionnaireSubmitted.php
git commit -m "$(cat <<'EOF'
Add QuestionnaireSent and QuestionnaireSubmitted notifications

Both extend Notification with ShouldQueue, deliver via mail and database.
Sent uses the band's name and email as the from-address. Submitted's
subject differs based on isUpdate.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 23: SendQuestionnaireRequest + BookingQuestionnaireController

**Files:**
- Create: `app/Http/Requests/SendQuestionnaireRequest.php`
- Create: `app/Http/Controllers/BookingQuestionnaireController.php`
- Modify: `routes/booking.php` (add four routes)
- Create: `tests/Feature/Questionnaires/SendQuestionnaireTest.php`

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Questionnaires/SendQuestionnaireTest.php`:

```php
<?php

namespace Tests\Feature\Questionnaires;

use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\QuestionnaireInstances;
use App\Models\Questionnaires;
use App\Models\User;
use App\Notifications\QuestionnaireSent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class SendQuestionnaireTest extends TestCase
{
    use RefreshDatabase;

    private Bands $band;
    private User $owner;
    private Bookings $booking;
    private Contacts $contact;
    private Questionnaires $template;

    protected function setUp(): void
    {
        parent::setUp();
        $this->band = Bands::factory()->create();
        $this->owner = User::factory()->create();
        $this->band->owners()->create(['user_id' => $this->owner->id]);

        $this->booking = Bookings::factory()->create(['band_id' => $this->band->id]);
        $this->contact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);
        $this->booking->contacts()->attach($this->contact, ['is_primary' => true]);

        $this->template = Questionnaires::factory()->create(['band_id' => $this->band->id]);
    }

    public function test_band_owner_can_send_questionnaire(): void
    {
        Notification::fake();

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ]
        );

        $response->assertStatus(302);

        $this->assertDatabaseHas('questionnaire_instances', [
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'status' => 'sent',
        ]);

        Notification::assertSentTo($this->contact, QuestionnaireSent::class);
    }

    public function test_send_fails_when_contact_lacks_portal_access(): void
    {
        $this->contact->update(['can_login' => false]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ]
        );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_contact_not_on_booking(): void
    {
        $otherContact = Contacts::factory()->create(['band_id' => $this->band->id, 'can_login' => true]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $otherContact->id,
            ]
        );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_template_belongs_to_different_band(): void
    {
        $otherBand = Bands::factory()->create();
        $foreign = Questionnaires::factory()->create(['band_id' => $otherBand->id]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $foreign->id,
                'recipient_contact_id' => $this->contact->id,
            ]
        );

        $response->assertStatus(422);
    }

    public function test_send_fails_when_template_archived(): void
    {
        $this->template->update(['archived_at' => now()]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.send', [$this->band, $this->booking]),
            [
                'questionnaire_id' => $this->template->id,
                'recipient_contact_id' => $this->contact->id,
            ]
        );

        $response->assertStatus(422);
    }

    public function test_resend_does_not_create_new_instance(): void
    {
        Notification::fake();

        $existing = QuestionnaireInstances::factory()->create([
            'questionnaire_id' => $this->template->id,
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $response = $this->actingAs($this->owner)->post(
            route('bookings.questionnaires.resend', [$this->band, $this->booking, $existing])
        );

        $response->assertStatus(302);
        $this->assertSame(1, QuestionnaireInstances::where('booking_id', $this->booking->id)->count());
        Notification::assertSentTo($this->contact, QuestionnaireSent::class);
    }

    public function test_lock_and_unlock_changes_status(): void
    {
        $instance = QuestionnaireInstances::factory()->submitted()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->post(route('bookings.questionnaires.lock', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $instance->refresh();
        $this->assertSame('locked', $instance->status);
        $this->assertNotNull($instance->locked_at);

        $this->actingAs($this->owner)
            ->post(route('bookings.questionnaires.unlock', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $instance->refresh();
        $this->assertSame('submitted', $instance->status);
        $this->assertNull($instance->locked_at);
    }

    public function test_destroy_soft_deletes_instance(): void
    {
        $instance = QuestionnaireInstances::factory()->create([
            'booking_id' => $this->booking->id,
            'recipient_contact_id' => $this->contact->id,
            'sent_by_user_id' => $this->owner->id,
        ]);

        $this->actingAs($this->owner)
            ->delete(route('bookings.questionnaires.destroy', [$this->band, $this->booking, $instance]))
            ->assertStatus(302);

        $this->assertSoftDeleted('questionnaire_instances', ['id' => $instance->id]);
    }
}
```

- [ ] **Step 2: Run failing tests**

Run: `docker-compose exec app php artisan test tests/Feature/Questionnaires/SendQuestionnaireTest.php`

Expected: FAIL — routes/controller missing.

- [ ] **Step 3: Create SendQuestionnaireRequest**

Create `app/Http/Requests/SendQuestionnaireRequest.php`:

```php
<?php

namespace App\Http\Requests;

use App\Models\Questionnaires;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SendQuestionnaireRequest extends FormRequest
{
    public function authorize(): bool
    {
        $band = $this->route('band');
        return $this->user()->canWrite('questionnaires', $band->id);
    }

    public function rules(): array
    {
        $band = $this->route('band');
        $booking = $this->route('booking');

        return [
            'questionnaire_id' => [
                'required',
                'integer',
                Rule::exists('questionnaires', 'id')
                    ->where(fn ($q) => $q->where('band_id', $band->id)->whereNull('archived_at')->whereNull('deleted_at')),
            ],
            'recipient_contact_id' => [
                'required',
                'integer',
                Rule::exists('booking_contacts', 'contact_id')
                    ->where(fn ($q) => $q->where('booking_id', $booking->id)),
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $contactId = $this->input('recipient_contact_id');
            if (!$contactId) return;

            $contact = \App\Models\Contacts::find($contactId);
            if ($contact && !$contact->can_login) {
                $v->errors()->add('recipient_contact_id', 'This contact does not have portal access enabled. Enable it before sending a questionnaire.');
            }
        });
    }
}
```

- [ ] **Step 4: Create the controller**

Create `app/Http/Controllers/BookingQuestionnaireController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendQuestionnaireRequest;
use App\Models\Bands;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Models\Questionnaires;
use App\Models\QuestionnaireInstances;
use App\Notifications\QuestionnaireSent;
use App\Services\QuestionnaireSnapshotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class BookingQuestionnaireController extends Controller
{
    public function __construct(private QuestionnaireSnapshotService $snapshotService)
    {
    }

    public function send(SendQuestionnaireRequest $request, Bands $band, Bookings $booking): RedirectResponse
    {
        abort_if($booking->band_id !== $band->id, 404);

        $template = Questionnaires::findOrFail($request->input('questionnaire_id'));
        $contact = Contacts::findOrFail($request->input('recipient_contact_id'));

        $instance = $this->snapshotService->snapshot($template, $booking, $contact, Auth::user());
        $contact->notify(new QuestionnaireSent($instance));

        return back()->with('success', "Questionnaire sent to {$contact->name}.");
    }

    public function resend(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $instance->recipientContact->notify(new QuestionnaireSent($instance));
        return back()->with('success', 'Questionnaire email re-sent.');
    }

    public function lock(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $instance->update([
            'status' => QuestionnaireInstances::STATUS_LOCKED,
            'locked_at' => now(),
            'locked_by_user_id' => Auth::id(),
        ]);
        return back()->with('success', 'Questionnaire locked.');
    }

    public function unlock(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);

        $hasResponses = $instance->responses()->exists();
        $instance->update([
            'status' => $instance->submitted_at
                ? QuestionnaireInstances::STATUS_SUBMITTED
                : ($hasResponses ? QuestionnaireInstances::STATUS_IN_PROGRESS : QuestionnaireInstances::STATUS_SENT),
            'locked_at' => null,
            'locked_by_user_id' => null,
        ]);
        return back()->with('success', 'Questionnaire unlocked.');
    }

    public function destroy(Bands $band, Bookings $booking, QuestionnaireInstances $instance): RedirectResponse
    {
        $this->authorizeAccess($band, $booking, $instance);
        $instance->delete();
        return back()->with('success', 'Questionnaire deleted.');
    }

    private function authorizeAccess(Bands $band, Bookings $booking, QuestionnaireInstances $instance): void
    {
        abort_unless(Auth::user()->canWrite('questionnaires', $band->id), 403);
        abort_if($booking->band_id !== $band->id, 404);
        abort_if($instance->booking_id !== $booking->id, 404);
    }
}
```

- [ ] **Step 5: Add routes to `routes/booking.php`**

In `routes/booking.php`, find the existing `auth + verified` group with booking routes. Add inside that group:

```php
    Route::post('bands/{band}/booking/{booking}/questionnaires', [\App\Http\Controllers\BookingQuestionnaireController::class, 'send'])
        ->name('bookings.questionnaires.send');
    Route::post('bands/{band}/booking/{booking}/questionnaires/{instance}/resend', [\App\Http\Controllers\BookingQuestionnaireController::class, 'resend'])
        ->name('bookings.questionnaires.resend');
    Route::post('bands/{band}/booking/{booking}/questionnaires/{instance}/lock', [\App\Http\Controllers\BookingQuestionnaireController::class, 'lock'])
        ->name('bookings.questionnaires.lock');
    Route::post('bands/{band}/booking/{booking}/questionnaires/{instance}/unlock', [\App\Http\Controllers\BookingQuestionnaireController::class, 'unlock'])
        ->name('bookings.questionnaires.unlock');
    Route::delete('bands/{band}/booking/{booking}/questionnaires/{instance}', [\App\Http\Controllers\BookingQuestionnaireController::class, 'destroy'])
        ->name('bookings.questionnaires.destroy');
```

- [ ] **Step 6: Regenerate Ziggy**

Run: `docker-compose exec app php artisan ziggy:generate`

- [ ] **Step 7: Run tests to verify they pass**

Run: `docker-compose exec app php artisan test tests/Feature/Questionnaires/SendQuestionnaireTest.php`

Expected: PASS — 8 tests.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/BookingQuestionnaireController.php app/Http/Requests/SendQuestionnaireRequest.php routes/booking.php tests/Feature/Questionnaires/SendQuestionnaireTest.php
git add -A # ziggy
git commit -m "$(cat <<'EOF'
Add booking-side questionnaire controller (send, resend, lock, unlock, delete)

SendQuestionnaireRequest validates band-scope and portal-access. Resend
fires the email without snapshotting again. Lock/unlock toggle status with
appropriate field stamps. Destroy is a soft delete.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

## Task 24: Phase 4 checkpoint

- [ ] **Step 1: Run full backend test suite**

Run: `docker-compose exec app php artisan test --parallel --processes=4`

Expected: PASS

- [ ] **Step 2: Confirm Vite still builds**

Run: `docker-compose exec node npm run build`

Expected: PASS

- [ ] **Step 3: Phase 4 done.**

> **Phases 5 (client portal) and 6 (event mapping) are continued in a separate planning step. The pattern is identical: TDD with feature tests, atomic commits, checkpoint at end of phase.**
>
> **End of available plan.** When the engineer reaches this point, they should:
> 1. Confirm Phases 1–4 pass all tests.
> 2. Manually exercise the band-side flow (build template → send to client → see entry on the booking).
> 3. Open the next plan file `2026-04-28-questionnaires-part-2.md` (to be authored after Phase 4 lands) for portal + event integration.
