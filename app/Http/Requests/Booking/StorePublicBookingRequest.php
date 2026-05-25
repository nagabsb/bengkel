<?php

namespace App\Http\Requests\Booking;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StorePublicBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tenant' => ['nullable', 'string', 'max:120'],
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => ['nullable', 'string', 'max:30'],
            'vehicle_plate_number' => ['nullable', 'string', 'max:20'],
            'booking_date' => ['required', 'date_format:Y-m-d'],
            'booking_time' => ['nullable', 'date_format:H:i'],
            'complaint' => ['required', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_name.required' => 'Nama pelanggan wajib diisi.',
            'booking_date.required' => 'Tanggal booking wajib diisi.',
            'booking_date.date_format' => 'Format tanggal booking harus yyyy-mm-dd.',
            'booking_time.date_format' => 'Format jam booking harus HH:mm.',
            'complaint.required' => 'Keluhan awal wajib diisi.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $bookingDate = trim((string) $this->input('booking_date', ''));

        if ($bookingDate !== '') {
            try {
                $bookingDate = Carbon::parse($bookingDate)->format('Y-m-d');
            } catch (\Throwable) {
                // Keep raw input to trigger validation error in rules().
            }
        }

        $this->merge([
            'tenant' => trim((string) $this->input('tenant', '')),
            'customer_name' => trim((string) $this->input('customer_name', '')),
            'customer_phone' => trim((string) $this->input('customer_phone', '')),
            'vehicle_plate_number' => strtoupper(trim((string) $this->input('vehicle_plate_number', ''))),
            'booking_date' => $bookingDate,
            'booking_time' => trim((string) $this->input('booking_time', '')),
            'complaint' => trim((string) $this->input('complaint', '')),
            'notes' => trim((string) $this->input('notes', '')),
        ]);
    }
}
