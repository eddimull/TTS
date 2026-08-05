<?php

namespace App\Models;

use App\Events\BandDataChanged;
use App\Models\Traits\BroadcastsBandChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Lodging extends Model
{
    use HasFactory, SoftDeletes, LogsActivity, BroadcastsBandChanges;

    protected $fillable = [
        'band_id', 'name', 'address', 'latitude', 'longitude',
        'check_in_at', 'check_out_at', 'notes', 'booking_id', 'event_id',
    ];

    protected $casts = [
        'check_in_at'  => 'datetime',
        'check_out_at' => 'datetime',
        'latitude'     => 'float',
        'longitude'    => 'float',
    ];

    public function band()
    {
        return $this->belongsTo(Bands::class, 'band_id');
    }

    public function booking()
    {
        return $this->belongsTo(Bookings::class, 'booking_id');
    }

    public function event()
    {
        return $this->belongsTo(Events::class, 'event_id');
    }

    public function rooms()
    {
        return $this->hasMany(LodgingRoom::class)->orderBy('sort_order');
    }

    public function attachments()
    {
        return $this->hasMany(LodgingAttachment::class);
    }

    /**
     * Force a realtime "updated" signal for this lodging even though the
     * mutation that caused it (room sync, attachment upload/delete) only
     * touches a child table and leaves this row's own tracked columns
     * unchanged. BroadcastsBandChanges::broadcastHasMeaningfulChanges()
     * ignores updated_at-only changes, so a plain touch() alone never
     * broadcasts — this bypasses that gate by dispatching BandDataChanged
     * directly, mirroring BroadcastsBandChanges::broadcastBandChange()
     * exactly (same try/catch-and-report safety net: a realtime signal
     * must never break the write that caused it).
     */
    public function broadcastRefresh(): void
    {
        try {
            $bandId = $this->broadcastBandId();
            if (! $bandId) {
                return;
            }

            broadcast(new BandDataChanged(
                (int) $bandId,
                Str::snake(class_basename($this)),
                (int) $this->getKey(),
                'updated',
                $this->broadcastParent(),
            ))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'address', 'check_in_at', 'check_out_at',
                'notes', 'booking_id', 'event_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
