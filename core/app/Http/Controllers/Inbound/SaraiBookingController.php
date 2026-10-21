<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inbound;

use App\Channels\Dto\ReservationEvent;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Services\RealtimeBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SaraiBookingController extends Controller
{
    public function handle(Request $request, RealtimeBookingService $bookingService): JsonResponse
    {
        $channel = Channel::where('code', 'sarai')->firstOrFail();

        $data = $request->validate([
            'external_id' => 'required|string',
            'version' => 'nullable|integer',
            'room_code' => 'required|string',
            'rate_code' => 'required|string',
            'check_in' => 'required|date_format:Y-m-d',
            'check_out' => 'required|date_format:Y-m-d|after:check_in',
            'guest_name' => 'required|string',
            'adults' => 'nullable|integer',
            'total_price' => 'required|integer',
            'currency' => 'nullable|string',
        ]);

        $evt = new ReservationEvent(
            channelId: $channel->id,
            externalId: (string) $data['external_id'],
            externalVersion: (int) ($data['version'] ?? 1),
            type: 'upsert',
            roomCode: (string) $data['room_code'],
            rateCode: (string) $data['rate_code'],
            checkIn: (string) $data['check_in'],
            checkOut: (string) $data['check_out'],
            guestName: (string) $data['guest_name'],
            adults: (int) ($data['adults'] ?? 1),
            totalPrice: (int) $data['total_price'],
            currency: (string) ($data['currency'] ?? 'INR'),
            externalEventId: (string) Str::uuid(),
        );

        $result = $bookingService->book($evt);

        return response()->json($result['data'], $result['status']);
    }
}
