@extends('layouts.app', ['title' => 'Inventory & Rate Calendar'])

@section('content')
<div class="flex flex-col w-full">
    <!-- Top Utility Context Bar -->
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
        <div class="flex flex-col gap-1">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-heading-md text-2xl font-semibold text-primary tracking-tight whitespace-nowrap" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">
                    Inventory &amp; Rate Calendar
                </h1>
            </div>
            <div class="flex items-center gap-2 text-secondary text-xs">
                <span class="font-medium text-tertiary-container">Showing {{ $startDate->format('M d') }} – {{ $endDate->format('M d, Y') }}</span>
            </div>
        </div>

        <!-- Sliding Window Navigation Controls -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="flex items-center bg-surface rounded-lg shadow-sm border border-border p-0.5 shrink-0">
                <a href="{{ route('calendar', ['start_date' => $startDate->copy()->subDays(10)->toDateString()]) }}"
                   class="h-8 px-2.5 flex items-center gap-1 text-xs font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-colors"
                   title="Slide Previous 10 Days">
                    <span class="material-symbols-outlined text-base">chevron_left</span>
                    <span>Prev 10 Days</span>
                </a>
                <div class="h-4 w-px bg-border mx-1"></div>
                <a href="{{ route('calendar', ['start_date' => $today->toDateString()]) }}"
                   class="h-8 px-3 flex items-center gap-1.5 text-xs font-semibold text-primary {{ $startDate->isSameDay($today) ? 'bg-surface-container-low' : '' }} rounded">
                    <span class="material-symbols-outlined text-base text-tertiary-container">today</span>
                    <span>{{ $startDate->format('M d') }} – {{ $endDate->format('d') }}</span>
                    @if($startDate->isSameDay($today))
                        <span class="px-1.5 py-0.5 rounded bg-tertiary-container text-on-primary text-[10px] uppercase font-bold">TODAY</span>
                    @endif
                </a>
                <div class="h-4 w-px bg-border mx-1"></div>
                <a href="{{ route('calendar', ['start_date' => $startDate->copy()->addDays(10)->toDateString()]) }}"
                   class="h-8 px-2.5 flex items-center gap-1 text-xs font-medium text-on-surface-variant hover:text-on-surface hover:bg-surface-container rounded transition-colors"
                   title="Slide Next 10 Days">
                    <span>Next 10 Days</span>
                    <span class="material-symbols-outlined text-base">chevron_right</span>
                </a>
            </div>

            <div class="flex items-center gap-2">
                <div class="relative">
                    <select id="filterRoomsSelect" onchange="filterRooms(this.value)" class="h-8 pl-2.5 pr-3 bg-surface hover:bg-surface-container text-on-surface text-xs rounded-lg border border-border shadow-sm cursor-pointer focus:outline-none font-medium">
                        <option value="all">Filter Rooms: All ({{ $roomTypes->count() }})</option>
                        @foreach($roomTypes as $r)
                            <option value="{{ $r->code }}">{{ $r->name }} ({{ $r->code }})</option>
                        @endforeach
                    </select>
                </div>

                <button type="button" onclick="$('#bulkEditModal').removeClass('hidden')"
                        class="h-8 px-4 bg-tertiary-container hover:bg-primary text-on-primary font-semibold text-xs rounded-lg shadow-sm flex items-center gap-1.5 transition-colors shrink-0">
                    <span class="material-symbols-outlined text-base">edit_calendar</span>
                    <span>Bulk Edit</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Operational KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <!-- 1. Window Occupancy -->
        <div class="bg-surface rounded-xl p-4 shadow-sm border border-border flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-secondary">Window Occupancy</span>
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-success-soft text-tertiary text-xs font-semibold">
                    <span class="material-symbols-outlined text-xs">trending_up</span>+3.4% MoM
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="font-heading-md text-2xl font-bold text-primary">{{ $occupancyPercent }}%</span>
                <span class="text-xs text-secondary">10-Day Window</span>
            </div>
            <div class="w-full bg-surface-container rounded-full h-1.5 mt-3 overflow-hidden">
                <div class="bg-tertiary-container h-full rounded-full" style="width: {{ min(100, $occupancyPercent) }}%"></div>
            </div>
        </div>

        <!-- 2. ADR -->
        <div class="bg-surface rounded-xl p-4 shadow-sm border border-border flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-secondary">Avg Daily Rate (ADR)</span>
                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-success-soft text-tertiary text-xs font-semibold">
                    <span class="material-symbols-outlined text-xs">arrow_upward</span>+₹420
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="font-heading-md text-2xl font-bold text-primary">{{ $adrFormatted }}</span>
                <span class="text-xs text-secondary">Base Yield</span>
            </div>
            <div class="text-secondary text-xs mt-3 flex items-center justify-between">
                <span>Target: ₹6,500</span>
                <span class="text-tertiary-container font-semibold">105.3% to Plan</span>
            </div>
        </div>

        <!-- 3. Channel Health -->
        <div class="bg-surface rounded-xl p-4 shadow-sm border border-border flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-secondary">Channel Health</span>
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-success-soft text-tertiary-container text-xs font-semibold">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container"></span>All Stable
                </span>
            </div>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="font-heading-md text-2xl font-bold text-primary">{{ $activeChannelsCount }} / {{ $totalChannelsCount }}</span>
                <span class="text-xs text-secondary">OTAs Synced</span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs text-secondary">
                <span>Raahi • Sarai • Mela</span>
                <span class="text-tertiary-container font-medium">Live Feed</span>
            </div>
        </div>

        <!-- 4. Critical Alerts -->
        <div class="bg-{{ $criticalAlertsCount > 0 ? 'danger-soft' : 'surface' }} rounded-xl p-4 shadow-sm border border-{{ $criticalAlertsCount > 0 ? 'danger/30' : 'border' }} flex flex-col justify-between">
            <div class="flex items-start justify-between">
                <span class="text-xs uppercase tracking-wider font-semibold text-{{ $criticalAlertsCount > 0 ? 'danger' : 'secondary' }}">Critical Alerts</span>
                @if($criticalAlertsCount > 0)
                    <span class="material-symbols-outlined text-danger text-base">warning</span>
                @endif
            </div>
            <div class="flex items-baseline gap-2 mt-2">
                <span class="font-heading-md text-2xl font-bold text-{{ $criticalAlertsCount > 0 ? 'danger' : 'primary' }}">
                    {{ $criticalAlertsCount > 0 ? $criticalAlertsCount . ' Deficit' : '0 Alerts' }}
                </span>
            </div>
            <div class="mt-3 flex items-center justify-between text-xs">
                <span class="text-secondary">Oversold Check</span>
                <a href="{{ route('alerts') }}" class="underline font-semibold text-{{ $criticalAlertsCount > 0 ? 'danger' : 'tertiary-container' }} hover:opacity-80">
                    Review Triage
                </a>
            </div>
        </div>
    </div>

    <!-- Calendar Table Container -->
    <div class="relative bg-surface rounded-xl shadow-sm border border-border overflow-hidden flex flex-col mb-8">
        <div class="p-3 bg-surface-container-low border-b border-border flex flex-wrap items-center justify-between gap-2 z-30">
            <div class="flex items-center gap-2">
                <span class="font-title-sm text-xs font-semibold text-primary flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-tertiary-container text-base">calendar_view_week</span>
                    Active 10-Day Sliding Range
                </span>
                <span class="px-2 py-0.5 rounded bg-surface border border-border font-data-mono text-xs font-semibold text-primary">
                    {{ $startDate->format('M d') }} – {{ $endDate->format('M d, Y') }}
                </span>
            </div>
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5">
                    <label for="roomFilterSelect" class="text-xs text-secondary font-medium">Filter Rooms:</label>
                    <select id="roomFilterSelect" onchange="filterRooms(this.value)" class="text-xs bg-surface border border-border rounded px-2 py-0.5 text-primary focus:outline-none focus:ring-1 focus:ring-tertiary-container">
                        <option value="all">All Room Types</option>
                        @foreach($roomTypes as $room)
                            <option value="{{ $room->code }}">{{ $room->name }} ({{ $room->code }})</option>
                        @endforeach
                    </select>
                </div>
                <div class="flex items-center gap-2">
                    <span class="text-xs text-secondary">Quick Jump:</span>
                    <a href="{{ route('calendar', ['start_date' => $today->toDateString()]) }}"
                       class="px-2 py-0.5 rounded text-xs {{ $startDate->isSameDay($today) ? 'bg-tertiary-container text-on-primary font-semibold' : 'text-secondary hover:text-primary hover:bg-surface' }}">
                        Today
                    </a>
                    <a href="{{ route('calendar', ['start_date' => $today->copy()->addDays(10)->toDateString()]) }}"
                       class="px-2 py-0.5 rounded text-xs text-secondary hover:text-primary hover:bg-surface transition-colors">
                        +10 Days
                    </a>
                    <a href="{{ route('calendar', ['start_date' => $today->copy()->addDays(20)->toDateString()]) }}"
                       class="px-2 py-0.5 rounded text-xs text-secondary hover:text-primary hover:bg-surface transition-colors">
                        +20 Days
                    </a>
                </div>
            </div>
        </div>

        <div class="overflow-x-auto relative">
            <table class="w-full text-left border-collapse min-w-[1100px]">
                <thead class="sticky top-0 z-30 shadow-sm">
                    <tr class="border-b border-border bg-surface">
                        <th class="sticky left-0 z-40 bg-surface px-4 py-3 w-[260px] min-w-[260px] border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)]">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-semibold uppercase tracking-wider text-secondary">Room &amp; Rate Metrics</span>
                                <span class="font-data-mono text-xs text-primary font-semibold">{{ $startDate->format('M Y') }}</span>
                            </div>
                        </th>
                        @foreach($dates as $d)
                            @php
                                $isToday = $d->isSameDay($today);
                            @endphp
                            <th class="w-[84px] min-w-[84px] px-1.5 py-2 text-center {{ $isToday ? 'bg-success-soft/70 border-r border-tertiary-container/30 relative' : 'bg-surface border-r border-border' }}">
                                @if($isToday)
                                    <div class="absolute -top-0 left-0 right-0 h-1 bg-tertiary-container"></div>
                                    <span class="inline-block px-1.5 py-0.5 mb-1 rounded bg-tertiary-container text-on-primary font-label-sm text-[9px] font-bold tracking-wider uppercase">TODAY</span>
                                @endif
                                <div class="text-[11px] uppercase font-bold {{ $isToday ? 'text-tertiary-container' : 'text-secondary' }}">
                                    {{ $d->format('D') }}
                                </div>
                                <div class="font-heading-sm text-sm font-bold {{ $isToday ? 'text-tertiary-container' : 'text-primary' }}">
                                    {{ $d->format('d') }}
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($roomTypes as $room)
                        <!-- Group Header: Room Type -->
                        <tr data-room-row="{{ $room->code }}" class="bg-surface-container-low border-y border-border">
                            <td class="sticky left-0 z-30 bg-surface-container-low px-4 py-2 border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)]">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-tertiary-container shrink-0"></span>
                                    <span class="font-heading-sm text-xs font-bold text-primary truncate">{{ $room->name }} ({{ $room->code }})</span>
                                    <span class="text-xs text-secondary ml-auto shrink-0 font-medium">{{ $room->total_rooms }} Keys</span>
                                </div>
                            </td>
                            <td class="bg-surface-container-low px-4 py-2 text-secondary text-xs" colspan="10">
                                <div class="flex items-center justify-between pr-4">
                                    <span class="italic text-[11px]">Dynamic Inventory &amp; Channel Allocation</span>
                                    <span class="font-data-mono text-[11px] text-tertiary-container font-semibold">10-Day Allocation Track</span>
                                </div>
                            </td>
                        </tr>

                        <!-- Row 1: Available to Sell -->
                        <tr data-room-row="{{ $room->code }}" class="border-b border-border hover:bg-surface-container-low transition-colors">
                            <td class="sticky left-0 z-20 bg-surface px-4 py-2 text-xs text-on-surface border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)] flex items-center justify-between">
                                <span class="font-semibold text-tertiary-container">Available to Sell</span>
                                <span class="text-[11px] text-secondary">Units</span>
                            </td>
                            @foreach($dates as $d)
                                @php
                                    $dStr = $d->toDateString();
                                    $inv = $inventoryMatrix[$room->id][$dStr] ?? null;
                                    $avail = $inv ? (int) $inv->available : $room->total_rooms;
                                    $stopSell = $inv ? (bool) $inv->stop_sell : false;
                                    $isToday = $d->isSameDay($today);
                                @endphp
                                <td class="text-center font-data-mono text-xs font-bold border-r border-border {{ $isToday ? 'bg-success-soft/30' : 'bg-surface' }}">
                                    @if($stopSell)
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-warning-soft text-warning font-semibold text-[10px] border border-warning/30">STOP</span>
                                    @elseif($avail < 0)
                                        <span class="inline-block px-1.5 py-0.5 rounded bg-danger-soft text-danger font-bold text-xs border border-danger/30">{{ $avail }} Deficit</span>
                                    @else
                                        <span class="text-tertiary-container font-semibold">{{ $avail }}</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>

                        <!-- Row 2: Booked (Occupied) / Capacity with Inline Edit -->
                        <tr data-room-row="{{ $room->code }}" class="border-b border-border hover:bg-surface-container-low transition-colors">
                            <td class="sticky left-0 z-20 bg-surface px-4 py-2 text-xs text-secondary border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)] flex items-center justify-between">
                                <span>Booked / Capacity</span>
                                <span class="text-[10px] text-secondary">Click to edit cap</span>
                            </td>
                            @foreach($dates as $d)
                                @php
                                    $dStr = $d->toDateString();
                                    $inv = $inventoryMatrix[$room->id][$dStr] ?? null;
                                    $booked = $inv ? (int) $inv->booked : 0;
                                    $cap = $inv ? (int) $inv->capacity : $room->total_rooms;
                                @endphp
                                <td class="text-center font-data-mono text-xs border-r border-border hover:bg-surface-container cursor-pointer cell-edit"
                                    data-room-type-id="{{ $room->id }}"
                                    data-stay-date="{{ $dStr }}"
                                    data-field="capacity"
                                    data-current="{{ $cap }}"
                                    title="Click to edit capacity">
                                    <span class="font-bold text-primary">{{ $booked }}</span>
                                    <span class="text-secondary text-[11px]">/ {{ $cap }}</span>
                                </td>
                            @endforeach
                        </tr>

                        <!-- Row 3 & 4: Rate Plans (EP & CP) -->
                        @foreach($room->ratePlans as $ratePlan)
                            <tr data-room-row="{{ $room->code }}" class="border-b border-border hover:bg-surface-container-low transition-colors">
                                <td class="sticky left-0 z-20 bg-surface px-4 py-2 text-xs text-on-surface border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)] flex items-center justify-between">
                                    <span class="flex items-center gap-1.5 truncate">
                                        <span class="w-1.5 h-1.5 rounded-full bg-secondary shrink-0"></span>
                                        {{ $ratePlan->name }}
                                    </span>
                                    <span class="text-[10px] text-secondary font-data-mono">Base ₹{{ number_format($ratePlan->base_price) }}</span>
                                </td>
                                @foreach($dates as $d)
                                    @php
                                        $dStr = $d->toDateString();
                                        $rp = $priceMatrix[$ratePlan->id][$dStr] ?? null;
                                        $price = $rp ? (int) $rp->price : $ratePlan->base_price;
                                    @endphp
                                    <td class="text-center font-data-mono text-xs border-r border-border hover:bg-surface-container cursor-pointer cell-edit"
                                        data-rate-plan-id="{{ $ratePlan->id }}"
                                        data-stay-date="{{ $dStr }}"
                                        data-field="price"
                                        data-current="{{ $price }}"
                                        title="Click to edit rate">
                                        <span class="text-primary font-medium">₹{{ number_format($price) }}</span>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach

                        <!-- Row 5: Channels Sync Status (Raahi/Sarai/Mela) -->
                        <tr data-room-row="{{ $room->code }}" class="border-b border-border bg-surface-container-lowest">
                            <td class="sticky left-0 z-20 bg-surface px-4 py-1.5 text-[11px] text-secondary border-r-2 border-border shadow-[4px_0_8px_-2px_rgba(0,0,0,0.06)]">
                                Channels (Raahi/Sarai/Mela)
                            </td>
                            @foreach($dates as $d)
                                @php
                                    $dStr = $d->toDateString();
                                    $raahiState = $syncMatrix['availability'][$room->id][$dStr]['raahi'] ?? 'synced';
                                    $saraiState = $syncMatrix['availability'][$room->id][$dStr]['sarai'] ?? 'synced';
                                    $melaState  = $syncMatrix['availability'][$room->id][$dStr]['mela'] ?? 'synced';

                                    $dotClass = function($state) {
                                        return match($state) {
                                            'synced' => 'bg-[#4C7168]',
                                            'pending' => 'bg-warning',
                                            'blocked' => 'bg-danger',
                                            default => 'bg-[#4C7168]'
                                        };
                                    };
                                @endphp
                                <td class="text-center py-1.5 border-r border-border">
                                    <span class="inline-flex gap-1 items-center justify-center">
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClass($raahiState) }}" title="Raahi: {{ $raahiState }}"></span>
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClass($saraiState) }}" title="Sarai: {{ $saraiState }}"></span>
                                        <span class="w-1.5 h-1.5 rounded-full {{ $dotClass($melaState) }}" title="Mela: {{ $melaState }}"></span>
                                    </span>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Bulk Edit Modal -->
