<?php

namespace Modules\Hms\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HospitalityDocumentService
{
    public const KINDS = ['quotation', 'invoice', 'receipt'];

    /**
     * Build the authoritative stay dates used by both A4 accommodation
     * documents and the compact receipt. Calendar nights drive room pricing;
     * elapsed hours remain descriptive and never silently change billing.
     */
    public function stayContext(object $booking, string $kind): array
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw ValidationException::withMessages(['document' => 'Select quotation, invoice or receipt.']);
        }
        $arrival = Carbon::parse($booking->hms_booking_arrival_date_time);
        $checkout = Carbon::parse($booking->hms_booking_departure_date_time);
        if ($checkout->lessThanOrEqualTo($arrival)) {
            throw ValidationException::withMessages(['checkout_date' => 'Checkout must be after arrival.']);
        }

        $nights = max(1, $arrival->copy()->startOfDay()->diffInDays($checkout->copy()->startOfDay()));
        $minutes = max(1, $arrival->diffInMinutes($checkout));
        $hours = intdiv($minutes, 60);
        $remainingMinutes = $minutes % 60;

        return [
            'kind' => $kind,
            'title' => [
                'quotation' => 'Accommodation Quotation',
                'invoice' => 'Accommodation Invoice',
                'receipt' => 'Accommodation Payment Receipt',
            ][$kind],
            'show_payment_summary' => $kind !== 'quotation',
            'show_payment_lines' => $kind !== 'quotation',
            'booking_date' => Carbon::parse($booking->transaction_date ?: $booking->created_at)->toDateString(),
            'arrival_date' => $arrival->toDateString(),
            'arrival_time' => $arrival->format('H:i'),
            'checkout_date' => $checkout->toDateString(),
            'checkout_time' => $checkout->format('H:i'),
            'number_of_days' => $nights,
            'nights' => $nights,
            'elapsed_minutes' => $minutes,
            'stay_duration' => $nights.' '.($nights === 1 ? 'night' : 'nights').' ('.$hours.' '.($hours === 1 ? 'hour' : 'hours')
                .($remainingMinutes ? ' '.$remainingMinutes.' minutes' : '').')',
        ];
    }

    /** Payment deposits reduce the booking balance; security deposits do not. */
    public function accommodationPayments(Collection $payments): Collection
    {
        return $payments->reject(fn ($payment) => ($payment->payment_purpose ?? null) === 'security_deposit')->values();
    }
}
