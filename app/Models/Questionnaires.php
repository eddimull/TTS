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
