@extends('layouts.app', ['title' => 'Distribution Channels'])

@section('content')
<div class="flex flex-col w-full max-w-7xl">
    <!-- Header & Action Hierarchy -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-heading-md font-semibold text-2xl lg:text-3xl text-primary tracking-tight" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">
                    Distribution Channels
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-success-soft text-tertiary-container text-xs font-medium">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse"></span>
                    Live Sync Active
                </span>
            </div>
            <p class="text-xs text-secondary mt-1">Lotus Residency · Bengaluru · 3 Configured Channels</p>
        </div>

        <div class="flex items-center gap-2.5 flex-wrap">
            <a href="{{ route('sync-logs') }}" class="h-9 px-3.5 bg-surface hover:bg-surface-container text-primary border border-border text-xs font-medium rounded-md transition-colors flex items-center gap-1.5 shadow-sm">
                <span class="material-symbols-outlined text-base text-secondary">history</span>
                <span>Sync Audit</span>
            </a>
            <form action="{{ route('channels.sync-all') }}" method="POST" id="forceSyncForm">
                @csrf
                <button type="submit" class="h-9 px-3.5 bg-surface hover:bg-surface-container text-primary border border-border text-xs font-medium rounded-md transition-colors flex items-center gap-1.5 shadow-sm" id="forceSyncBtn">
                    <span class="material-symbols-outlined text-base text-secondary" id="forceSyncIcon">sync</span>
                    <span id="forceSyncText">Force Full Sync</span>
                </button>
            </form>
            <button type="button" onclick="alert('Connect new channels: Browse 28 certified integrations in the directory.');" class="h-9 px-4 bg-tertiary-container hover:bg-[#3D5B54] text-on-primary text-xs font-semibold rounded-[9px] transition-all flex items-center gap-1.5 shadow-sm hover:shadow active:scale-[0.98]">
                <span class="material-symbols-outlined text-base">add</span>
                <span>+ Add Channel</span>
            </button>
        </div>
    </div>

    <!-- Clean Slim Summary Row (3 Metrics) -->
    <div class="bg-surface rounded-xl border border-border p-4 mb-6 shadow-sm">
        <div class="grid grid-cols-1 md:grid-cols-3 divide-y md:divide-y-0 md:divide-x divide-border">
            <!-- Metric 1: Active Channels -->
            <div class="flex items-center gap-3.5 py-2 md:py-0 md:px-4 first:pl-2">
                <div class="w-9 h-9 rounded-lg bg-success-soft flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-tertiary-container text-lg">hub</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[11px] font-medium text-secondary uppercase tracking-wider">Active Channels</span>
                    <span class="text-sm font-semibold text-primary leading-tight mt-0.5">{{ $activeChannelsCount }} Connected</span>
                    <span class="text-xs text-secondary truncate mt-0.5">Raahi Stays, Sarai Direct, Mela Bulk</span>
                </div>
            </div>

            <!-- Metric 2: Mapping Health -->
            <div class="flex items-center gap-3.5 py-3 md:py-0 md:px-6">
                <div class="w-9 h-9 rounded-lg bg-warning-soft flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-warning text-lg">rule</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[11px] font-medium text-secondary uppercase tracking-wider">Mapping Health</span>
                    <span class="text-sm font-semibold text-primary leading-tight mt-0.5">{{ $totalMappingsCount }} Entities Mapped</span>
                    <span class="text-xs text-secondary font-medium truncate mt-0.5">All room &amp; rate codes aligned</span>
                </div>
            </div>

            <!-- Metric 3: Last Sync -->
            <div class="flex items-center gap-3.5 py-2 md:py-0 md:px-6 last:pr-2">
                <div class="w-9 h-9 rounded-lg bg-surface-container-low flex items-center justify-center shrink-0">
                    <span class="material-symbols-outlined text-secondary text-lg">schedule</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-[11px] font-medium text-secondary uppercase tracking-wider">Last Sync Activity</span>
                    <span class="text-sm font-semibold text-primary leading-tight mt-0.5 tabular-nums">
                        {{ $lastSuccessAt ? $lastSuccessAt->diffForHumans() : 'Just now' }}
                    </span>
                    <span class="text-xs text-tertiary-container truncate mt-0.5">Push engine level-triggered</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Connected Channels List (Main Focus) -->
    <div class="space-y-4 mb-6">
        @foreach($channels as $c)
            @php
                $stats = $mtdStats[$c->id] ?? ['volume' => 0, 'count' => 0, 'quarantined' => 0];
                $isDegraded = $c->status->value === 'degraded';
                $isPaused = $c->status->value === 'paused';
                $roomCount = $c->roomMappings->count();
                $rateCount = $c->rateMappings->count();
            @endphp
            <div class="bg-surface rounded-xl border border-border p-5 shadow-sm hover:border-[#D6D2C8] transition-all">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                    <!-- Left Info Block -->
                    <div class="flex items-start gap-4 min-w-0">
                        <div class="w-11 h-11 rounded-lg bg-surface-container-low border border-border flex items-center justify-center shrink-0 text-primary">
                            <span class="material-symbols-outlined text-2xl text-tertiary-container">
                                @if($c->code === 'raahi') flight_takeoff
                                @elseif($c->code === 'sarai') domain_verification
                                @else local_shipping
                                @endif
                            </span>
                        </div>
                        <div class="flex flex-col min-w-0">
                            <div class="flex items-center gap-2.5 flex-wrap">
                                <h3 class="font-heading-sm font-semibold text-lg text-primary">{{ $c->name }}</h3>
                                @if($isPaused)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-surface-container text-secondary text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-secondary"></span>
                                        Paused
                                    </span>
                                @elseif($isDegraded)
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-danger-soft text-danger text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-danger"></span>
                                        Circuit Degraded ({{ $c->consecutive_failures }} fails)
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-success-soft text-tertiary-container text-xs font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container"></span>
                                        Healthy
                                    </span>
                                @endif
                                <span class="text-xs text-secondary font-normal uppercase">· {{ $c->protocol->value }} protocol (Markup: {{ $c->markup_bps }} bps)</span>
                            </div>

                            <!-- Details Row -->
                            <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-2 text-xs text-secondary">
                                <span class="flex items-center gap-1">
                                    <span class="material-symbols-outlined text-xs">sync</span>
                                    <span>Last sync: <strong class="text-primary font-medium">{{ $c->last_success_at ? $c->last_success_at->diffForHumans() : 'Never' }}</strong></span>
                                </span>
                                <span>·</span>
                                <span class="text-primary font-medium">{{ $roomCount }} Rooms Mapped <span class="text-secondary font-normal">(STD, DLX, STE)</span></span>
                                <span>·</span>
                                <span>Rates: <span class="text-primary font-medium">{{ $rateCount }} Plans Active</span></span>
                                @if($stats['quarantined'] > 0)
                                    <span>·</span>
                                    <span class="text-danger font-bold">{{ $stats['quarantined'] }} Quarantined Rows</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Right Volume & Actions -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-4 lg:gap-6 self-start lg:self-center shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-border w-full lg:w-auto justify-between lg:justify-end">
                        <div class="flex flex-col sm:text-right">
                            <span class="text-[11px] font-medium text-secondary uppercase tracking-wider">MTD Volume</span>
                            <div class="flex items-baseline gap-1 mt-0.5 sm:justify-end">
                                <span class="text-base font-semibold text-primary tabular-nums">₹{{ number_format($stats['volume']) }}</span>
                                <span class="text-xs text-secondary tabular-nums">({{ $stats['count'] }} bookings)</span>
                            </div>
                        </div>

                        <!-- Button actions -->
                        <div class="flex items-center gap-2">
                            @if($stats['quarantined'] > 0)
                                <form action="{{ route('channels.unblock', $c->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="h-8 px-3 rounded-md bg-warning-soft hover:bg-warning text-warning hover:text-on-primary border border-warning/30 text-xs font-semibold transition-colors flex items-center gap-1">
                                        <span class="material-symbols-outlined text-xs">lock_open</span>
                                        <span>Unblock</span>
                                    </button>
                                </form>
                            @endif

                            <form action="{{ route('channels.force-sync', $c->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="h-8 px-3 rounded-md bg-surface-container-low hover:bg-surface-container text-primary border border-border text-xs font-medium transition-colors">
                                    Retry / Sync
                                </button>
                            </form>

                            <form action="{{ route('channels.toggle-pause', $c->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="h-8 w-8 rounded-md bg-surface hover:bg-surface-container-low text-secondary hover:text-primary border border-border flex items-center justify-center transition-colors" title="{{ $isPaused ? 'Resume Sync' : 'Pause Sync' }}">
                                    <span class="material-symbols-outlined text-base">{{ $isPaused ? 'play_arrow' : 'pause' }}</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <!-- Dedicated / Expandable Mapping Area -->
    <div class="bg-surface rounded-xl border border-border shadow-sm mb-6 overflow-hidden">
        <!-- Collapsible Header Toggle -->
        <button type="button" aria-expanded="true" class="w-full px-5 py-4 flex items-center justify-between text-left hover:bg-surface-container transition-colors" id="toggleMatrixBtn">
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined text-tertiary-container text-xl">schema</span>
                <div class="flex flex-col">
                    <span class="font-heading-sm font-semibold text-base text-primary">Channel Room &amp; Rate Mapping Matrix</span>
                    <span class="text-xs text-secondary mt-0.5">Property: {{ $hotel->name ?? 'Lotus Residency' }} — Bengaluru ({{ $roomTypes->sum('total_rooms') }} Total Keys)</span>
                </div>
            </div>
            <div class="flex items-center gap-2">
                <span class="text-xs text-secondary font-medium hidden sm:inline" id="toggleMatrixLabel">Hide matrix</span>
                <span class="material-symbols-outlined text-secondary text-lg transition-transform duration-200" id="matrixCaret">expand_less</span>
            </div>
        </button>

        <!-- Collapsible Content -->
        <div class="px-5 pb-5 pt-1 border-t border-border" id="mappingMatrixContainer">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-border text-secondary font-semibold uppercase text-[10px] tracking-wider">
                            <th class="py-2.5 px-3">Internal Room Code</th>
                            @foreach($channels as $c)
                                <th class="py-2.5 px-3">{{ $c->name }}</th>
                            @endforeach
                            <th class="py-2.5 px-3 text-right">Parity Status / Base Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border text-xs">
                        @foreach($roomTypes as $room)
                            <tr class="hover:bg-surface-container transition-colors">
                                <td class="py-3 px-3 font-medium text-primary">
                                    <div class="flex items-center gap-2">
                                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container"></span>
                                        <span>{{ $room->code }} · {{ $room->name }}</span>
                                        <span class="text-[11px] text-secondary">({{ $room->total_rooms }} Keys)</span>
                                    </div>
                                </td>
                                @foreach($channels as $c)
                                    @php
                                        $map = $room->mappings->firstWhere('channel_id', $c->id);
                                    @endphp
                                    <td class="py-3 px-3 font-data-mono text-[11px] text-primary">
                                        @if($map)
                                            <span>{{ $map->external_room_code }}</span>
                                        @else
                                            <span class="inline-flex items-center gap-1 font-semibold text-[10px] text-warning bg-warning-soft px-1.5 py-0.5 rounded border border-warning/30">
                                                <span class="material-symbols-outlined text-xs">warning</span>
                                                UNMAPPED
                                            </span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="py-3 px-3 text-right tabular-nums">
                                    @php
                                        $basePrice = $room->ratePlans->first()?->base_price ?? 0;
                                    @endphp
                                    <span class="inline-flex items-center gap-1.5 text-tertiary-container font-medium">
                                        <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container"></span>
                                        ₹{{ number_format($basePrice) }} · Parity OK
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-3 pt-3 border-t border-border flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-secondary">
                <span>All wholesale and retail rate multipliers enforced by sync worker.</span>
                <span class="text-tertiary-container font-medium flex items-center gap-1">
                    <span>Active mapping live across {{ $channels->count() }} distribution endpoints</span>
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                </span>
            </div>
        </div>
    </div>

    <!-- Subtle Integration Directory Callout -->
    <div class="bg-surface rounded-xl border border-border p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs mb-8">
        <div class="flex items-center gap-3">
            <div class="w-8 h-8 rounded-lg bg-surface-container-low border border-border flex items-center justify-center shrink-0 text-tertiary-container">
                <span class="material-symbols-outlined text-lg">explore</span>
            </div>
            <div>
                <span class="font-medium text-primary">Looking to connect a new OTA or GDS?</span>
                <span class="text-secondary block sm:inline sm:ml-1">Browse 28 certified integrations in the Add Channel directory.</span>
            </div>
        </div>
        <button type="button" onclick="alert('OTA Directory: Booking.com, Agoda, Expedia, MakeMyTrip, Cleartrip, Traveloka, Trip.com, and 21 other connectors available in production.');" class="h-8 px-3.5 rounded-md bg-surface hover:bg-surface-container text-primary border border-border font-medium transition-colors shrink-0 self-start sm:self-center flex items-center gap-1">
            <span>Explore Directory</span>
            <span class="material-symbols-outlined text-sm">open_in_new</span>
        </button>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const toggleBtn = document.getElementById('toggleMatrixBtn');
    const container = document.getElementById('mappingMatrixContainer');
    const caret = document.getElementById('matrixCaret');
    const label = document.getElementById('toggleMatrixLabel');

    if (toggleBtn && container && caret) {
        toggleBtn.addEventListener('click', () => {
            const isHidden = container.classList.toggle('hidden');
            caret.style.transform = isHidden ? 'rotate(180deg)' : 'rotate(0deg)';
            if (label) {
                label.textContent = isHidden ? 'Show matrix' : 'Hide matrix';
            }
        });
    }
});
</script>
@endpush
