<?php

declare(strict_types=1);

namespace App\Http\Controllers\Extranet;

use App\Enums\ChannelStatus;
use App\Enums\SyncOutcome;
use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\ChannelSyncState;
use App\Models\Hotel;
use App\Models\SyncLog;
use App\Services\SyncKick;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SyncLogController extends Controller
{
    public function index(Request $request): View
    {
        $hotel = $this->getActiveHotel() ?? Hotel::first();
        $channelId = $request->query('channel_id');
        $outcome = $request->query('outcome');
        $direction = $request->query('direction');
        $correlationId = $request->query('correlation_id');
        $activeTab = $request->query('tab', 'all');

        $query = SyncLog::with('channel')->orderBy('created_at', 'desc');

        // Apply Tab Filter
        if ($activeTab === 'attention') {
            $query->whereIn('outcome', [SyncOutcome::TransientFail, SyncOutcome::PermanentFail]);
        } elseif ($activeTab === 'reservations') {
            $query->where(fn($q) => $q->where('operation', 'like', '%reservation%')->orWhere('direction', 'in'));
        } elseif ($activeTab === 'availability_rates') {
            $query->where(fn($q) => $q->where('operation', 'like', '%avail%')->orWhere('operation', 'like', '%rate%')->orWhere('operation', 'like', '%inv%'));
        } elseif ($activeTab === 'stopsell') {
            $query->where('operation', 'like', '%stop%');
        }

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

        $logs = $query->paginate(20)->withQueryString();

        // Stats
        $totalLogs = SyncLog::count();
        $okLogs = SyncLog::where('outcome', SyncOutcome::Ok)->count();
        $failLogs = SyncLog::whereIn('outcome', [SyncOutcome::TransientFail, SyncOutcome::PermanentFail])->count();
        $successRate = $totalLogs > 0 ? round(($okLogs / $totalLogs) * 100, 1) : 100.0;
        $avgDuration = round((float) (SyncLog::avg('duration_ms') ?? 0));

        // Tab counts
        $tabCounts = [
            'all' => $totalLogs,
            'attention' => $failLogs,
            'reservations' => SyncLog::where(fn($q) => $q->where('operation', 'like', '%reservation%')->orWhere('direction', 'in'))->count(),
            'availability_rates' => SyncLog::where(fn($q) => $q->where('operation', 'like', '%avail%')->orWhere('operation', 'like', '%rate%')->orWhere('operation', 'like', '%inv%'))->count(),
            'stopsell' => SyncLog::where('operation', 'like', '%stop%')->count(),
        ];

        $channels = Channel::all();

        return view('extranet.sync_logs', [
            'hotel' => $hotel,
            'logs' => $logs,
            'channels' => $channels,
            'channelId' => $channelId,
            'outcome' => $outcome,
            'direction' => $direction,
            'correlationId' => $correlationId,
            'activeTab' => $activeTab,
            'tabCounts' => $tabCounts,
            'totalLogs' => $totalLogs,
            'successRate' => $successRate,
            'failLogs' => $failLogs,
            'avgDuration' => $avgDuration,
        ]);
    }

    public function triggerCheck(): RedirectResponse
    {
        Channel::query()->update([
            'consecutive_failures' => 0,
            'circuit_open_until' => null,
            'status' => ChannelStatus::Active,
        ]);
        ChannelSyncState::whereNotNull('blocked_at')->update([
            'blocked_at' => null,
            'attempts' => 0,
            'last_error' => null,
            'updated_at' => now(),
        ]);
        SyncKick::allChannels();

        return back()->with('success', 'Reset circuits and triggered live synchronization check across all active channel adapters.');
    }

    public function exportCsv(): StreamedResponse
    {
        $fileName = 'sync_audit_' . now()->format('Y-m-d_His') . '.csv';

        return response()->streamDownload(function () {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, ['ID', 'Timestamp (UTC)', 'Correlation ID', 'Channel', 'Direction', 'Operation', 'Status Code', 'Duration (ms)', 'Attempt', 'Outcome', 'Error']);

            SyncLog::with('channel')
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($logs) use ($handle) {
                    foreach ($logs as $l) {
                        fputcsv($handle, [
                            $l->id,
                            $l->created_at?->toIso8601String(),
                            $l->correlation_id,
                            $l->channel?->name ?? 'N/A',
                            $l->direction->value,
                            $l->operation,
                            $l->response_status ?? '',
                            $l->duration_ms,
                            $l->attempt,
                            $l->outcome->value,
                            $l->error ?? '',
                        ]);
                    }
                });

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv',
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
