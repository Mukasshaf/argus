@extends('layouts.app', ['title' => 'Reservations Ledger'])

@section('content')
<div class="flex flex-col w-full">
    <!-- Top Editorial Header & Operational Overview -->
    <div class="flex flex-col gap-4 mb-6">
        <div class="flex flex-col md:flex-row md:items-end justify-between gap-4">
            <div class="flex items-center gap-3">
                <h1 class="font-heading-md text-2xl font-semibold tracking-tight text-primary" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">
                    Reservations
                </h1>
                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-success-soft text-tertiary-container text-xs font-semibold border border-tertiary-container/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse"></span>
                    Live Feed
                </span>
            </div>
            <div class="flex items-center gap-2 pt-3 mt-2">
                <a href="{{ route('reservations.export') }}" class="h-9 px-3.5 bg-surface border border-border text-primary font-medium text-xs rounded hover:bg-surface-container transition-colors flex items-center gap-1.5 shadow-sm">
                    <span class="material-symbols-outlined text-base text-secondary">download</span>
                    <span>Export CSV</span>
                </a>
                <button type="button" onclick="$('#manualBookingModal').removeClass('hidden')" class="h-9 px-3.5 bg-tertiary-container hover:bg-[#3D5B54] text-on-primary font-semibold text-xs rounded shadow-sm transition-all flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-base">add</span>
                    <span>Manual Reservation</span>
                </button>
            </div>
        </div>

        <!-- Quick Vital Metrics Grid -->
        <div class="bg-surface rounded-xl border border-border shadow-sm px-6 py-3 flex flex-col md:flex-row items-stretch justify-between divide-y md:divide-y-0 md:divide-x divide-border">
            <div class="flex items-center gap-4 flex-1 py-2 md:py-0 md:pr-6">
                <div class="w-10 h-10 rounded bg-surface-container-low flex items-center justify-center text-primary shrink-0 border border-border">
                    <span class="material-symbols-outlined text-xl">bedroom_parent</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <span class="text-xs uppercase tracking-wider font-semibold text-secondary truncate">Active Bookings</span>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="font-heading-sm text-xl font-bold text-primary">{{ $totalReservationsCount }}</span>
                        <span class="text-xs text-secondary font-data-mono">Confirmed: {{ $confirmedCount }}</span>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 flex-1 py-2 md:py-0 md:px-6">
                <div class="w-10 h-10 rounded bg-danger-soft flex items-center justify-center text-danger shrink-0 border border-danger/20">
                    <span class="material-symbols-outlined text-xl">warning</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-xs uppercase tracking-wider font-semibold text-danger truncate">Inventory Deficit</span>
                        <span class="text-xs font-medium text-danger font-data-mono">Action Needed</span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="font-heading-sm text-xl font-bold text-danger whitespace-nowrap">{{ $criticalOversoldCount }} Oversold</span>
                        <a href="{{ route('reservations', ['tab' => 'oversold']) }}" class="text-xs text-danger underline cursor-pointer truncate">View Deficit Nights</a>
                    </div>
                </div>
            </div>

            <div class="flex items-center gap-4 flex-1 py-2 md:py-0 md:pl-6">
                <div class="w-10 h-10 rounded bg-warning-soft flex items-center justify-center text-warning shrink-0 border border-warning/20">
                    <span class="material-symbols-outlined text-xl">sync_problem</span>
                </div>
                <div class="flex flex-col min-w-0">
                    <div class="flex items-center justify-between gap-1">
                        <span class="text-xs uppercase tracking-wider font-semibold text-warning truncate">Inbound Issues</span>
                        <span class="text-xs font-medium text-warning font-data-mono">Quarantine</span>
                    </div>
                    <div class="flex items-baseline gap-2 mt-0.5">
                        <span class="font-heading-sm text-xl font-bold text-warning whitespace-nowrap">{{ $failedInboundCount }} Pending</span>
                        @if($failedInboundCount > 0)
                            <a href="{{ route('reservations', ['tab' => 'failed']) }}" class="text-xs text-warning underline cursor-pointer">Reprocess Queue</a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Ledger Container -->
    <div class="bg-surface rounded-xl border border-border shadow-sm flex flex-col overflow-hidden mb-8">
        <!-- Tab Navigation Segment -->
        <div class="px-4 pt-2 bg-surface-container-low flex items-center justify-between overflow-x-auto select-none border-b border-border">
            <div class="flex items-center space-x-1" role="tablist">
                <a href="{{ route('reservations', ['tab' => 'all']) }}"
                   class="px-4 py-2.5 rounded-t text-xs font-semibold flex items-center gap-1.5 relative {{ $tab === 'all' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary hover:bg-surface-container' }}">
                    <span>All Reservations</span>
                    <span class="text-[11px] bg-surface-container-high px-1.5 py-0.2 rounded-full text-secondary">{{ $totalReservationsCount }}</span>
                </a>

                <a href="{{ route('reservations', ['tab' => 'confirmed']) }}"
                   class="px-4 py-2.5 rounded-t text-xs font-semibold flex items-center gap-1.5 relative {{ $tab === 'confirmed' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary hover:bg-surface-container' }}">
                    <span>Confirmed</span>
                    <span class="text-[11px] bg-surface-container-high px-1.5 py-0.2 rounded-full text-secondary">{{ $confirmedCount }}</span>
                </a>

                <a href="{{ route('reservations', ['tab' => 'oversold']) }}"
                   class="px-4 py-2.5 rounded-t text-xs font-semibold flex items-center gap-1.5 relative {{ $tab === 'oversold' ? 'bg-surface text-danger border-b-2 border-danger' : 'text-danger hover:bg-surface-container' }}">
                    <span>Oversold / Deficit</span>
                    <span class="text-[11px] bg-danger text-on-error px-1.5 py-0.2 rounded-full">{{ $criticalOversoldCount }}</span>
                </a>

                <a href="{{ route('reservations', ['tab' => 'failed']) }}"
                   class="px-4 py-2.5 rounded-t text-xs font-semibold flex items-center gap-1.5 relative {{ $tab === 'failed' ? 'bg-surface text-warning border-b-2 border-warning' : 'text-warning hover:bg-surface-container' }}">
                    <span>Failed Inbound</span>
                    <span class="text-[11px] bg-warning text-on-primary px-1.5 py-0.2 rounded-full">{{ $failedInboundCount }}</span>
                </a>

                <a href="{{ route('reservations', ['tab' => 'cancelled']) }}"
                   class="px-4 py-2.5 rounded-t text-xs font-semibold flex items-center gap-1.5 relative {{ $tab === 'cancelled' ? 'bg-surface text-primary border-b-2 border-primary' : 'text-secondary hover:text-primary hover:bg-surface-container' }}">
                    <span>Cancelled</span>
                    <span class="text-[11px] bg-surface-container-high px-1.5 py-0.2 rounded-full text-secondary">{{ $cancelledCount }}</span>
                </a>
            </div>

            <div class="hidden lg:flex items-center gap-1 text-secondary text-xs pr-2">
                <span class="w-2 h-2 rounded-full bg-tertiary-container animate-pulse"></span>
                <span>Active Ledger</span>
            </div>
        </div>

        <!-- Filter & Action Controls Bar -->
        <div class="p-4 bg-surface flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-3 border-b border-border">
            <form method="GET" action="{{ route('reservations') }}" class="flex-1 flex flex-wrap items-center gap-2">
                <input type="hidden" name="tab" value="{{ $tab }}">
                <div class="relative w-64">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-secondary text-base">search</span>
                    <input type="text" name="search" value="{{ $search }}" placeholder="Search guest, booking ref..."
                           class="h-8 pl-8 pr-3 bg-surface-container-low text-on-surface placeholder:text-secondary rounded text-xs w-full focus:outline-none focus:bg-surface border border-border">
                </div>
                <div class="relative">
                    <select name="channel_id" onchange="this.form.submit()"
                            class="h-8 pl-3 pr-7 bg-surface-container-low text-on-surface text-xs rounded border border-border appearance-none cursor-pointer">
                        <option value="">All Channels</option>
                        @foreach($channels as $c)
                            <option value="{{ $c->id }}" {{ $channelId == $c->id ? 'selected' : '' }}>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if($search || $channelId)
                    <a href="{{ route('reservations', ['tab' => $tab]) }}" class="h-8 px-2 text-secondary hover:text-primary text-xs flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">filter_list_off</span>
                        <span>Clear</span>
                    </a>
                @endif
            </form>
        </div>

        <!-- Content Area: Standard Table OR Oversold Nights OR Failed Inbound -->
        @if($tab === 'oversold')
            <!-- D4: Oversold Nights View -->
            <div class="p-6 space-y-6">
                <div class="p-3 bg-danger-soft border border-danger/30 rounded text-xs text-danger flex items-center gap-2">
                    <span class="material-symbols-outlined text-base">error</span>
                    <span><strong>Notification Mode Deficit:</strong> The following nights have negative available rooms. Holders are listed in chronological receipt order.</span>
                </div>

                @forelse($oversoldNights as $item)
                    <div class="bg-surface rounded-lg border border-danger/40 p-4 shadow-sm">
                        <div class="flex items-center justify-between border-b border-border pb-2 mb-3">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded bg-danger text-on-error font-data-mono text-xs font-bold">-{{ $item['deficit'] }} Deficit</span>
                                <span class="font-heading-sm text-sm font-semibold text-primary">{{ $item['inventory']->roomType->name }} ({{ $item['inventory']->roomType->code }})</span>
                                <span class="text-secondary text-xs">• {{ $item['inventory']->stay_date->format('l, d M Y') }}</span>
                            </div>
                            <span class="text-xs text-secondary font-data-mono">Capacity: {{ $item['inventory']->capacity }} | Booked: {{ $item['inventory']->booked }}</span>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-secondary uppercase border-b border-border">
                                        <th class="py-1 px-2 font-semibold">Priority</th>
                                        <th class="py-1 px-2 font-semibold">Booking ID</th>
                                        <th class="py-1 px-2 font-semibold">Guest</th>
                                        <th class="py-1 px-2 font-semibold">Received At (UTC)</th>
                                        <th class="py-1 px-2 font-semibold text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-border">
                                    @foreach($item['holders'] as $idx => $holder)
                                        @php
                                            $isOversoldHolder = $idx >= $item['inventory']->capacity;
                                        @endphp
                                        <tr class="{{ $isOversoldHolder ? 'bg-danger-soft/50 font-semibold' : '' }}">
                                            <td class="py-1.5 px-2 font-data-mono">{{ $idx + 1 }}</td>
                                            <td class="py-1.5 px-2 font-data-mono">{{ $holder->external_id }}</td>
                                            <td class="py-1.5 px-2">{{ $holder->guest_name }}</td>
                                            <td class="py-1.5 px-2 font-data-mono text-secondary">{{ $holder->received_at }}</td>
                                            <td class="py-1.5 px-2 text-right">
                                                @if($isOversoldHolder)
                                                    <span class="px-1.5 py-0.5 rounded bg-danger text-on-error font-bold text-[10px]">Oversold Key</span>
                                                @else
                                                    <span class="px-1.5 py-0.5 rounded bg-success-soft text-tertiary-container font-medium text-[10px]">Secured</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @empty
                    <div class="py-12 text-center text-secondary text-xs">
                        <span class="material-symbols-outlined text-3xl text-[#4C7168] mb-1">check_circle</span>
                        <p class="font-semibold text-primary">No oversold nights currently recorded.</p>
                        <p>All room types have available capacity &ge; 0.</p>
                    </div>
                @endforelse
            </div>

        @elseif($tab === 'failed')
            <!-- Failed Inbound Events Table with Reprocess -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container text-secondary text-xs uppercase tracking-wider border-b border-border">
                            <th class="py-2.5 px-4 font-semibold">Event ID &amp; Channel</th>
                            <th class="py-2.5 px-4 font-semibold">Kind</th>
                            <th class="py-2.5 px-4 font-semibold">Error Message</th>
                            <th class="py-2.5 px-4 font-semibold">Received</th>
                            <th class="py-2.5 px-4 font-semibold text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($failedEvents as $evt)
                            <tr class="hover:bg-surface-container-low transition-colors bg-warning-soft/20">
                                <td class="py-3 px-4 font-data-mono text-xs">
                                    <span class="font-bold text-primary">{{ $evt->external_event_id }}</span>
                                    <span class="block text-[11px] text-secondary">{{ $evt->channel->name ?? 'Channel #' . $evt->channel_id }}</span>
                                </td>
                                <td class="py-3 px-4 text-xs">{{ $evt->kind }}</td>
                                <td class="py-3 px-4 text-xs text-danger font-mono text-[11px]">{{ $evt->error ?? 'Processing error' }}</td>
                                <td class="py-3 px-4 text-xs font-data-mono text-secondary">{{ $evt->received_at->diffForHumans() }}</td>
                                <td class="py-3 px-4 text-right">
                                    <form action="{{ route('reservations.reprocess', $evt->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="h-7 px-3 bg-warning text-on-primary rounded text-xs font-semibold hover:opacity-90 transition-opacity flex items-center gap-1 ml-auto">
                                            <span class="material-symbols-outlined text-xs">refresh</span>
                                            <span>Reprocess</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-12 text-center text-secondary text-xs">
                                    <span class="material-symbols-outlined text-3xl text-[#4C7168] mb-1">task_alt</span>
                                    <p class="font-semibold text-primary">Zero failed inbound events.</p>
                                    <p>All channel payloads parsed and ingested cleanly.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        @else
            <!-- Standard Reservations Table matching Stitch Design -->
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-surface-container text-secondary text-xs uppercase tracking-wider select-none border-b border-border">
                            <th class="py-2.5 px-4 font-semibold">Booking Ref &amp; Channel</th>
                            <th class="py-2.5 px-4 font-semibold">Guest &amp; Contact</th>
                            <th class="py-2.5 px-4 font-semibold">Room &amp; Rate Plan</th>
                            <th class="py-2.5 px-4 font-semibold">Stay Window</th>
                            <th class="py-2.5 px-4 font-semibold text-right">Amount (INR)</th>
                            <th class="py-2.5 px-4 font-semibold text-center">Status</th>
                            <th class="py-2.5 px-4 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-border">
                        @forelse($reservations as $res)
                            <tr class="hover:bg-surface-container-low transition-colors {{ $res->status->value === 'cancelled' ? 'opacity-60 bg-surface-container-low' : '' }}">
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="font-data-mono text-xs font-bold text-primary">#{{ $res->id }} · {{ $res->external_id }}</span>
                                        <span class="text-[11px] text-secondary font-data-mono">{{ $res->channel->name ?? 'Channel' }} (v{{ $res->external_version }})</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold text-primary">{{ $res->guest_name }}</span>
                                        <span class="text-[11px] text-secondary">Adults: {{ $res->adults }}</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold text-primary">{{ $res->roomType->name ?? 'Room' }} ({{ $res->roomType->code ?? '' }})</span>
                                        <span class="text-xs text-secondary">{{ $res->ratePlan->name ?? 'Rate' }} ({{ $res->ratePlan->code ?? '' }})</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle">
                                    <div class="flex flex-col">
                                        <span class="text-xs font-semibold text-primary">{{ $res->check_in->format('M d') }} – {{ $res->check_out->format('M d, Y') }}</span>
                                        <span class="text-xs text-secondary">{{ $res->check_in->diffInDays($res->check_out) }} Nights</span>
                                    </div>
                                </td>
                                <td class="py-3 px-4 align-middle text-right">
                                    <span class="font-data-mono text-xs font-bold text-primary">₹{{ number_format($res->total_price) }}</span>
                                    <span class="block text-[11px] text-secondary">{{ $res->currency }}</span>
                                </td>
                                <td class="py-3 px-4 align-middle text-center">
                                    @if($res->status->value === 'confirmed')
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-success-soft text-tertiary-container border border-tertiary-container/20 text-xs font-semibold">
                                            <span class="material-symbols-outlined text-xs">check_circle</span>Confirmed
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-surface-container-high text-secondary border border-border text-xs font-semibold">
                                            <span class="material-symbols-outlined text-xs">cancel</span>Cancelled
                                        </span>
                                    @endif
                                </td>
                                <td class="py-3 px-4 align-middle text-right">
                                    <button class="h-7 px-2.5 bg-surface border border-border text-secondary hover:text-primary hover:bg-surface-container rounded text-xs transition-colors">
                                        Voucher
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="py-12 text-center text-secondary text-xs">
                                    <span class="material-symbols-outlined text-3xl mb-1 text-secondary">inbox</span>
                                    <p class="font-semibold text-primary">No reservations matching query.</p>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination Bar -->
            <div class="px-4 py-3 bg-surface-container-low flex flex-col sm:flex-row items-center justify-between gap-2 border-t border-border">
                <div class="text-xs text-secondary">
                    Showing <span class="font-semibold text-primary">{{ $reservations->firstItem() ?? 0 }} to {{ $reservations->lastItem() ?? 0 }}</span> of <span class="font-semibold text-primary">{{ $reservations->total() }}</span> reservations
                </div>
                <div>
                    {{ $reservations->withQueryString()->links() }}
                </div>
            </div>
        @endif
    </div>
