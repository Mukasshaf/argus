<?php

declare(strict_types=1);

namespace Sim\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class SaraiSimController
{
    private function checkFault(): ?JsonResponse
    {
        $config = DB::table('sim_config')->where('channel_code', 'sarai')->first();
        if (!$config) {
            return null;
        }

        if ($config->latency_ms > 0) {
            usleep($config->latency_ms * 1000);
        }

        if ($config->mode === 'http500') {
            return response()->json(['error' => 'Internal Server Error'], 500);
        }
        if ($config->mode === 'http429') {
            return response()->json(['error' => 'Too Many Requests'], 429, ['Retry-After' => '10']);
        }
        if ($config->mode === 'token_expired') {
            return response()->json(['error' => 'The access token provided has expired'], 401);
        }
        if ($config->mode === 'reject400') {
            return response()->json(['error' => 'Bad Request validation failed'], 400);
        }

        return null;
    }

    public function issueToken(Request $request): JsonResponse
    {
        return response()->json([
            'access_token' => 'sim_sarai_bearer_token_' . time(),
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);
    }

    public function updateAvailability(Request $request, string $property, string $room): JsonResponse
    {
        if ($fault = $this->checkFault()) {
            return $fault;
        }

        $items = $request->input('items', []);
        foreach ($items as $item) {
            $date = $item['date'];
            $avail = (int) $item['available'];
            $key = "{$room}:{$date}";
            DB::table('sim_state')->updateOrInsert(
                ['channel_code' => 'sarai', 'kind' => 'availability', 'key' => $key],
                ['value' => $avail, 'updated_at' => now()]
            );
        }

        return response()->json(['status' => 'updated', 'count' => count($items)]);
    }

    public function updateRates(Request $request, string $property, string $rate): JsonResponse
    {
        if ($fault = $this->checkFault()) {
            return $fault;
        }

        $items = $request->input('items', []);
        foreach ($items as $item) {
            $date = $item['date'];
            $price = (int) $item['price'];
            $key = "{$rate}:{$date}";
            DB::table('sim_state')->updateOrInsert(
                ['channel_code' => 'sarai', 'kind' => 'rate', 'key' => $key],
                ['value' => $price, 'updated_at' => now()]
            );
        }

        return response()->json(['status' => 'updated', 'count' => count($items)]);
    }

    public function getReservations(Request $request): JsonResponse
    {
        if ($fault = $this->checkFault()) {
            return $fault;
        }

        $cursor = (int) ($request->query('cursor') ?: 0);
        $limit = (int) ($request->query('limit') ?: 100);

        $reservations = DB::table('sim_reservations')
            ->where('channel_code', 'sarai')
            ->where('id', '>', $cursor)
            ->orderBy('id', 'asc')
            ->limit($limit)
            ->get();

        $items = [];
        $maxId = $cursor;
        foreach ($reservations as $r) {
            $items[] = [
                'id' => $r->external_id,
                'version' => $r->version,
                'type' => $r->type,
                'room_code' => $r->room_code,
                'rate_code' => $r->rate_code,
                'check_in' => $r->check_in,
                'check_out' => $r->check_out,
                'guest_name' => $r->guest_name,
                'adults' => $r->adults,
                'total_price' => $r->total_price,
                'currency' => $r->currency,
            ];
            $maxId = max($maxId, (int) $r->id);
        }

        return response()->json([
            'items' => $items,
            'next_cursor' => count($items) > 0 ? (string) $maxId : null,
        ]);
    }

    public function requestBooking(Request $request): JsonResponse
    {
        $coreUrl = rtrim(env('CORE_BASE_URL', 'http://localhost:8000'), '/');
        $endpoint = $coreUrl . '/api/inbound/sarai/booking-requests';

        $payload = [
            'external_id' => $request->input('external_id', 'DIR-' . random_int(100000, 999999)),
            'version' => (int) $request->input('version', 1),
            'room_code' => $request->input('room_code', 'STD'),
            'rate_code' => $request->input('rate_code', 'EP'),
            'check_in' => $request->input('check_in', now()->toDateString()),
            'check_out' => $request->input('check_out', now()->addDays(2)->toDateString()),
            'guest_name' => $request->input('guest_name', 'Pooja Nambiar'),
            'adults' => (int) $request->input('adults', 1),
            'total_price' => (int) $request->input('total_price', 7840),
            'currency' => 'INR',
        ];

        $res = Http::post($endpoint, $payload);

        return response()->json([
            'status' => $res->status(),
            'body' => $res->json() ?? $res->body(),
        ], $res->status());
    }
}
