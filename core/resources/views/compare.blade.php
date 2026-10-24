<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Compare Rates — {{ $hotel->name }} | Argus</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Lora:ital,wght@0,500;0,600;1,400&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#1F2421',
                        secondary: '#4A504B',
                        accent: '#8C5E45',
                        surface: '#FDFBF7',
                        'surface-container': '#F4EFEB',
                        'surface-container-high': '#EBE5DF',
                        border: '#D8D1C7',
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-[#F8F6F0] text-primary min-h-screen antialiased flex flex-col font-['Inter',sans-serif]">
    <!-- Header -->
    <header class="bg-surface border-b border-border py-4 px-6 shadow-sm">
        <div class="max-w-6xl mx-auto flex items-center justify-between">
            @php
                $allHotels = \App\Models\Hotel::all();
            @endphp
            <div class="flex items-center gap-3.5">
                <div class="w-9 h-9 rounded-lg bg-primary text-white flex items-center justify-center font-bold text-xs tracking-wider font-mono shadow-sm">
                    {{ strtoupper(substr($hotel->code, 0, 3)) }}
                </div>
                <div class="relative">
                    <button type="button"
                            onclick="document.getElementById('compareHotelDropdown').classList.toggle('hidden'); document.getElementById('compareDropdownArrow').classList.toggle('rotate-180');"
                            class="flex items-center gap-1.5 text-left group cursor-pointer focus:outline-none">
                        <div>
                            <div class="flex items-center gap-1">
                                <h1 class="text-lg font-bold tracking-tight text-primary font-['Lora',serif] group-hover:text-accent transition-colors">{{ $hotel->name }}</h1>
                                <span class="material-symbols-outlined text-secondary text-base transition-transform duration-200 group-hover:text-accent" id="compareDropdownArrow">expand_more</span>
                            </div>
                            <p class="text-xs text-secondary">{{ $hotel->city }}, India • Currency: INR (₹) • <span class="text-accent underline font-medium">Switch Hotel</span></p>
                        </div>
                    </button>

                    <!-- Dropdown Menu -->
                    <div id="compareHotelDropdown" class="hidden absolute left-0 top-full mt-2 w-72 bg-white border border-border rounded-xl shadow-xl z-50 py-1.5 overflow-hidden ring-1 ring-black/5">
                        <div class="px-3.5 py-2 bg-surface-container border-b border-border flex items-center justify-between">
                            <span class="text-[10px] uppercase font-bold text-secondary tracking-wider">Select Hotel</span>
                            <span class="text-[10px] text-secondary font-medium">{{ $allHotels->count() }} Properties</span>
                        </div>
                        <div class="divide-y divide-border/50">
                            @foreach($allHotels as $h)
                                @php $isActive = $h->id === $hotel->id; @endphp
                                <a href="{{ route('compare', ['hotel' => $h->code, 'checkin' => $checkIn, 'checkout' => $checkOut, 'adults' => $adults]) }}"
                                   class="flex items-center justify-between px-3.5 py-2.5 text-xs hover:bg-[#F4EFEB] transition-colors {{ $isActive ? 'bg-[#EAE5DF] font-semibold text-primary' : 'text-primary' }}">
                                    <div>
                                        <div class="font-medium text-sm">{{ $h->name }}</div>
                                        <div class="text-[11px] text-secondary mt-0.5">{{ $h->city }} · <span class="font-mono text-[10px]">{{ $h->code }}</span></div>
                                    </div>
                                    @if($isActive)
                                        <span class="material-symbols-outlined text-base text-accent">check_circle</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
            <a href="{{ route('login') }}" class="text-xs font-semibold text-accent hover:underline flex items-center gap-1">
                <span>Staff Extranet</span>
                <span class="material-symbols-outlined text-sm">arrow_forward</span>
            </a>
        </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-6xl mx-auto w-full p-6 flex-1 flex flex-col gap-6">
        <!-- Date Selector Card -->
        <div class="bg-white rounded-xl border border-border p-5 shadow-sm">
            <form method="GET" action="{{ route('compare', ['hotel' => $hotel->code]) }}" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Check-in</label>
                    <input type="date" name="checkin" value="{{ $checkIn }}" class="w-full h-10 px-3 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Check-out</label>
                    <input type="date" name="checkout" value="{{ $checkOut }}" class="w-full h-10 px-3 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-secondary uppercase tracking-wider mb-1">Guests</label>
                    <input type="number" name="adults" min="1" max="10" value="{{ $adults }}" class="w-full h-10 px-3 text-sm border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-accent">
                </div>
                <div>
                    <button type="submit" class="w-full h-10 bg-primary hover:bg-[#333a35] text-white font-semibold text-sm rounded-lg transition-colors flex items-center justify-center gap-1.5 shadow-sm">
                        <span class="material-symbols-outlined text-base">search</span>
                        <span>Update Rates</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Section -->
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-bold font-['Lora',serif] text-primary">Channel Rate Comparison</h2>
                    <p class="text-xs text-secondary mt-0.5">Stay: {{ $checkIn }} to {{ $checkOut }} ({{ $nightsCount }} night{{ $nightsCount > 1 ? 's' : '' }}) • Rates include channel markups</p>
                </div>
                <span class="px-2.5 py-1 bg-[#E8F0EA] text-[#245934] rounded-full text-xs font-semibold border border-[#245934]/20 flex items-center gap-1.5">
                    <span class="w-2 h-2 rounded-full bg-[#245934] animate-pulse"></span>
                    Direct Live Rates
                </span>
            </div>

            @if(empty($offers))
                <div class="bg-white rounded-xl border border-dashed border-border p-12 text-center flex flex-col items-center justify-center">
                    <span class="material-symbols-outlined text-4xl text-secondary mb-2">hotel</span>
                    <h3 class="text-base font-semibold text-primary">No Rooms Available for Selected Dates</h3>
                    <p class="text-xs text-secondary mt-1 max-w-md">There is currently no open inventory across connected channels for this date range. Try selecting different dates.</p>
                </div>
            @else
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    @foreach($offers as $offer)
                        <div class="bg-white rounded-xl border border-border p-5 shadow-sm flex flex-col justify-between hover:shadow-md transition-shadow">
                            <div class="flex flex-col gap-2">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs uppercase tracking-wider font-bold px-2 py-0.5 rounded bg-surface-container border border-border text-secondary">
                                        {{ $offer['channel_name'] }}
                                    </span>
                                    <span class="text-[11px] font-mono text-secondary">
                                        +{{ $offer['markup_bps'] / 100 }}% markup
                                    </span>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-primary font-['Lora',serif]">{{ $offer['room_type_name'] }}</h3>
                                    <p class="text-xs text-secondary">{{ $offer['rate_plan_name'] }} ({{ $offer['rate_plan_code'] }})</p>
                                </div>
                            </div>

                            <div class="mt-6 pt-4 border-t border-border flex items-end justify-between">
                                <div>
                                    <span class="text-[10px] uppercase font-semibold text-secondary block">Total for {{ $offer['nights_count'] }} night(s)</span>
                                    <div class="text-xl font-bold font-['JetBrains_Mono',monospace] text-primary">
                                        ₹{{ number_format($offer['total_price'], 0, '.', ',') }}
                                    </div>
                                    <span class="text-[11px] text-secondary">₹{{ number_format($offer['price_per_night'], 0, '.', ',') }} / night</span>
                                </div>
                                <span class="px-2.5 py-1 text-xs font-semibold rounded bg-[#F4EFEB] text-accent border border-border">
                                    Protocol: {{ strtoupper($offer['protocol']) }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-border py-4 px-6 text-center text-xs text-secondary">
        &copy; {{ date('Y') }} Argus
    </footer>

    <script>
        document.addEventListener('click', function(e) {
            const dropdown = document.getElementById('compareHotelDropdown');
            const arrow = document.getElementById('compareDropdownArrow');
            if (dropdown && !dropdown.contains(e.target) && !e.target.closest('button[onclick*="compareHotelDropdown"]')) {
                dropdown.classList.add('hidden');
                if (arrow) arrow.classList.remove('rotate-180');
            }
        });
    </script>
</body>
</html>
