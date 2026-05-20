<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short, reusable dress-code label scoped to a single band. Backs the
 * "attire chips" picker on the mobile event-edit screen.
 *
 * @property int    $id
 * @property int    $band_id
 * @property string $label
 * @property int    $position
 */
class AttireChip extends Model
{
    use HasFactory;

    protected $table = 'attire_chips';

    protected $fillable = ['label', 'position'];

    protected $casts = [
        'position' => 'integer',
    ];

    public function band(): BelongsTo
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }
}
