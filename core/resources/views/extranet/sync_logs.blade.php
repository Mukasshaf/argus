@extends('layouts.app', ['title' => 'Channel Sync Logs'])

@section('content')
<div class="flex flex-col w-full max-w-7xl">
    <!-- Header & Action Hierarchy -->
    <div class="flex flex-col md:flex-row md:items-end justify-between gap-4 mb-5">
        <div class="flex items-center gap-3">
            <h1 class="font-heading-md font-semibold text-2xl lg:text-3xl text-primary tracking-tight" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">
                Sync Logs
            </h1>
            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-success-soft text-tertiary-container text-xs font-semibold border border-tertiary-container/20">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tertiary-container opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-tertiary-container"></span>
                </span>
                Live Stream
            </span>
        </div>

        <div class="flex items-center gap-2.5 pt-3 mt-2">
            <form action="{{ route('sync-logs.trigger-check') }}" method="POST">
                @csrf
                <button type="submit" class="h-9 px-3.5 bg-surface border border-border text-primary font-medium text-xs rounded-lg hover:bg-surface-container transition-colors flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-base text-secondary">refresh</span>
                    <span>Run Sync Check</span>
                </button>
            </form>
            <a href="{{ route('sync-logs.export') }}" class="h-9 px-3.5 bg-surface border border-border text-primary font-medium text-xs rounded-lg hover:bg-surface-container transition-colors flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-base text-secondary">download</span>
                <span>Export Audit Log</span>
            </a>
        </div>
    </div>

    <!-- Summary Row -->
    <div class="bg-surface rounded-xl border border-border p-4 mb-6 shadow-sm">
        <div class="grid grid-cols-2 md:grid-cols-4 divide-y md:divide-y-0 md:divide-x divide-border">
            <div class="p-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-surface-container flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-primary text-lg">sync_alt</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs text-secondary uppercase font-semibold">Total Operations</span>
                    <span class="text-base font-bold text-primary">{{ number_format($totalLogs) }}</span>
                </div>
            </div>

            <div class="p-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-success-soft flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-tertiary-container text-lg">check_circle</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs text-secondary uppercase font-semibold">Success Rate</span>
                    <span class="text-base font-bold text-primary">{{ $successRate }}%</span>
                </div>
            </div>

            <div class="p-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-danger-soft flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-danger text-lg">error</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs text-secondary uppercase font-semibold">Failures</span>
                    <span class="text-base font-bold text-danger">{{ $failLogs }}</span>
                </div>
            </div>

            <div class="p-3 flex items-center gap-3">
                <div class="w-9 h-9 rounded bg-surface-container flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-secondary text-lg">timer</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs text-secondary uppercase font-semibold">Avg Latency</span>
                    <span class="text-base font-bold text-primary">{{ $avgDuration }} ms</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Audit Log Ledger Container -->
    <div class="bg-surface rounded-xl border border-border shadow-sm flex flex-col overflow-hidden mb-6">
        <!-- 5-Segment Tab Navigation Bar -->
        <div class="px-4 pt-2 bg-surface-container-low flex items-center justify-between border-b border-border overflow-x-auto select-none">
            <div class="flex items-center space-x-1" role="tablist">
                <!-- All Events -->
                <a href="{{ route('sync-logs', array_merge(request()->query(), ['tab' => 'all', 'page' => 1])) }}"
                   class="px-3.5 py-2.5 rounded-t-lg {{ $activeTab === 'all' ? 'bg-surface text-primary font-semibold border-t border-x border-border relative' : 'text-secondary hover:text-primary hover:bg-surface-container font-medium' }} text-xs flex items-center gap-1.5 transition-colors">
                    <span>All Events</span>
                    <span class="text-[11px] font-semibold bg-surface-container px-1.5 py-0.5 rounded-full text-secondary">{{ number_format($tabCounts['all']) }}</span>
                    @if($activeTab === 'all')
                        <div class="absolute -bottom-px left-0 right-0 h-0.5 bg-primary"></div>
                    @endif
                </a>

                <!-- Needs Attention -->
                <a href="{{ route('sync-logs', array_merge(request()->query(), ['tab' => 'attention', 'page' => 1])) }}"
                   class="px-3.5 py-2.5 rounded-t-lg {{ $activeTab === 'attention' ? 'bg-surface text-danger font-semibold border-t border-x border-border relative' : 'text-danger font-medium hover:bg-danger-soft/30' }} text-xs flex items-center gap-1.5 transition-colors">
                    <span>Needs Attention</span>
                    <span class="text-[11px] font-bold bg-danger-soft text-danger px-1.5 py-0.5 rounded-full border border-danger/20">{{ $tabCounts['attention'] }}</span>
                    @if($activeTab === 'attention')
                        <div class="absolute -bottom-px left-0 right-0 h-0.5 bg-danger"></div>
                    @endif
                </a>

                <!-- Reservations -->
                <a href="{{ route('sync-logs', array_merge(request()->query(), ['tab' => 'reservations', 'page' => 1])) }}"
                   class="px-3.5 py-2.5 rounded-t-lg {{ $activeTab === 'reservations' ? 'bg-surface text-primary font-semibold border-t border-x border-border relative' : 'text-secondary hover:text-primary hover:bg-surface-container font-medium' }} text-xs flex items-center gap-1.5 transition-colors">
                    <span>Reservations</span>
                    <span class="text-[11px] bg-surface-container px-1.5 py-0.5 rounded-full text-secondary">{{ number_format($tabCounts['reservations']) }}</span>
                    @if($activeTab === 'reservations')
                        <div class="absolute -bottom-px left-0 right-0 h-0.5 bg-primary"></div>
                    @endif
                </a>

                <!-- Rates & Availability -->
                <a href="{{ route('sync-logs', array_merge(request()->query(), ['tab' => 'availability_rates', 'page' => 1])) }}"
                   class="px-3.5 py-2.5 rounded-t-lg {{ $activeTab === 'availability_rates' ? 'bg-surface text-primary font-semibold border-t border-x border-border relative' : 'text-secondary hover:text-primary hover:bg-surface-container font-medium' }} text-xs flex items-center gap-1.5 transition-colors">
                    <span>Rates &amp; Availability</span>
                    <span class="text-[11px] bg-surface-container px-1.5 py-0.5 rounded-full text-secondary">{{ number_format($tabCounts['availability_rates']) }}</span>
                    @if($activeTab === 'availability_rates')
                        <div class="absolute -bottom-px left-0 right-0 h-0.5 bg-primary"></div>
                    @endif
                </a>

                <!-- Stop-Sell Toggles -->
                <a href="{{ route('sync-logs', array_merge(request()->query(), ['tab' => 'stopsell', 'page' => 1])) }}"
                   class="px-3.5 py-2.5 rounded-t-lg {{ $activeTab === 'stopsell' ? 'bg-surface text-primary font-semibold border-t border-x border-border relative' : 'text-secondary hover:text-primary hover:bg-surface-container font-medium' }} text-xs flex items-center gap-1.5 transition-colors">
                    <span>Stop-Sell Toggles</span>
                    <span class="text-[11px] bg-surface-container px-1.5 py-0.5 rounded-full text-secondary">{{ number_format($tabCounts['stopsell']) }}</span>
                    @if($activeTab === 'stopsell')
                        <div class="absolute -bottom-px left-0 right-0 h-0.5 bg-primary"></div>
                    @endif
                </a>
            </div>

            <div class="hidden lg:flex items-center gap-2 text-secondary text-xs pr-2">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-tertiary-container opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-tertiary-container"></span>
                </span>
                <span class="font-data-mono text-[11px]">Level-triggered sync active</span>
            </div>
        </div>

        <!-- Filters Bar Inside Ledger Container -->
        <div class="p-3.5 bg-surface border-b border-border">
            <form method="GET" action="{{ route('sync-logs') }}" class="flex flex-wrap items-center gap-3">
                <input type="hidden" name="tab" value="{{ $activeTab }}">

                <div class="relative w-64">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-secondary text-base">search</span>
                    <input type="text" name="correlation_id" value="{{ $correlationId }}" placeholder="Filter correlation ID..."
                           class="h-8 pl-8 pr-3 bg-surface-container-low text-xs text-primary rounded border border-border focus:outline-none w-full">
                </div>

                <div class="relative">
                    <select name="channel_id" onchange="this.form.submit()" class="h-8 pl-3 pr-7 bg-surface-container-low text-xs text-primary rounded border border-border cursor-pointer">
                        <option value="">All Channels</option>
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}" {{ $channelId == $ch->id ? 'selected' : '' }}>{{ $ch->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="relative">
                    <select name="outcome" onchange="this.form.submit()" class="h-8 pl-3 pr-7 bg-surface-container-low text-xs text-primary rounded border border-border appearance-none cursor-pointer">
                        <option value="all">All Outcomes</option>
                        <option value="ok" {{ $outcome === 'ok' ? 'selected' : '' }}>OK (Success)</option>
                        <option value="transient_fail" {{ $outcome === 'transient_fail' ? 'selected' : '' }}>Transient Fail</option>
                        <option value="permanent_fail" {{ $outcome === 'permanent_fail' ? 'selected' : '' }}>Permanent Fail</option>
                    </select>
                </div>

                <div class="relative">
                    <select name="direction" onchange="this.form.submit()" class="h-8 pl-3 pr-7 bg-surface-container-low text-xs text-primary rounded border border-border appearance-none cursor-pointer">
                        <option value="all">All Directions</option>
                        <option value="out" {{ $direction === 'out' ? 'selected' : '' }}>Outbound (Push)</option>
                        <option value="in" {{ $direction === 'in' ? 'selected' : '' }}>Inbound (Webhook/Poll)</option>
                    </select>
                </div>

                @if($channelId || $outcome || $direction || $correlationId)
                    <a href="{{ route('sync-logs', ['tab' => $activeTab]) }}" class="h-8 px-2 text-secondary hover:text-primary text-xs flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">filter_list_off</span>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-surface-container text-secondary uppercase font-semibold border-b border-border">
                        <th class="py-2.5 px-4">Time (UTC)</th>
                        <th class="py-2.5 px-4">Correlation ID</th>
                        <th class="py-2.5 px-4">Channel</th>
                        <th class="py-2.5 px-4">Direction &amp; Op</th>
                        <th class="py-2.5 px-4 text-center">HTTP Status</th>
                        <th class="py-2.5 px-4 text-right">Latency</th>
                        <th class="py-2.5 px-4 text-center">Outcome</th>
                        <th class="py-2.5 px-4 text-right">Inspect</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse($logs as $log)
                        <tr class="hover:bg-surface-container-low transition-colors">
                            <td class="py-2.5 px-4 font-data-mono text-secondary">{{ $log->created_at->format('H:i:s') }}</td>
                            <td class="py-2.5 px-4 font-data-mono text-primary truncate max-w-[140px]" title="{{ $log->correlation_id }}">
                                {{ substr($log->correlation_id, 0, 13) }}...
                            </td>
                            <td class="py-2.5 px-4 font-semibold text-primary">{{ $log->channel->name ?? 'Channel' }}</td>
                            <td class="py-2.5 px-4">
                                <span class="px-1.5 py-0.5 rounded text-[10px] uppercase font-bold {{ $log->direction->value === 'out' ? 'bg-primary/10 text-primary' : 'bg-tertiary-container/10 text-tertiary-container' }}">
                                    {{ $log->direction->value }}
                                </span>
                                <span class="ml-1 text-secondary">{{ $log->operation }}</span>
                            </td>
                            <td class="py-2.5 px-4 text-center font-data-mono">
                                @if($log->response_status)
                                    <span class="px-1.5 py-0.5 rounded text-[11px] font-semibold {{ $log->response_status >= 200 && $log->response_status < 300 ? 'bg-success-soft text-tertiary-container' : 'bg-danger-soft text-danger' }}">
                                        {{ $log->response_status }}
                                    </span>
                                @else
                                    <span class="text-secondary">—</span>
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-right font-data-mono">{{ $log->duration_ms }} ms</td>
                            <td class="py-2.5 px-4 text-center">
                                @if($log->outcome->value === 'ok')
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded bg-success-soft text-tertiary-container font-semibold text-[11px]">
                                        <span class="material-symbols-outlined text-xs">check</span>OK
                                    </span>
                                @elseif($log->outcome->value === 'transient_fail')
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded bg-warning-soft text-warning font-semibold text-[11px]">
                                        <span class="material-symbols-outlined text-xs">refresh</span>Retry
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-0.5 px-2 py-0.5 rounded bg-danger-soft text-danger font-semibold text-[11px]">
                                        <span class="material-symbols-outlined text-xs">block</span>Blocked
                                    </span>
                                @endif
                            </td>
                            <td class="py-2.5 px-4 text-right">
                                <button type="button" onclick="inspectLog({{ $log->id }})" class="h-6 px-2 bg-surface border border-border text-secondary hover:text-primary rounded text-[11px]">
                                    Inspect
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="py-12 text-center text-secondary">
                                <span class="material-symbols-outlined text-3xl mb-1 text-secondary">list_alt</span>
                                <p class="font-semibold text-primary">No sync log entries recorded.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-4 py-3 bg-surface-container-low flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-border">
            <div class="text-xs text-secondary">
                Showing <span class="font-semibold text-primary">{{ $logs->firstItem() ?? 0 }} to {{ $logs->lastItem() ?? 0 }}</span> of <span class="font-semibold text-primary">{{ $logs->total() }}</span> logs
            </div>
            <div>
                {{ $logs->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>

<!-- Inspect Drawer Modal -->
<div id="logInspectModal" class="hidden fixed inset-0 bg-primary/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-xl shadow-xl border border-border w-full max-w-3xl overflow-hidden flex flex-col max-h-[85vh]">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <div class="flex items-center gap-2">
                <h3 class="font-heading-sm text-base font-semibold text-primary" id="inspectTitle">Log Details</h3>
                <span id="inspectOutcomeBadge" class="px-2 py-0.5 rounded text-xs font-bold"></span>
            </div>
            <button type="button" onclick="$('#logInspectModal').addClass('hidden')" class="text-secondary hover:text-primary">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <div class="p-6 overflow-y-auto space-y-4">
            <div class="grid grid-cols-2 gap-4 text-xs bg-surface-container-low p-3 rounded border border-border">
                <div><span class="text-secondary font-semibold">Channel:</span> <span id="inspectChannel" class="font-medium text-primary"></span></div>
                <div><span class="text-secondary font-semibold">Operation:</span> <span id="inspectOperation" class="font-medium text-primary"></span></div>
                <div><span class="text-secondary font-semibold">Correlation ID:</span> <span id="inspectCorrelation" class="font-data-mono text-primary"></span></div>
                <div><span class="text-secondary font-semibold">Latency / Status:</span> <span id="inspectMeta" class="font-medium text-primary"></span></div>
            </div>

            <div id="inspectErrorContainer" class="hidden p-3 rounded bg-danger-soft border border-danger/30 text-danger text-xs font-mono"></div>

            <div>
                <label class="block text-xs font-semibold uppercase text-secondary mb-1">Request Payload (Redacted)</label>
                <pre id="inspectRequest" class="p-3 bg-primary text-[#ECE9E1] rounded text-[11px] font-mono overflow-x-auto max-h-56"></pre>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase text-secondary mb-1">Response Payload</label>
                <pre id="inspectResponse" class="p-3 bg-surface-container-high text-primary rounded text-[11px] font-mono overflow-x-auto max-h-56"></pre>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    function inspectLog(id) {
        $.getJSON('/extranet/sync-logs/' + id, function(data) {
            $('#inspectTitle').text('Sync Log #' + data.id + ' — ' + data.operation);
            $('#inspectChannel').text(data.channel + ' (' + data.channel_code + ')');
            $('#inspectOperation').text(data.direction.toUpperCase() + ' · ' + data.operation);
            $('#inspectCorrelation').text(data.correlation_id);
            $('#inspectMeta').text(data.duration_ms + ' ms | HTTP ' + (data.response_status || 'N/A'));

            $('#inspectOutcomeBadge')
                .text(data.outcome.toUpperCase())
                .removeClass('bg-success-soft text-tertiary-container bg-warning-soft text-warning bg-danger-soft text-danger')
                .addClass(data.outcome === 'ok' ? 'bg-success-soft text-tertiary-container' : 'bg-danger-soft text-danger');

            if (data.error) {
                $('#inspectErrorContainer').removeClass('hidden').text('Error: ' + data.error);
            } else {
                $('#inspectErrorContainer').addClass('hidden');
            }

            $('#inspectRequest').text(data.request_body || '(No request body)');
            $('#inspectResponse').text(data.response_body || '(No response body)');

            $('#logInspectModal').removeClass('hidden');
        });
    }
</script>
@endpush
