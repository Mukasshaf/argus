<?php

declare(strict_types=1);

namespace Sim\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SimStateController
{
    public function getState(string $channel): JsonResponse
    {
        $avail = DB::table('sim_state')
            ->where('channel_code', $channel)
            ->where('kind', 'availability')
            ->pluck('value', 'key')
            ->all();

        $rates = DB::table('sim_state')
            ->where('channel_code', $channel)
            ->where('kind', 'rate')
            ->pluck('value', 'key')
            ->all();

        return response()->json([
            'channel' => $channel,
            'availability' => $avail,
            'rates' => $rates,
        ]);
    }
}
