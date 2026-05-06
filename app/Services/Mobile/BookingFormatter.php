<?php

namespace App\Services\Mobile;

use App\Models\Bookings;
use App\Models\Payments;
use App\Services\Mobile\TokenService;

class BookingFormatter
{
    public function format(Bookings $booking): array
    {
        $base = [
            'id'              => $booking->id,
            'name'            => $booking->name,
            'event_count'     => $booking->event_count,
            'start_date'      => $booking->start_date?->format('Y-m-d'),
            'end_date'        => $booking->end_date?->format('Y-m-d'),
            'venue_summary'   => $booking->venue_summary,
            'is_multi_event'  => $booking->is_multi_event,
            'status'          => $booking->status,
            'price'           => (string) $booking->price,
            'event_type_id'   => $booking->event_type_id,
            'notes'           => $booking->notes,
            'amount_paid'     => (string) $booking->amount_paid,
            'amount_due'      => (string) $booking->amount_due,
            'is_paid'         => (bool) $booking->is_paid,
            'contract_option' => $booking->contract_option,
            'band'            => $booking->band ? [
                'id'          => $booking->band->id,
                'name'        => $booking->band->name,
                'is_personal' => (bool) $booking->band->is_personal,
                'logo_url'    => TokenService::resolveLogoUrl($booking->band->logo),
            ] : null,
            'contacts'        => $this->formatContacts($booking->contacts),
            'events'          => $booking->relationLoaded('events')
                ? $booking->events->map(fn ($e) => [
                    'id'           => $e->id,
                    'key'          => $e->key,
                    'title'        => $e->title,
                    'date'         => $e->date?->format('Y-m-d'),
                    'start_time'   => $e->start_time?->format('H:i'),
                    'end_time'     => $e->end_time?->format('H:i'),
                    'venue_name'   => $e->venue_name,
                    'venue_address' => $e->venue_address,
                ])->values()->all()
                : [],
            'contract' => null,
            'payments'  => [],
        ];

        if ($booking->relationLoaded('contract') && $booking->contract) {
            $c = $booking->contract;
            $base['contract'] = [
                'id'          => $c->id,
                'status'      => $c->status,
                'asset_url'   => $c->asset_url,
                'envelope_id' => $c->envelope_id,
            ];
        }

        if ($booking->relationLoaded('payments')) {
            $base['payments'] = $booking->payments
                ->map(fn ($p) => $this->formatPayment($p))
                ->values()->all();
        }

        return $base;
    }

    public function formatForFinance(Bookings $booking): array
    {
        return [
            'id'             => $booking->id,
            'name'           => $booking->name ?? '',
            'event_count'    => $booking->event_count,
            'start_date'     => $booking->start_date?->format('Y-m-d') ?? '',
            'end_date'       => $booking->end_date?->format('Y-m-d') ?? '',
            'venue_summary'  => $booking->venue_summary ?? '',
            'is_multi_event' => $booking->is_multi_event,
            'status'         => $booking->status ?? '',
            'price'         => (string) $booking->price,
            'amount_paid'   => (string) $booking->amount_paid,
            'amount_due'    => (string) $booking->amount_due,
            'is_paid'       => (bool) $booking->is_paid,
        ];
    }

    public function formatContacts($contacts): array
    {
        return $contacts->map(fn ($c) => [
            'id'         => $c->id,
            'bc_id'      => $c->pivot->id ?? null,
            'contact_id' => $c->id,
            'name'       => $c->name,
            'email'      => $c->email,
            'phone'      => $c->phone,
            'role'       => $c->pivot->role ?? null,
            'is_primary' => (bool) ($c->pivot->is_primary ?? false),
        ])->values()->all();
    }

    public function formatPayment(Payments $payment): array
    {
        return [
            'id'           => $payment->id,
            'name'         => $payment->name,
            'amount'       => (string) $payment->amount,
            'date'         => $payment->date?->format('Y-m-d'),
            'payment_type' => $payment->payment_type instanceof \App\Enums\PaymentType
                              ? $payment->payment_type->value
                              : $payment->payment_type,
            'status'       => $payment->status,
        ];
    }
}
