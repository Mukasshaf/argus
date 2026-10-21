<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\SyncOutcome;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Hotel;
use App\Models\SyncLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SyncLogController extends Controller
{
    public function index(Request $request): View
    {
        $hotel = Hotel::first();
        $channelId = $request->query('channel_id');
        $outcome = $request->query('outcome');
        $direction = $request->query('direction');
        $correlationId = $request->query('correlation_id');

        $query = SyncLog::with('channel')->orderBy('created_at', 'desc');

        if ($channelId) {
            $query->where('channel_id', $channelId);
        }
        if ($outcome && $outcome !== 'all') {
            $query->where('outcome', $outcome);
        }
        if ($direction && $direction !== 'all') {
            $query->where('direction', $direction);
        }
        if ($correlationId) {
            $query->where('correlation_id', 'like', "%{$correlationId}%");
        }

        $logs = $query->paginate(20);

        // Stats
        $totalLogs = SyncLog::count();
        $okLogs = SyncLog::where('outcome', SyncOutcome::Ok)->count();
        $failLogs = SyncLog::whereIn('outcome', [SyncOutcome::TransientFail, SyncOutcome::PermanentFail])->count();
        $successRate = $totalLogs > 0 ? round(($okLogs / $totalLogs) * 100, 1) : 100.0;
        $avgDuration = round(SyncLog::avg('duration_ms') ?? 0);

        $channels = Channel::all();

        return view('extranet.sync_logs', [
            'hotel' => $hotel,
            'logs' => $logs,
            'channels' => $channels,
            'channelId' => $channelId,
            'outcome' => $outcome,
            'direction' => $direction,
            'correlationId' => $correlationId,
            'totalLogs' => $totalLogs,
            'successRate' => $successRate,
            'failLogs' => $failLogs,
            'avgDuration' => $avgDuration,
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $log = SyncLog::with('channel')->findOrFail($id);

        return response()->json([
            'id' => $log->id,
            'correlation_id' => $log->correlation_id,
            'channel' => $log->channel?->name ?? 'Unknown',
            'channel_code' => $log->channel?->code ?? '',
            'direction' => $log->direction->value,
            'operation' => $log->operation,
            'response_status' => $log->response_status,
            'duration_ms' => $log->duration_ms,
            'attempt' => $log->attempt,
            'outcome' => $log->outcome->value,
            'error' => $log->error,
            'request_body' => $log->request_body,
            'response_body' => $log->response_body,
            'created_at' => $log->created_at?->toIso8601String(),
        ]);
    }
}