<div id="bulkEditModal" class="hidden fixed inset-0 bg-primary/40 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-xl shadow-xl border border-border w-full max-w-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between">
            <h3 class="font-heading-sm text-base font-semibold text-primary">Bulk Inventory &amp; Rate Update</h3>
            <button type="button" onclick="$('#bulkEditModal').addClass('hidden')" class="text-secondary hover:text-primary">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>
        <form id="bulkEditForm" class="p-6 space-y-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase mb-1">Start Date</label>
                    <input type="date" name="start_date" value="{{ $today->toDateString() }}" required
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase mb-1">End Date</label>
                    <input type="date" name="end_date" value="{{ $today->copy()->addDays(9)->toDateString() }}" required
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-secondary uppercase mb-1.5">Days of Week</label>
                <div class="flex items-center gap-2 flex-wrap text-xs">
                    @foreach(['Mon' => 1, 'Tue' => 2, 'Wed' => 3, 'Thu' => 4, 'Fri' => 5, 'Sat' => 6, 'Sun' => 7] as $label => $day)
                        <label class="inline-flex items-center gap-1 bg-surface-container-low px-2 py-1 rounded border border-border cursor-pointer">
                            <input type="checkbox" name="weekdays[]" value="{{ $day }}" checked class="rounded border-border text-tertiary-container">
                            <span>{{ $label }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-secondary uppercase mb-1">Update Target</label>
                <select name="action" id="bulkActionSelect" class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary">
                    <option value="set_capacity">Set Room Capacity</option>
                    <option value="set_price">Set Rate Plan Price (INR)</option>
                    <option value="set_stop_sell">Toggle Stop Sell</option>
                </select>
            </div>

            <div id="roomTypeGroup">
                <label class="block text-xs font-semibold text-secondary uppercase mb-1">Room Type</label>
                <select name="room_type_id" class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary">
                    @foreach($roomTypes as $r)
                        <option value="{{ $r->id }}">{{ $r->name }} ({{ $r->code }})</option>
                    @endforeach
                </select>
            </div>

            <div id="ratePlanGroup" class="hidden">
                <label class="block text-xs font-semibold text-secondary uppercase mb-1">Rate Plan</label>
                <select name="rate_plan_id" class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary">
                    @foreach($roomTypes as $r)
                        @foreach($r->ratePlans as $rp)
                            <option value="{{ $rp->id }}">{{ $r->name }} — {{ $rp->name }} ({{ $rp->code }})</option>
                        @endforeach
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-semibold text-secondary uppercase mb-1" id="bulkValueLabel">New Capacity Value</label>
                <input type="number" name="value" id="bulkValueInput" value="10" required
                       class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
            </div>

            <div id="bulkError" class="hidden p-2 rounded bg-danger-soft text-danger text-xs"></div>

            <div class="pt-2 flex items-center justify-end gap-2">
                <button type="button" onclick="$('#bulkEditModal').addClass('hidden')" class="h-9 px-4 rounded border border-border text-secondary text-xs hover:bg-surface-container">
                    Cancel
                </button>
                <button type="submit" class="h-9 px-4 rounded bg-tertiary-container text-on-primary font-semibold text-xs hover:bg-primary transition-colors">
                    Apply Updates
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $('#bulkActionSelect').on('change', function() {
        const val = $(this).val();
        if (val === 'set_price') {
            $('#roomTypeGroup').addClass('hidden');
            $('#ratePlanGroup').removeClass('hidden');
            $('#bulkValueLabel').text('New Price (INR)');
            $('#bulkValueInput').val('5000').attr('type', 'number');
        } else if (val === 'set_stop_sell') {
            $('#roomTypeGroup').removeClass('hidden');
            $('#ratePlanGroup').addClass('hidden');
            $('#bulkValueLabel').text('Stop Sell (1 = Closed, 0 = Open)');
            $('#bulkValueInput').val('1').attr('type', 'number');
        } else {
            $('#roomTypeGroup').removeClass('hidden');
            $('#ratePlanGroup').addClass('hidden');
            $('#bulkValueLabel').text('New Capacity Value');
            $('#bulkValueInput').val('10').attr('type', 'number');
        }
    });

    $('#bulkEditForm').on('submit', function(e) {
        e.preventDefault();
        $('#bulkError').addClass('hidden').text('');
        $.ajax({
            url: "{{ route('calendar.bulk') }}",
            method: 'POST',
            data: $(this).serialize(),
            success: function() {
                location.reload();
            },
            error: function(xhr) {
                const msg = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Validation error.';
                $('#bulkError').removeClass('hidden').text(msg);
            }
        });
    });

    // Inline cell edit on click
    $('.cell-edit').on('click', function() {
        const cell = $(this);
        if (cell.find('input').length > 0) return;

        const currentVal = cell.data('current');
        const field = cell.data('field');
        const originalHtml = cell.html();

        const input = $('<input type="number" class="w-16 h-6 text-center text-xs border border-tertiary-container rounded bg-surface">')
            .val(currentVal);

        cell.html('').append(input);
        input.focus().select();

        input.on('blur keyup', function(e) {
            if (e.type === 'keyup' && e.key !== 'Enter' && e.key !== 'Escape') return;

            if (e.key === 'Escape') {
                cell.html(originalHtml);
                return;
            }

            const newVal = input.val();
            if (newVal == currentVal) {
                cell.html(originalHtml);
                return;
            }

            const payload = {
                stay_date: cell.data('stay-date'),
                field: field,
                value: newVal
            };
            if (cell.data('room-type-id')) payload.room_type_id = cell.data('room-type-id');
            if (cell.data('rate-plan-id')) payload.rate_plan_id = cell.data('rate-plan-id');

            $.ajax({
                url: "{{ route('calendar.cell') }}",
                method: 'PATCH',
                data: payload,
                success: function() {
                    location.reload();
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || 'Error updating cell');
                    cell.html(originalHtml);
                }
            });
        });
    });

    window.filterRooms = function(code) {
        if (!code || code === 'all') {
            $('tr[data-room-row]').show();
        } else {
            $('tr[data-room-row]').each(function() {
                const rowCode = $(this).attr('data-room-row');
                if (rowCode === code) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        }
    };
</script>
@endpush
