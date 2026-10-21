@extends('layouts.app', ['title' => 'Operational Alerts'])

@section('content')
<div class="flex flex-col w-full max-w-7xl space-y-6">
    <!-- Header & Top Page Controls -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-2 border-b border-border">
        <div class="flex flex-col">
            <div class="flex items-center gap-2 mb-1">
                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full bg-success-soft text-tertiary-container text-xs font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse"></span>
                    Live Triage Active
                </span>
                <span class="text-secondary text-sm">•</span>
                <span class="text-secondary text-xs">{{ $openCount }} open alerts require attention</span>
            </div>
            <h1 class="text-2xl font-semibold text-primary tracking-tight" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">
                Operational Alerts
            </h1>
        </div>
    </div>

    <!-- Slim Horizontal Summary Bar -->
    <div class="grid grid-cols-2 md:grid-cols-4 bg-surface rounded-xl border border-border shadow-sm divide-y md:divide-y-0 md:divide-x divide-border">
        <div class="p-3.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-danger-soft text-danger flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">error</span>
            </div>
            <div class="flex flex-col min-w-0">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-semibold text-primary text-base">{{ $criticalCount }} Critical</span>
                </div>
                <span class="text-[11px] text-secondary truncate">Oversold Deficit</span>
            </div>
        </div>

        <div class="p-3.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-warning-soft text-warning flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">warning</span>
            </div>
            <div class="flex flex-col min-w-0">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-semibold text-primary text-base">{{ $warningCount }} Warnings</span>
                </div>
                <span class="text-[11px] text-secondary truncate">Blocked / Drift / Mappings</span>
            </div>
        </div>

        <div class="p-3.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-success-soft text-tertiary-container flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">task_alt</span>
            </div>
            <div class="flex flex-col min-w-0">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-semibold text-primary text-base">{{ $resolvedCount }} Resolved</span>
                </div>
                <span class="text-[11px] text-secondary truncate">Auto-cured or operator closed</span>
            </div>
        </div>

        <div class="p-3.5 flex items-center gap-3">
            <div class="w-9 h-9 rounded bg-surface-container-low text-secondary flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-lg">hub</span>
            </div>
            <div class="flex flex-col min-w-0">
                <div class="flex items-baseline gap-1.5">
                    <span class="font-semibold text-primary text-base">3 Feeds</span>
                </div>
                <span class="text-[11px] text-secondary truncate">Raahi, Sarai, Mela</span>
            </div>
        </div>
    </div>

    <!-- Filter & Triage Bar -->
    <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 p-2 bg-surface rounded-xl border border-border shadow-sm">
        <div class="flex flex-wrap items-center gap-1.5">
            <a href="{{ route('alerts', array_merge(request()->query(), ['filter' => 'open'])) }}"
               class="h-8 px-3 rounded text-xs font-medium transition-colors flex items-center gap-1.5 {{ $filter === 'open' ? 'bg-primary text-on-primary' : 'bg-surface hover:bg-surface-container text-secondary' }}">
                <span>All Open</span>
                <span class="px-1.5 py-0.2 rounded-full bg-surface/20 text-xs">{{ $openCount }}</span>
            </a>
            <a href="{{ route('alerts', array_merge(request()->query(), ['filter' => 'critical'])) }}"
               class="h-8 px-3 rounded text-xs font-medium transition-colors flex items-center gap-1.5 {{ $filter === 'critical' ? 'bg-danger text-on-error' : 'bg-surface hover:bg-surface-container text-danger border border-transparent hover:border-border' }}">
                <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                <span>Critical ({{ $criticalCount }})</span>
            </a>
            <a href="{{ route('alerts', array_merge(request()->query(), ['filter' => 'warning'])) }}"
               class="h-8 px-3 rounded text-xs font-medium transition-colors flex items-center gap-1.5 {{ $filter === 'warning' ? 'bg-warning text-on-primary' : 'bg-surface hover:bg-surface-container text-warning border border-transparent hover:border-border' }}">
                <span class="w-1.5 h-1.5 rounded-full bg-warning"></span>
                <span>Warnings ({{ $warningCount }})</span>
            </a>
            <a href="{{ route('alerts', array_merge(request()->query(), ['filter' => 'resolved'])) }}"
               class="h-8 px-3 rounded text-xs font-medium transition-colors border border-transparent hover:border-border {{ $filter === 'resolved' ? 'bg-secondary text-on-primary' : 'bg-surface hover:bg-surface-container text-secondary' }}">
                <span>Recently Resolved ({{ $resolvedCount }})</span>
            </a>
        </div>

        <form method="GET" action="{{ route('alerts') }}" class="flex items-center gap-2">
            <input type="hidden" name="filter" value="{{ $filter }}">
            <div class="relative w-64">
                <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-secondary text-base">search</span>
                <input type="text" name="search" value="{{ $search }}" placeholder="Search alert impact, room..."
                       class="h-8 pl-8 pr-3 bg-surface-container-low text-xs text-primary rounded border border-border focus:outline-none w-full">
            </div>
            @if($search)
                <a href="{{ route('alerts', ['filter' => $filter]) }}" class="h-8 px-2 text-secondary hover:text-primary text-xs flex items-center">
                    <span class="material-symbols-outlined text-sm">close</span>
                </a>
            @endif
        </form>
    </div>

    <!-- Scannable Open Alerts List -->
    <div class="space-y-3 mb-8">
        @forelse($alerts as $alert)
            @php
                $isCritical = $alert->type->value === 'oversold';
                $isResolved = $alert->resolved_at !== null;
                $payload = $alert->payload ?? [];
            @endphp
            <div class="bg-surface rounded-xl border-l-4 {{ $isResolved ? 'border-l-secondary opacity-60' : ($isCritical ? 'border-l-danger' : 'border-l-warning') }} border-y border-r border-border p-4 sm:p-5 shadow-sm transition-shadow hover:shadow-md flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3.5 min-w-0 max-w-3xl">
                    <div class="w-9 h-9 rounded {{ $isCritical ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning' }} flex items-center justify-center shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-xl">
                            @if($alert->type->value === 'oversold') error
                            @elseif($alert->type->value === 'channel_blocked') lock
                            @elseif($alert->type->value === 'drift') sync_problem
                            @else help_outline
                            @endif
                        </span>
                    </div>
                    <div class="flex flex-col min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full {{ $isCritical ? 'bg-danger-soft text-danger' : 'bg-warning-soft text-warning' }} text-[11px] font-semibold uppercase tracking-wider">
                                {{ strtoupper($alert->type->value) }}
                            </span>
                            <span class="text-xs text-secondary">{{ $alert->created_at->format('M d, Y · H:i') }} (UTC)</span>
                            @if($alert->acknowledged_at)
                                <span class="text-xs text-secondary font-medium">• Acknowledged</span>
                            @endif
                            @if($isResolved)
                                <span class="text-xs text-tertiary-container font-semibold">• Resolved</span>
                            @endif
                        </div>

                        <h2 class="font-title-md text-sm text-primary font-semibold tracking-tight">
                            @if($alert->type->value === 'oversold')
                                Oversold Inventory Deficit (Available: {{ $payload['available'] ?? -1 }})
                            @elseif($alert->type->value === 'channel_blocked')
                                Channel Quarantined: {{ $payload['channel_code'] ?? 'Channel' }}
                            @elseif($alert->type->value === 'unmapped_code')
                                Unmapped Channel Code: {{ $payload['code'] ?? 'Unknown' }}
                            @elseif($alert->type->value === 'drift')
                                Ledger Drift Detected (Room: {{ $payload['room_type_id'] ?? '' }}, Date: {{ $payload['stay_date'] ?? '' }})
                            @endif
                        </h2>

                        <p class="mt-2 text-xs text-on-surface bg-surface-container-low px-3 py-1.5 rounded border border-border/80">
                            <strong>Details:</strong>
                            {{ $payload['message'] ?? $payload['error'] ?? json_encode($payload) }}
                        </p>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-2 self-start md:self-center shrink-0 flex-wrap">
                    @if($alert->type->value === 'oversold')
                        <a href="{{ route('reservations', ['tab' => 'oversold']) }}" class="h-8 px-3 rounded bg-danger-soft text-danger hover:bg-danger hover:text-on-error border border-danger/30 text-xs font-semibold transition-colors flex items-center gap-1 shadow-xs">
                            <span class="material-symbols-outlined text-xs">swap_horiz</span>
                            <span>Review Deficit</span>
                        </a>
                    @elseif($alert->type->value === 'channel_blocked' || $alert->type->value === 'unmapped_code')
                        <a href="{{ route('channels') }}" class="h-8 px-3 rounded bg-warning-soft text-warning hover:bg-warning hover:text-on-primary border border-warning/30 text-xs font-semibold transition-colors flex items-center gap-1 shadow-xs">
                            <span class="material-symbols-outlined text-xs">schema</span>
                            <span>Manage Mappings</span>
                        </a>
                    @elseif($alert->type->value === 'drift')
                        <a href="{{ route('calendar') }}" class="h-8 px-3 rounded bg-surface-container text-primary hover:bg-surface-container-high border border-border text-xs font-medium transition-colors flex items-center gap-1 shadow-xs">
                            <span class="material-symbols-outlined text-xs">calendar_month</span>
                            <span>Check Calendar</span>
                        </a>
                    @endif

                    @if(!$alert->acknowledged_at && !$isResolved)
                        <form action="{{ route('alerts.acknowledge', $alert->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="h-8 px-3 rounded bg-surface border border-border text-secondary hover:text-primary text-xs font-medium transition-colors">
                                Acknowledge
                            </button>
                        </form>
                    @endif

                    @if(!$isResolved)
                        <form action="{{ route('alerts.resolve', $alert->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="h-8 px-3 rounded bg-tertiary-container text-on-primary hover:bg-primary text-xs font-semibold transition-colors flex items-center gap-1 shadow-sm">
                                <span class="material-symbols-outlined text-xs">task_alt</span>
                                <span>Resolve</span>
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="py-12 bg-surface rounded-xl border border-border text-center text-secondary">
                <span class="material-symbols-outlined text-3xl mb-1 text-[#4C7168]">verified</span>
                <p class="font-semibold text-primary">No alerts found for this filter.</p>
                <p class="text-xs">All channels and inventory ledgers are operational.</p>
            </div>
        @endforelse
    </div>

    <div>
        {{ $alerts->withQueryString()->links() }}
    </div>
</div>
@endsection
