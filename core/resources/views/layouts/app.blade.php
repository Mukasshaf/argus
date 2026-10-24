<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Argus Hotel Ops' }} — Lotus Residency</title>

    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Noto+Serif:wght@500;600;700&amp;family=Lora:ital,wght@0,400..700;1,400..700&amp;display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" rel="stylesheet">

    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <script id="tailwind-config">
        tailwind.config = {
            "darkMode": "class",
            "theme": {
                "extend": {
                    "colors": {
                        "error": "#ba1a1a",
                        "on-error-container": "#93000a",
                        "secondary-fixed": "#dbe5e0",
                        "tertiary-container": "#113730",
                        "on-primary-fixed-variant": "#3b4950",
                        "surface-container-high": "#ebe8e0",
                        "primary-fixed": "#d6e5ee",
                        "surface-container-highest": "#e5e2da",
                        "success-soft": "#F0F5F3",
                        "primary-fixed-dim": "#bbc9d1",
                        "on-primary": "#ffffff",
                        "surface": "#FFFFFF",
                        "on-primary-fixed": "#101d24",
                        "on-tertiary-container": "#7aa197",
                        "on-error": "#ffffff",
                        "danger": "#AD3E39",
                        "error-container": "#ffdad6",
                        "on-surface": "#1c1c17",
                        "surface-tint": "#536068",
                        "on-surface-variant": "#43474a",
                        "surface-dim": "#dddad2",
                        "primary": "#111e25",
                        "on-secondary-fixed": "#141d1b",
                        "border": "#E4E1D9",
                        "on-secondary-container": "#5b6561",
                        "primary-container": "#26333a",
                        "surface-bright": "#fcf9f1",
                        "outline-variant": "#c3c7ca",
                        "tertiary-fixed-dim": "#a7cfc4",
                        "on-tertiary": "#ffffff",
                        "on-background": "#1c1c17",
                        "tertiary-fixed": "#c3ebe0",
                        "warning-soft": "#FEF9EE",
                        "background": "#fcf9f1",
                        "on-secondary-fixed-variant": "#3f4945",
                        "outline": "#73787b",
                        "on-primary-container": "#8d9ba4",
                        "on-tertiary-fixed-variant": "#294d45",
                        "secondary-fixed-dim": "#bfc9c4",
                        "surface-container-lowest": "#ffffff",
                        "secondary": "#57615d",
                        "inverse-surface": "#31312b",
                        "on-tertiary-fixed": "#00201a",
                        "surface-container": "#f1eee6",
                        "inverse-on-surface": "#f4f1e9",
                        "warning": "#B78128",
                        "on-secondary": "#ffffff",
                        "inverse-primary": "#bbc9d1",
                        "surface-variant": "#e5e2da",
                        "nav-active-bg": "#EAF0EC",
                        "tertiary": "#00211b",
                        "surface-container-low": "#f6f3eb",
                        "danger-soft": "#FDF2F2",
                        "secondary-container": "#d8e2dd"
                    },
                    "spacing": {
                        "60": "15rem",
                        "sidebar": "15rem"
                    },
                    "fontFamily": {
                        "heading-sm": ["Noto Serif", "Lora", "Georgia", "serif"],
                        "heading-md": ["Noto Serif", "Lora", "Georgia", "serif"],
                        "editorial": ["Lora", "Georgia", "serif"],
                        "title-sm": ["Inter", "sans-serif"],
                        "title-md": ["Inter", "sans-serif"],
                        "body-md": ["Inter", "sans-serif"],
                        "body-sm": ["Inter", "sans-serif"],
                        "data-mono": ["Inter", "monospace"],
                        "label-sm": ["Inter", "sans-serif"],
                        "label-md": ["Inter", "sans-serif"]
                    }
                }
            }
        };
    </script>
    <style>
        @layer base {
            html, body {
                margin: 0;
                padding: 0;
                background-color: #fcf9f1;
            }
            body {
                overscroll-behavior: none;
            }
        }
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #f1eee6;
        }
        ::-webkit-scrollbar-thumb {
            background: #d8e2dd;
            border-radius: 4px;
        }
    </style>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });
    </script>
