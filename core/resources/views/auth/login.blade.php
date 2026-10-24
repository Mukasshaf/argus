<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <title>Login — Argus Hotel Operations</title>
    <link href="https://fonts.googleapis.com" rel="preconnect">
    <link crossorigin="" href="https://fonts.gstatic.com" rel="preconnect">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;family=Noto+Serif:wght@500;600;700&amp;family=Lora:ital,wght@0,400..700;1,400..700&amp;display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { background-color: #fcf9f1; font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-white border border-[#E4E1D9] rounded-2xl p-8 shadow-sm">
        <div class="mb-6 text-center">
            <h1 class="text-3xl font-semibold tracking-tight text-[#111e25]" style="font-family: 'Lora', 'Noto Serif', serif;">Argus</h1>
        </div>

        @if($errors->any())
            <div class="mb-4 p-3 rounded-lg bg-[#FDF2F2] border border-[#AD3E39]/20 text-[#AD3E39] text-xs">
                {{ $errors->first() }}
            </div>
        @endif

        <form action="{{ route('login') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label class="block text-xs font-semibold text-[#57615d] uppercase mb-1">Email Address</label>
                <input type="email" name="email" value="{{ old('email', 'sunita.rao@lotusresidency.in') }}" required autofocus
                       class="w-full h-10 px-3 bg-[#FAF8F5] border border-[#E4E1D9] rounded-lg text-xs text-[#111e25] focus:outline-none focus:border-[#113730]">
            </div>

            <div>
                <label class="block text-xs font-semibold text-[#57615d] uppercase mb-1">Password</label>
                <input type="password" name="password" value="secret" required
                       class="w-full h-10 px-3 bg-[#FAF8F5] border border-[#E4E1D9] rounded-lg text-xs text-[#111e25] focus:outline-none focus:border-[#113730]">
            </div>

            <div class="flex items-center justify-between text-xs text-[#57615d]">
                <label class="flex items-center gap-1.5 cursor-pointer">
                    <input type="checkbox" name="remember" checked class="rounded border-[#E4E1D9] text-[#113730]">
                    <span>Remember me</span>
                </label>
                <span class="text-secondary">Default password: secret</span>
            </div>

            <button type="submit" class="w-full h-10 rounded-lg bg-[#113730] hover:bg-[#111e25] text-white font-semibold text-xs transition-colors shadow-sm mt-2">
                Sign In to Extranet
            </button>
        </form>
    </div>
</body>
</html>