</div>

<!-- Manual Reservation Modal -->
<div id="manualBookingModal" class="{{ $errors->has('manual') ? '' : 'hidden' }} fixed inset-0 bg-primary/50 backdrop-blur-xs z-50 flex items-center justify-center p-4">
    <div class="bg-surface rounded-xl shadow-xl border border-border w-full max-w-xl overflow-hidden flex flex-col">
        <div class="px-6 py-4 border-b border-border flex items-center justify-between bg-surface-container-low">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-tertiary-container text-xl">add_circle</span>
                <h3 class="font-heading-sm text-base font-semibold text-primary">Create Manual Reservation</h3>
            </div>
            <button type="button" onclick="$('#manualBookingModal').addClass('hidden')" class="text-secondary hover:text-primary">
                <span class="material-symbols-outlined text-lg">close</span>
            </button>
        </div>

        <form action="{{ route('reservations.manual') }}" method="POST" class="p-6 space-y-4">
            @csrf

            @if($errors->has('manual'))
                <div class="p-3 rounded bg-danger-soft border border-danger/30 text-danger text-xs font-medium">
                    {{ $errors->first('manual') }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Guest Name</label>
                    <input type="text" name="guest_name" required placeholder="e.g. Vikram Malhotra"
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Channel Origin</label>
                    <select name="channel_id" required class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                        @foreach($channels as $ch)
                            <option value="{{ $ch->id }}">{{ $ch->name }} ({{ $ch->protocol->value }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Room Type</label>
                    <select name="room_type_id" required class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                        @foreach($roomTypes as $rm)
                            <option value="{{ $rm->id }}">{{ $rm->name }} ({{ $rm->code }})</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Rate Plan</label>
                    <select name="rate_plan_id" required class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                        @foreach($ratePlans as $rp)
                            <option value="{{ $rp->id }}">{{ $rp->name }} ({{ $rp->code }}) — Base ₹{{ number_format($rp->base_price) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Check-in Date</label>
                    <input type="date" name="check_in" value="{{ now()->toDateString() }}" required
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Check-out Date</label>
                    <input type="date" name="check_out" value="{{ now()->addDay()->toDateString() }}" required
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Adults</label>
                    <input type="number" name="adults" min="1" max="10" value="1" required
                           class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Total Stay Price (INR)</label>
                <input type="number" name="total_price" min="0" value="4800" required
                       class="w-full h-9 px-3 bg-surface border border-border rounded text-xs text-primary focus:outline-none focus:border-tertiary-container">
                <span class="text-[11px] text-secondary mt-1 block">Level-triggered sync worker will immediately broadcast inventory reduction to all channels.</span>
            </div>

            <div class="pt-3 border-t border-border flex items-center justify-end gap-2">
                <button type="button" onclick="$('#manualBookingModal').addClass('hidden')" class="h-9 px-4 rounded bg-surface hover:bg-surface-container text-secondary text-xs font-medium border border-border transition-colors">
                    Cancel
                </button>
                <button type="submit" class="h-9 px-4 rounded bg-tertiary-container hover:bg-[#3D5B54] text-on-primary text-xs font-semibold transition-all shadow-sm">
                    Create Booking &amp; Update Inventory
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
