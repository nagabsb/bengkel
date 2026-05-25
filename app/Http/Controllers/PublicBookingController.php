<?php

namespace App\Http\Controllers;

use App\Http\Requests\Booking\StorePublicBookingRequest;
use App\Services\Owner\OwnerBookingPageBuilderService;
use App\Services\Owner\OwnerBookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class PublicBookingController extends Controller
{
    public function show(
        \Illuminate\Http\Request $request,
        OwnerBookingPageBuilderService $ownerBookingPageBuilderService,
    ): Response {
        $pageData = $ownerBookingPageBuilderService->buildPublicPageData($request);

        if (! (bool) ($pageData['isAvailable'] ?? false)) {
            $message = trim((string) ($pageData['availabilityMessage'] ?? 'Halaman booking tidak tersedia.'));
            abort(404, $message !== '' ? $message : 'Halaman booking tidak tersedia.');
        }

        return Inertia::render('Booking/Public', [
            ...$pageData,
        ]);
    }

    public function store(
        StorePublicBookingRequest $request,
        OwnerBookingPageBuilderService $ownerBookingPageBuilderService,
        OwnerBookingService $ownerBookingService,
    ): RedirectResponse {
        $publicBookingContext = $ownerBookingPageBuilderService->resolvePublicBookingContext($request);
        $validated = $request->validated();

        $plateNumber = trim((string) ($validated['vehicle_plate_number'] ?? ''));
        $manualNote = trim((string) ($validated['notes'] ?? ''));

        $notes = array_values(array_filter([
            $plateNumber !== '' ? 'No. Polisi: '.$plateNumber : null,
            $manualNote !== '' ? $manualNote : null,
        ]));

        $ownerBookingService->createBooking(
            (string) $publicBookingContext['tenant_id'],
            (string) $publicBookingContext['workshop_id'],
            [
                'workshop_id' => (string) $publicBookingContext['workshop_id'],
                'booking_date' => (string) ($validated['booking_date'] ?? ''),
                'booking_time' => trim((string) ($validated['booking_time'] ?? '')) !== ''
                    ? (string) $validated['booking_time']
                    : null,
                'customer_name' => (string) ($validated['customer_name'] ?? ''),
                'customer_phone' => trim((string) ($validated['customer_phone'] ?? '')) !== ''
                    ? (string) $validated['customer_phone']
                    : null,
                'complaint' => (string) ($validated['complaint'] ?? ''),
                'notes' => count($notes) > 0 ? implode(PHP_EOL, $notes) : null,
            ],
        );

        $bookingDate = trim((string) ($validated['booking_date'] ?? ''));
        $bookingTime = trim((string) ($validated['booking_time'] ?? ''));

        $statusMessage = 'Selamat, booking Anda berhasil. Silakan datang ke bengkel.';
        if ($bookingDate !== '') {
            try {
                $formattedBookingDate = Carbon::createFromFormat('Y-m-d', $bookingDate)->format('d/m/Y');
            } catch (\Throwable) {
                $formattedBookingDate = $bookingDate;
            }

            $statusMessage = 'Selamat, booking Anda berhasil. Silakan datang ke bengkel pada tanggal '.$formattedBookingDate;
            if ($bookingTime !== '') {
                $statusMessage .= ' pukul '.$bookingTime.' WIB';
            }

            $statusMessage .= '.';
        }

        return back()->with('status', $statusMessage);
    }
}
