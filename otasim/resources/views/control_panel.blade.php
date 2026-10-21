<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>OTA Simulator — Control Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 text-gray-800 p-8">
    <div class="max-w-5xl mx-auto space-y-6">
        <div class="flex items-center justify-between border-b pb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">StayBridge OTA Simulator (otasim)</h1>
                <p class="text-xs text-gray-500">Three Simulated OTAs: Raahi (XML), Sarai (JSON), Mela (CSV)</p>
            </div>
            <div class="text-xs bg-emerald-50 text-emerald-700 px-3 py-1.5 rounded-full font-semibold border border-emerald-200">
                Port 8081 · {{ $stateCount }} Replica State Records
            </div>
        </div>

        @if(session('success'))
            <div class="p-3 bg-green-50 border border-green-200 text-green-700 text-xs rounded-lg">
                {{ session('success') }}
            </div>
        @endif

        <!-- Fault Mode Controls -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            @foreach(['raahi' => 'Raahi Stays (XML)', 'sarai' => 'Sarai Direct (JSON)', 'mela' => 'Mela Bulk (CSV)'] as $code => $name)
                @php
                    $cfg = $configs->get($code);
                    $currentMode = $cfg?->mode ?? 'ok';
                @endphp
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm space-y-4">
                    <div class="flex items-center justify-between">
                        <h3 class="font-bold text-sm text-gray-900">{{ $name }}</h3>
                        <span class="text-xs px-2 py-0.5 rounded {{ $currentMode === 'ok' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }} font-mono">
                            {{ $currentMode }}
                        </span>
                    </div>

                    <form action="/sim/config" method="POST" class="space-y-3 text-xs">
                        <input type="hidden" name="channel_code" value="{{ $code }}">
                        <div>
                            <label class="block text-gray-500 font-medium mb-1">Fault Mode</label>
                            <select name="mode" class="w-full h-8 px-2 border rounded bg-gray-50">
                                <option value="ok" {{ $currentMode === 'ok' ? 'selected' : '' }}>OK (Normal)</option>
                                <option value="http500" {{ $currentMode === 'http500' ? 'selected' : '' }}>HTTP 500 Server Error</option>
                                <option value="http429" {{ $currentMode === 'http429' ? 'selected' : '' }}>HTTP 429 Rate Limit</option>
                                <option value="reject400" {{ $currentMode === 'reject400' ? 'selected' : '' }}>HTTP 400 Permanent Error</option>
                                @if($code === 'sarai')
                                    <option value="token_expired" {{ $currentMode === 'token_expired' ? 'selected' : '' }}>HTTP 401 Token Expired</option>
                                @endif
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-500 font-medium mb-1">Artificial Latency (ms)</label>
                            <input type="number" name="latency_ms" value="{{ $cfg?->latency_ms ?? 0 }}" class="w-full h-8 px-2 border rounded bg-gray-50">
                        </div>
                        <button type="submit" class="w-full h-8 bg-gray-900 text-white font-semibold rounded hover:bg-gray-800 transition-colors">
                            Apply Fault
                        </button>
                    </form>
                </div>
            @endforeach
        </div>

        <!-- Inbound Injection Scenarios -->
        <div class="bg-white p-6 rounded-xl border border-gray-200 shadow-sm space-y-4">
            <h2 class="font-bold text-base text-gray-900">Scenario Injections &amp; Race Triggers</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <form action="/sim/race/simultaneous" method="POST" class="p-4 rounded-lg bg-red-50 border border-red-200 space-y-2">
                    <h4 class="font-bold text-xs text-red-900">Race: Book Last Room</h4>
                    <p class="text-[11px] text-red-700">Fires simultaneous booking on Raahi (Webhook) &amp; Sarai (Request Mode) for same room/night.</p>
                    <button type="submit" class="w-full h-8 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded transition-colors">
                        Simulate Race Now
                    </button>
                </form>

                <form action="/sim/raahi/webhook" method="POST" class="p-4 rounded-lg bg-blue-50 border border-blue-200 space-y-2">
                    <h4 class="font-bold text-xs text-blue-900">Send Raahi Webhook</h4>
                    <p class="text-[11px] text-blue-700">Delivers signed HMAC OTA_HotelResNotifRQ commit snapshot.</p>
                    <button type="submit" class="w-full h-8 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded transition-colors">
                        Send Booking Webhook
                    </button>
                </form>

                <form action="/sim/sarai/booking" method="POST" class="p-4 rounded-lg bg-purple-50 border border-purple-200 space-y-2">
                    <h4 class="font-bold text-xs text-purple-900">Send Sarai Realtime Request</h4>
                    <p class="text-[11px] text-purple-700">Synchronous HTTP booking request; receives 201 or 409.</p>
                    <button type="submit" class="w-full h-8 bg-purple-600 hover:bg-purple-700 text-white font-bold text-xs rounded transition-colors">
                        Send Request Mode
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
