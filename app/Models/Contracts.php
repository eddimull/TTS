<?php

namespace App\Models;

use App\Models\Traits\Signable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class Contracts extends Model
{
    use HasFactory;
    use Signable;
    use LogsActivity;

    protected $fillable = [
        'envelope_id',
        'author_id',
        'status',
        'asset_url',
        'custom_terms',
    ];

    protected $casts = [
        'custom_terms' => 'array',
        'updated_at' => 'date:Y-m-d h:i a',
    ];

    public function contractable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the PDF URL for PandaDoc or external access
     * Returns a publicly accessible URL for the contract PDF
     */
    public function getPdfUrl(): string
    {
        // If asset_url is already a full URL, return it
        if (filter_var($this->asset_url, FILTER_VALIDATE_URL)) {
            return $this->asset_url;
        }
        
        $filePath = ltrim($this->asset_url, '/');
        
        // Check if we're using MinIO (local development)
        $endpoint = config('filesystems.disks.s3.endpoint');
        
        if ($endpoint && str_contains($endpoint, 'minio')) {
            // For local development with MinIO, use a Laravel route as public proxy
            // This allows PandaDoc to access files during development
            return route('contracts.public.view', [
                'contractId' => $this->id,
                'token' => $this->generateAccessToken()
            ]);
        }
        
        // For production (AWS S3), generate a temporary signed URL
        return Storage::disk('s3')->temporaryUrl(
            $filePath,
            now()->addHours(24) // 24 hours for PandaDoc processing
        );
    }
    
    /**
     * Generate a temporary access token for public contract viewing
     */
    public function generateAccessToken(): string
    {
        return hash_hmac('sha256', $this->id . $this->updated_at, config('app.key'));
    }
    
    /**
     * Verify the access token for public contract viewing
     */
    public function verifyAccessToken(string $token): bool
    {
        return hash_equals($this->generateAccessToken(), $token);
    }

    /**
     * Get the relative file path for internal storage reference
     */
    public function getFilePath(): string
    {
        return ltrim($this->asset_url, '/');
    }

    public function getSignatureFields(): array
    {
        return [
            "name" => [
                "value" => "{$this->contractable->getContractRecipients()[0]['first_name']} {$this->contractable->getContractRecipients()[0]['last_name']}",
                "role" => "user"
            ]
        ];
    }

    public function getContractRecipients(): array
    {
        return $this->contractable->getContractRecipients();
    }

    public function getContractName(): string
    {
        return $this->contractable->getContractName();
    }

    public function uploadContract($pdf): void
    {
        $path = $pdf->store('contracts', 'public');
        
        // Generate the full URL for public disk
        $url = Storage::disk('public')->url($path);
        
        // If it's a relative path, make it absolute
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            $url = config('app.url') . $url;
        }
        
        $this->asset_url = $url;
        $this->save();
    }

    public function getCustomTermsAttribute($value)
    {
        return $value ? json_decode($value, true) : [];
    }

    /**
     * Configure activity logging options
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'envelope_id',
                'author_id',
                'status',
                'asset_url',
                'contractable_type',
                'contractable_id',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName('contracts')
            ->setDescriptionForEvent(fn(string $eventName) => "Contract has been {$eventName}");
    }
}
