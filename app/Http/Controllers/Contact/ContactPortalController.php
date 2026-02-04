<?php

namespace App\Http\Controllers\Contact;

use App\Http\Controllers\Controller;
use App\Models\Bookings;
use App\Models\Contacts;
use App\Services\ContactPaymentService;
use App\Services\MediaLibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ContactPortalController extends Controller
{
    protected $paymentService;

    public function __construct(ContactPaymentService $paymentService)
    {
        $this->middleware('auth:contact');
        $this->paymentService = $paymentService;
    }

    /**
     * Show the contact dashboard with all their bookings
     */
    public function dashboard()
    {
        $contact = Auth::guard('contact')->user();
        
        // Get outstanding invoices
        $outstandingInvoices = \App\Models\Invoices::whereHas('booking', function($query) use ($contact) {
                $query->whereHas('contacts', function($q) use ($contact) {
                    $q->where('contacts.id', $contact->id);
                });
            })
            ->with(['booking.band', 'booking.payments' => function($query) {
                $query->where('status', '!=', 'void');
            }])
            ->where('status', '!=', 'paid')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Get the payment amount (what band receives) - need raw value in cents
                $payment = $invoice->booking->payments()->where('invoices_id', $invoice->id)->first();
                $baseAmount = $payment ? $payment->getRawOriginal('amount') : 0;
                $feeAmount = $invoice->amount - $baseAmount;
                
                return [
                    'id' => $invoice->id,
                    'stripe_url' => $invoice->stripe_url,
                    'base_amount' => $baseAmount,
                    'fee_amount' => $feeAmount,
                    'total_amount' => $invoice->amount,
                    'has_convenience_fee' => $invoice->convenience_fee,
                    'status' => $invoice->status,
                    'created_at' => $invoice->created_at->format('M j, Y'),
                    'booking' => [
                        'id' => $invoice->booking->id,
                        'name' => $invoice->booking->name,
                        'date' => $invoice->booking->date->format('M j, Y'),
                        'band_name' => $invoice->booking->band->name,
                    ],
                ];
            });
        
        // Get all bookings for this contact with payment information
        $bookings = $contact->bookings()
            ->with([
                'band', 
                'eventType', 
                'payments' => function($query) {
                    $query->where('status', 'paid')->orderBy('date', 'desc');
                }, 
                'payments.invoice',
                'contract'
            ])
            ->where('date', '>=', now()->subMonths(6))
            ->orderBy('date', 'desc')
            ->get()
            ->map(function ($booking) {
                // Get the last time the status was changed from activity log
                $statusChangedAt = $booking->activities()
                    ->where('log_name', 'bookings')
                    ->whereJsonContains('properties->attributes->status', $booking->status)
                    ->latest()
                    ->first()?->created_at;

                // If no status change found, use updated_at as fallback
                if (!$statusChangedAt) {
                    $statusChangedAt = $booking->updated_at;
                }

                // Format payment history with detailed information
                $paymentHistory = $booking->payments->map(function($payment) {
                    $invoiceData = null;
                    if ($payment->invoice) {
                        $invoiceData = [
                            'id' => $payment->invoice->id,
                            'stripe_id' => $payment->invoice->stripe_id,
                            'stripe_url' => $payment->invoice->stripe_url,
                            'status' => $payment->invoice->status,
                            'amount' => $payment->invoice->amount,
                            'convenience_fee' => $payment->invoice->convenience_fee,
                            'created_at' => $payment->invoice->created_at->format('M j, Y'),
                        ];
                    }

                    return [
                        'id' => $payment->id,
                        'name' => $payment->name ?? 'Payment',
                        'amount' => (int) $payment->amount,
                        'date' => $payment->date?->format('M j, Y'),
                        'status' => $payment->status,
                        'payment_type' => $payment->payment_type?->value ?? 'manual',
                        'has_invoice' => $payment->invoice !== null,
                        'invoice' => $invoiceData,
                    ];
                });

                // Format contract information with comprehensive details
                $contract = null;
                if ($booking->contract) {
                    $contract = [
                        'id' => $booking->contract->id,
                        'status' => $booking->contract->status,
                        'download_url' => route('portal.booking.contract', $booking->id), // Use dedicated download route
                        'envelope_id' => $booking->contract->envelope_id,
                        'created_at' => $booking->contract->created_at->format('M j, Y'),
                        'updated_at' => $booking->contract->updated_at->format('M j, Y g:i A'),
                        'is_completed' => in_array($booking->contract->status, ['document.completed', 'completed']),
                        'is_signed' => in_array($booking->contract->status, ['document.completed', 'document.signed', 'completed']),
                        'can_download' => !empty($booking->contract->asset_url),
                    ];
                }

                return [
                    'id' => $booking->id,
                    'name' => $booking->name,
                    'date' => $booking->date->format('M j, Y'),
                    'start_time' => $booking->start_time->format('g:i A'),
                    'end_time' => $booking->end_time->format('g:i A'),
                    'venue_name' => $booking->venue_name,
                    'venue_address' => $booking->venue_address,
                    'status' => $booking->status,
                    'status_changed_at' => $statusChangedAt?->format('M j, Y'),
                    'price' => $booking->price,
                    'amount_paid' => $booking->amount_paid,
                    'amount_due' => $booking->amount_due,
                    'band_name' => $booking->band->name,
                    'event_type' => $booking->eventType?->name,
                    'is_paid' => $booking->is_paid,
                    'has_balance' => $booking->amount_due > 0,
                    'payments' => $paymentHistory,
                    'contract' => $contract,
                ];
            });

        return Inertia::render('Contact/Dashboard', [
            'portal' => [
                'id' => $contact->id,
                'name' => $contact->name,
                'email' => $contact->email,
                'phone' => $contact->phone,
            ],
            'bookings' => $bookings,
            'outstandingInvoices' => $outstandingInvoices,
        ]);
    }

    /**
     * Show payment page for a specific booking
     */
    public function showPayment(Bookings $booking)
    {
        $contact = Auth::guard('contact')->user();
        
        // Verify this contact is associated with this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        return Inertia::render('Contact/Payment', [
            'booking' => [
                'id' => $booking->id,
                'name' => $booking->name,
                'date' => $booking->date->format('Y-m-d'),
                'venue_name' => $booking->venue_name,
                'band_name' => $booking->band->name,
                'price' => $booking->price,
                'amount_paid' => $booking->amount_paid,
                'amount_due' => $booking->amount_due,
            ],
            'portal' => [
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Create Stripe Checkout Session for payment
     */
    public function createCheckoutSession(Request $request, Bookings $booking)
    {
        $contact = Auth::guard('contact')->user();
        
        // Verify this contact is associated with this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        $request->validate([
            'amount' => 'required|numeric|min:1|max:' . $booking->amount_due,
        ]);
       

        try {
            $checkoutUrl = $this->paymentService->createCheckoutSession(
                $booking,
                $contact,
                $request->amount,
                true // Always apply convenience fee
            );

            return response()->json([
                'checkout_url' => $checkoutUrl,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create payment session: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Payment success callback
     */
    public function paymentSuccess(Request $request)
    {
        $sessionId = $request->query('session_id');
        
        return Inertia::render('Contact/PaymentSuccess', [
            'session_id' => $sessionId,
        ]);
    }

    /**
     * Payment cancellation callback
     */
    public function paymentCancelled()
    {
        return Inertia::render('Contact/PaymentCancelled');
    }

    /**
     * Show payment history for the contact
     */
    public function paymentHistory()
    {
        $contact = Auth::guard('contact')->user();
        
        // Get all payments related to this contact's bookings with comprehensive details
        $payments = $contact->bookings()
            ->with(['payments' => function($query) {
                $query->where('status', 'paid')->orderBy('date', 'desc');
            }, 'payments.invoice', 'band'])
            ->get()
            ->flatMap(function ($booking) {
                return $booking->payments->map(function ($payment) use ($booking) {
                    $invoiceData = null;
                    if ($payment->invoice) {
                        $invoiceData = [
                            'id' => $payment->invoice->id,
                            'stripe_url' => $payment->invoice->stripe_url,
                            'stripe_id' => $payment->invoice->stripe_id,
                            'amount' => $payment->invoice->amount,
                            'status' => $payment->invoice->status,
                            'convenience_fee' => $payment->invoice->convenience_fee,
                            'created_at' => $payment->invoice->created_at->format('M j, Y'),
                        ];
                    }

                    return [
                        'id' => $payment->id,
                        'name' => $payment->name ?? 'Payment',
                        'booking_id' => $booking->id,
                        'booking_name' => $booking->name,
                        'booking_date' => $booking->date->format('M j, Y'),
                        'amount' => $payment->amount,
                        'date' => $payment->date?->format('M j, Y'),
                        'status' => $payment->status,
                        'payment_type' => $payment->payment_type?->value ?? 'manual',
                        'band_name' => $booking->band->name,
                        'invoice' => $invoiceData,
                    ];
                });
            })
            ->sortByDesc('date')
            ->values();

        return Inertia::render('Contact/PaymentHistory', [
            'payments' => $payments,
            'contact' => [
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Show all invoices for the contact
     */
    public function invoices()
    {
        $contact = Auth::guard('contact')->user();
        
        // Get all invoices from the contact's bookings with comprehensive details
        $invoices = \App\Models\Invoices::whereHas('booking', function($query) use ($contact) {
                $query->whereHas('contacts', function($q) use ($contact) {
                    $q->where('contacts.id', $contact->id);
                });
            })
            ->with(['booking.band', 'booking.contract', 'booking.payments' => function($query) {
                $query->where('status', '!=', 'void');
            }])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($invoice) {
                // Get the payment amount (what band receives) - need raw value in cents
                $payment = $invoice->booking->payments()->where('invoices_id', $invoice->id)->first();
                $baseAmount = $payment ? $payment->getRawOriginal('amount') : 0;
                $feeAmount = $invoice->amount - $baseAmount;
                
                // Format contract information if available
                $contractData = null;
                if ($invoice->booking->contract) {
                    $contractData = [
                        'id' => $invoice->booking->contract->id,
                        'status' => $invoice->booking->contract->status,
                        'download_url' => route('portal.booking.contract', $invoice->booking->id), // Use dedicated download route
                        'is_signed' => in_array($invoice->booking->contract->status, ['document.completed', 'document.signed', 'completed']),
                    ];
                }
                
                return [
                    'id' => $invoice->id,
                    'stripe_id' => $invoice->stripe_id,
                    'stripe_url' => $invoice->stripe_url,
                    'base_amount' => $baseAmount,
                    'fee_amount' => $feeAmount,
                    'total_amount' => $invoice->amount,
                    'has_convenience_fee' => $invoice->convenience_fee,
                    'status' => $invoice->status,
                    'created_at' => $invoice->created_at->format('M j, Y'),
                    'paid_at' => $invoice->paid_at?->format('M j, Y'),
                    'booking' => [
                        'id' => $invoice->booking->id,
                        'name' => $invoice->booking->name,
                        'date' => $invoice->booking->date->format('M j, Y'),
                        'band_name' => $invoice->booking->band->name,
                        'venue_name' => $invoice->booking->venue_name,
                        'contract' => $contractData,
                    ],
                    'payment' => $payment ? [
                        'id' => $payment->id,
                        'name' => $payment->name,
                        'payment_type' => $payment->payment_type?->value ?? 'manual',
                        'date' => $payment->date?->format('M j, Y'),
                    ] : null,
                ];
            });

        return Inertia::render('Contact/Invoices', [
            'invoices' => $invoices,
            'contact' => [
                'name' => $contact->name,
                'email' => $contact->email,
            ],
        ]);
    }

    /**
     * Download contract for a booking
     */
    public function downloadContract(Bookings $booking)
    {
        $contact = Auth::guard('contact')->user();

        // Verify this contact is associated with this booking
        if (!$booking->contacts->contains($contact->id)) {
            abort(403, 'Unauthorized access to this booking.');
        }

        // Check if contract exists
        if (!$booking->contract || !$booking->contract->asset_url) {
            abort(404, 'Contract not found.');
        }

        $contract = $booking->contract;
        $filePath = ltrim($contract->asset_url, '/');

        // Check if file exists in S3
        if (!\Storage::disk('s3')->exists($filePath)) {
            abort(404, 'Contract file not found.');
        }

        // Stream the file from S3
        return \Storage::disk('s3')->download(
            $filePath,
            basename($filePath)
        );
    }

    /**
     * Show media gallery for contact's events
     */
    public function media()
    {
        $contact = Auth::guard('contact')->user();
        $mediaService = app(MediaLibraryService::class);

        // Get all media accessible to this contact
        $media = $mediaService->getContactAccessibleMedia($contact);

        // Group media by folder/event for organized display
        $folders = $media->groupBy('folder_path')->map(function ($files, $path) use ($contact) {
            $firstFile = $files->first();

            // Try to get the event for this folder
            $event = null;
            if ($path) {
                $event = \App\Models\Events::where('media_folder_path', $path)
                    ->whereHasMorph('eventable', [\App\Models\Bookings::class], function ($query) use ($contact) {
                        $query->whereHas('contacts', function ($q) use ($contact) {
                            $q->where('contacts.id', $contact->id);
                        });
                    })
                    ->with('eventable')
                    ->first();
            }

            return [
                'path' => $path,
                'name' => $event ? $event->title : ($path ? basename($path) : 'Uncategorized'),
                'event_date' => $event ? $event->date->format('M j, Y') : null,
                'event_id' => $event?->id,
                'booking_name' => $event?->eventable?->name,
                'file_count' => $files->count(),
                'thumbnail_url' => $firstFile->media_type === 'image' || $firstFile->media_type === 'video'
                    ? route('portal.media.thumbnail', $firstFile->id)
                    : null,
                'files' => $files->map(function ($file) {
                    return [
                        'id' => $file->id,
                        'filename' => $file->filename,
                        'media_type' => $file->media_type,
                        'file_size' => $file->file_size,
                        'created_at' => $file->created_at->format('M j, Y'),
                        'is_image' => $file->media_type === 'image',
                        'is_video' => $file->media_type === 'video',
                        'thumbnail_url' => $file->media_type === 'image' || $file->media_type === 'video'
                            ? route('portal.media.thumbnail', $file->id)
                            : null,
                        'url' => route('portal.media.serve', $file->id),
                        'download_url' => route('portal.media.download', $file->id),
                    ];
                })->values(),
            ];
        })->values();

        return Inertia::render('Contact/Media', [
            'folders' => $folders,
            'totalFiles' => $media->count(),
        ]);
    }

    /**
     * Download a media file (with contact permission check)
     */
    public function downloadMedia($mediaId)
    {
        $contact = Auth::guard('contact')->user();
        $mediaService = app(MediaLibraryService::class);

        // Get all accessible media for this contact
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Check if this media file is in the accessible list
        $mediaFile = $accessibleMedia->firstWhere('id', $mediaId);

        if (!$mediaFile) {
            abort(403, 'You do not have permission to access this file.');
        }

        // Stream the file from storage
        $disk = $mediaFile->disk ?? config('filesystems.default');

        if (!\Storage::disk($disk)->exists($mediaFile->stored_filename)) {
            abort(404, 'File not found.');
        }

        return \Storage::disk($disk)->download(
            $mediaFile->stored_filename,
            $mediaFile->filename
        );
    }

    /**
     * Serve a media file thumbnail (with contact permission check)
     */
    public function serveMediaThumbnail($mediaId)
    {
        $contact = Auth::guard('contact')->user();
        $mediaService = app(MediaLibraryService::class);

        // Get all accessible media for this contact
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Check if this media file is in the accessible list
        $mediaFile = $accessibleMedia->firstWhere('id', $mediaId);

        if (!$mediaFile) {
            abort(403, 'You do not have permission to access this file.');
        }

        if ($mediaFile->media_type !== 'image' && $mediaFile->media_type !== 'video') {
            abort(404, 'Thumbnail not available for this file type');
        }

        $disk = $mediaFile->disk ?? config('filesystems.default');
        $thumbnailPath = str_replace(
            '.' . pathinfo($mediaFile->stored_filename, PATHINFO_EXTENSION),
            '_thumb.jpg',
            $mediaFile->stored_filename
        );

        // Generate ETag based on media ID and updated_at timestamp
        $etag = md5($mediaFile->id . '-' . $mediaFile->updated_at->timestamp);
        $lastModified = $mediaFile->updated_at->format('D, d M Y H:i:s') . ' GMT';

        // Check if client has cached version
        $ifNoneMatch = request()->header('If-None-Match');
        $ifModifiedSince = request()->header('If-Modified-Since');

        if ($ifNoneMatch === $etag || $ifModifiedSince === $lastModified) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Last-Modified', $lastModified)
                ->header('Cache-Control', 'public, max-age=2592000, immutable');
        }

        try {
            // If thumbnail doesn't exist, serve the original file
            if (!\Storage::disk($disk)->exists($thumbnailPath)) {
                $file = \Storage::disk($disk)->get($mediaFile->stored_filename);
                return response($file)
                    ->header('Content-Type', $mediaFile->mime_type)
                    ->header('Cache-Control', 'public, max-age=2592000, immutable')
                    ->header('ETag', $etag)
                    ->header('Last-Modified', $lastModified);
            }

            $file = \Storage::disk($disk)->get($thumbnailPath);

            return response($file)
                ->header('Content-Type', 'image/jpeg')
                ->header('Cache-Control', 'public, max-age=2592000, immutable')
                ->header('ETag', $etag)
                ->header('Last-Modified', $lastModified);
        } catch (\Exception $e) {
            abort(404, 'Thumbnail not found');
        }
    }

    /**
     * Serve a media file for inline viewing (with contact permission check)
     */
    public function serveMedia($mediaId)
    {
        $contact = Auth::guard('contact')->user();
        $mediaService = app(MediaLibraryService::class);

        // Get all accessible media for this contact
        $accessibleMedia = $mediaService->getContactAccessibleMedia($contact);

        // Check if this media file is in the accessible list
        $mediaFile = $accessibleMedia->firstWhere('id', $mediaId);

        if (!$mediaFile) {
            abort(403, 'You do not have permission to access this file.');
        }

        $disk = $mediaFile->disk ?? config('filesystems.default');

        if (!\Storage::disk($disk)->exists($mediaFile->stored_filename)) {
            abort(404, 'File not found.');
        }

        // Generate ETag based on media ID and updated_at timestamp
        $etag = md5($mediaFile->id . '-' . $mediaFile->updated_at->timestamp);
        $lastModified = $mediaFile->updated_at->format('D, d M Y H:i:s') . ' GMT';

        // Check if client has cached version
        $ifNoneMatch = request()->header('If-None-Match');
        $ifModifiedSince = request()->header('If-Modified-Since');

        if ($ifNoneMatch === $etag || $ifModifiedSince === $lastModified) {
            return response('', 304)
                ->header('ETag', $etag)
                ->header('Last-Modified', $lastModified)
                ->header('Cache-Control', 'public, max-age=2592000, immutable');
        }

        try {
            $file = \Storage::disk($disk)->get($mediaFile->stored_filename);

            return response($file)
                ->header('Content-Type', $mediaFile->mime_type)
                ->header('Content-Disposition', 'inline; filename="' . $mediaFile->filename . '"')
                ->header('Cache-Control', 'public, max-age=2592000, immutable')
                ->header('ETag', $etag)
                ->header('Last-Modified', $lastModified);
        } catch (\Exception $e) {
            abort(404, 'File not found');
        }
    }
}