</head>
<body class="bg-background font-body-md text-body-md text-on-surface antialiased">
    <!-- Fixed Left Sidebar matching Stitch Design -->
    <aside class="fixed left-0 top-0 h-screen w-60 w-[240px] bg-surface border-r border-border z-50 flex flex-col justify-between select-none" style="width: 240px;">
        <div class="flex flex-col">
            <!-- Plain Text Wordmark 'Argus' in Noto Serif / Lora -->
            <div class="h-16 px-5 flex items-center justify-between border-b border-border">
                <div class="flex items-baseline gap-2">
                    <span class="font-heading-md text-2xl font-semibold tracking-tight text-primary-container" style="font-family: 'Lora', 'Noto Serif', Georgia, serif;">Argus</span>
                </div>
            </div>

            <!-- Active Property Selector Dropdown -->
            @php
                $hotelsList = \App\Models\Hotel::with('roomTypes')->get();
                if ($hotelsList->count() < 2) {
                    \App\Models\Hotel::firstOrCreate(
                        ['code' => 'MYSORE01'],
                        ['name' => 'Mysore Palace View', 'city' => 'Mysuru', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']
                    );
                    \App\Models\Hotel::firstOrCreate(
                        ['code' => 'MALABAR01'],
                        ['name' => 'Malabar Heritage Resort', 'city' => 'Kochi', 'timezone' => 'Asia/Kolkata', 'currency' => 'INR']
                    );
                    $hotelsList = \App\Models\Hotel::with('roomTypes')->get();
                }
                $activeHotelId = session('active_hotel_id');
                $currentHotel = $activeHotelId ? ($hotelsList->firstWhere('id', $activeHotelId) ?? $hotelsList->first()) : $hotelsList->first();
                $currentKeys = $currentHotel ? ($currentHotel->roomTypes->sum('total_rooms') ?: ($currentHotel->code === 'MYSORE01' ? 12 : ($currentHotel->code === 'MALABAR01' ? 20 : 18))) : 18;
            @endphp
            <div class="p-3 border-b border-border relative" id="hotelSelectorContainer">
                <button type="button"
                        id="hotelDropdownBtn"
                        onclick="$('#hotelDropdownMenu').toggleClass('hidden'); $('#hotelDropdownArrow').toggleClass('rotate-180');"
                        class="w-full p-2.5 rounded bg-surface-container-low border border-border flex items-center justify-between cursor-pointer hover:bg-surface-container transition-all text-left shadow-sm group">
                    <div class="flex flex-col min-w-0 pr-1">
                        <div class="flex items-center gap-1.5">
                            <span class="w-1.5 h-1.5 rounded-full bg-tertiary-container animate-pulse"></span>
                            <span class="font-label-sm text-[10px] uppercase text-secondary tracking-wider">Active Property</span>
                        </div>
                        <span class="font-title-sm text-xs font-semibold text-primary truncate mt-0.5">{{ $currentHotel ? $currentHotel->name : 'Lotus Residency' }}</span>
                        <span class="font-body-sm text-[11px] text-on-surface-variant truncate">{{ $currentHotel ? $currentHotel->city : 'Bengaluru' }} · {{ $currentKeys }} Keys</span>
                    </div>
                    <span class="material-symbols-outlined text-secondary text-base shrink-0 transition-transform duration-200 group-hover:text-primary" id="hotelDropdownArrow">unfold_more</span>
                </button>

                <!-- Dropdown Menu -->
                <div id="hotelDropdownMenu" class="hidden absolute left-3 right-3 top-full mt-1.5 bg-surface border border-border rounded-xl shadow-xl z-50 py-1.5 overflow-hidden ring-1 ring-black/5">
                    <div class="px-3 py-1.5 bg-surface-container-low border-b border-border flex items-center justify-between">
                        <span class="text-[10px] uppercase font-bold text-secondary tracking-wider">Switch Property</span>
                        <span class="text-[10px] text-secondary font-medium">{{ $hotelsList->count() }} Available</span>
                    </div>
                    <div class="max-h-60 overflow-y-auto divide-y divide-border/50">
                        @foreach($hotelsList as $h)
                            @php
                                $hKeys = $h->roomTypes->sum('total_rooms') ?: ($h->code === 'MYSORE01' ? 12 : ($h->code === 'MALABAR01' ? 20 : 18));
                                $isActive = $currentHotel && $currentHotel->id === $h->id;
                            @endphp
                            <a href="{{ route('hotels.switch', ['id' => $h->id]) }}"
                               class="flex items-center justify-between px-3 py-2.5 text-xs hover:bg-surface-container-high transition-colors {{ $isActive ? 'bg-nav-active-bg font-semibold text-primary' : 'text-on-surface' }}">
                                <div class="flex flex-col min-w-0 pr-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-medium truncate">{{ $h->name }}</span>
                                        @if($isActive)
                                            <span class="px-1.5 py-0.2 rounded bg-tertiary-container/10 text-tertiary-container text-[9px] font-bold uppercase">Active</span>
                                        @endif
                                    </div>
                                    <span class="text-[10px] text-secondary mt-0.5">{{ $h->city }} · {{ $hKeys }} Keys · <span class="font-data-mono">{{ $h->code }}</span></span>
                                </div>
                                @if($isActive)
                                    <span class="material-symbols-outlined text-tertiary-container text-base shrink-0">check_circle</span>
                                @endif
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Navigation Links -->
            <nav class="flex flex-col py-3 px-2 space-y-1">
                @php
                    $currRoute = request()->route()?->getName();
                    $openAlertsCount = \App\Models\Alert::whereNull('resolved_at')->count();
                @endphp

                <!-- 1. Calendar -->
                <a href="{{ route('calendar') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded text-xs transition-colors {{ $currRoute === 'calendar' ? 'bg-nav-active-bg text-primary font-semibold border-l-2 border-primary pl-[calc(0.75rem-2px)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-lg shrink-0">calendar_month</span>
                    <span class="font-body-md text-xs">Calendar</span>
                </a>

                <!-- 2. Reservations -->
                <a href="{{ route('reservations') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded text-xs transition-colors {{ $currRoute === 'reservations' ? 'bg-nav-active-bg text-primary font-semibold border-l-2 border-primary pl-[calc(0.75rem-2px)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-lg shrink-0">book_online</span>
                    <span class="font-body-md text-xs">Reservations</span>
                </a>

                <!-- 3. Channels -->
                <a href="{{ route('channels') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded text-xs transition-colors {{ $currRoute === 'channels' ? 'bg-nav-active-bg text-primary font-semibold border-l-2 border-primary pl-[calc(0.75rem-2px)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-lg shrink-0">hub</span>
                    <span class="font-body-md text-xs">Channels</span>
                </a>

                <!-- 4. Sync Logs -->
                <a href="{{ route('sync-logs') }}"
                   class="flex items-center gap-3 px-3 py-2 rounded text-xs transition-colors {{ $currRoute === 'sync-logs' ? 'bg-nav-active-bg text-primary font-semibold border-l-2 border-primary pl-[calc(0.75rem-2px)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <span class="material-symbols-outlined text-lg shrink-0">sync_alt</span>
                    <span class="font-body-md text-xs">Sync Logs</span>
                </a>

                <!-- 5. Alerts -->
                <a href="{{ route('alerts') }}"
                   class="flex items-center justify-between px-3 py-2 rounded text-xs transition-colors {{ $currRoute === 'alerts' ? 'bg-nav-active-bg text-primary font-semibold border-l-2 border-primary pl-[calc(0.75rem-2px)]' : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface' }}">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-lg shrink-0">notifications</span>
                        <span class="font-body-md text-xs">Alerts</span>
                    </div>
                    @if($openAlertsCount > 0)
                        <span class="inline-flex items-center justify-center px-1.5 py-0.5 rounded-full bg-danger-soft text-danger font-label-sm text-[11px] font-bold border border-danger/20" id="sidebarAlertBadge">{{ $openAlertsCount }}</span>
                    @endif
                </a>
            </nav>
        </div>

        <!-- Bottom Status & User Profile -->
        <div class="flex flex-col border-t border-border bg-surface-container-lowest">
            <!-- Channel Health Indicator -->
            <div class="px-4 py-2 border-b border-border flex items-center gap-2 text-xs text-secondary">
                <span class="w-2 h-2 rounded-full bg-[#4C7168] animate-pulse shrink-0" id="sidebarHealthDot"></span>
                <span class="font-label-md text-[11px] text-secondary" id="sidebarHealthText">All channels healthy (3/3 connected)</span>
            </div>

            <!-- Profile Info & Logout -->
            <div class="p-3 flex items-center justify-between hover:bg-surface-container-low transition-colors group">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="w-8 h-8 rounded-full bg-primary/10 border border-border flex items-center justify-center text-primary font-semibold text-xs shrink-0">
                        SR
                    </div>
                    <div class="flex flex-col min-w-0">
                        <span class="font-title-sm text-xs font-semibold text-on-surface truncate">{{ auth()->user()?->name ?? 'Sunita Rao' }}</span>
                        <span class="font-label-sm text-[11px] text-secondary truncate">GM Operations</span>
                    </div>
                </div>
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" title="Logout" class="text-secondary hover:text-danger p-1 rounded transition-colors">
                        <span class="material-symbols-outlined text-base">logout</span>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Container -->
    <div class="ml-60 ml-[240px] min-h-screen flex flex-col bg-background" style="margin-left: 240px;">
        <!-- Top Utility Header -->
        <header class="fixed top-0 left-60 left-[240px] right-0 h-16 bg-surface/90 backdrop-blur-md border-b border-border z-40 px-8 flex items-center justify-between" style="left: 240px;">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-1.5 text-xs text-secondary font-label-md">
                    <span class="font-semibold text-primary">Lotus Residency</span>
                    <span class="text-border">/</span>
                    <span class="text-on-surface-variant">Bengaluru</span>
                </div>
                <div class="h-4 w-px bg-border hidden sm:block"></div>
                <div class="hidden sm:flex items-center gap-1.5 text-secondary font-data-mono text-[11px]">
                    <span class="material-symbols-outlined text-sm text-[#4C7168]">schedule</span>
                    <span id="liveClock">IST {{ now()->timezone('Asia/Kolkata')->format('H:i') }}</span>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="relative hidden md:block">
                    <span class="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-secondary text-base">search</span>
                    <input class="h-8 pl-8 pr-3 bg-surface-container-low border border-border rounded text-xs text-on-surface placeholder:text-secondary focus:outline-none focus:border-tertiary-container w-64 lg:w-72" placeholder="Search reservation, room, guest..." type="text">
                </div>
                <a href="{{ route('alerts') }}" class="relative h-8 w-8 flex items-center justify-center rounded border border-border bg-surface text-secondary hover:text-on-surface hover:bg-surface-container-low transition-colors" title="Notifications">
                    <span class="material-symbols-outlined text-base">notifications</span>
                    @if($openAlertsCount > 0)
                        <span class="absolute top-1 right-1 w-2 h-2 rounded-full bg-danger"></span>
                    @endif
                </a>
                <div class="flex items-center pl-1"></div>
            </div>
        </header>

        <!-- Flash messages -->
        <main class="w-full pt-20 px-8 pb-12 flex-1 bg-background">
            @if(session('success'))
                <div class="my-4 p-3 bg-success-soft border border-[#4C7168]/30 rounded text-xs text-[#113730] flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-[#4C7168]">check_circle</span>
                    <span>{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="my-4 p-3 bg-danger-soft border border-danger/30 rounded text-xs text-danger flex items-center gap-2">
                    <span class="material-symbols-outlined text-sm text-danger">error</span>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @yield('content')
        </main>
    </div>

    <!-- Live Sync Polling & Status Script -->
    <script src="{{ asset('js/extranet.js') }}"></script>
    @stack('scripts')
</body>
</html>
