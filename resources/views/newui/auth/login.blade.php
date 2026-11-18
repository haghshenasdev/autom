<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f7fb">
    <title>ورود به کارنما</title>
    <link rel="icon" href="{{ asset('karnama2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config={theme:{extend:{fontFamily:{sans:['Vazirmatn','sans-serif']},colors:{brand:'#1d4ed8',ink:'#17243a'}}}};</script>
    <style>body{font-family:Vazirmatn,sans-serif}input,button{font:inherit}</style>
</head>
<body class="min-h-screen bg-[#f5f7fb] text-ink antialiased">
<main class="mx-auto flex min-h-screen max-w-6xl items-center justify-center p-4 sm:p-8">
    <div class="grid w-full overflow-hidden rounded-[2rem] bg-white shadow-[0_24px_80px_rgba(23,36,58,.12)] lg:grid-cols-[1fr_1.05fr]">
        <section class="relative hidden min-h-[610px] overflow-hidden bg-gradient-to-br from-[#12316f] via-[#1d4ed8] to-[#3986f6] p-12 text-white lg:flex lg:flex-col lg:justify-between">
            <div class="absolute -left-20 -top-20 h-72 w-72 rounded-full border-[38px] border-white/10"></div>
            <div class="absolute -bottom-24 -right-16 h-80 w-80 rounded-full border border-white/20"></div>
            <img src="{{ asset('karnama.png') }}" alt="کارنما" class="relative z-10 h-16 w-52 object-contain object-right brightness-0 invert">
            <div class="relative z-10 max-w-md">
                <span class="rounded-full bg-white/15 px-4 py-2 text-xs font-semibold">فضای کاری یکپارچه</span>
                <h1 class="mt-7 text-4xl font-extrabold leading-[1.7]">کارها و اسناد سازمان را یک‌جا دنبال کنید</h1>
                <p class="mt-4 text-sm leading-8 text-blue-50">نامه‌ها، صورت‌جلسه‌ها و فعالیت‌های خود را در کارنما مدیریت کنید.</p>
            </div>
            <p class="relative z-10 text-xs text-blue-100">کارنما · سامانه مدیریت کار و مکاتبات</p>
        </section>
        <section class="flex min-h-[610px] items-center justify-center px-6 py-12 sm:px-12">
            <div class="w-full max-w-md">
                <div class="mb-9 lg:hidden"><img src="{{ asset('karnama2.png') }}" alt="کارنما" class="h-14 w-14"><p class="mt-3 text-xs font-semibold text-slate-500">سامانه مدیریت کار و مکاتبات</p></div>
                <p class="text-sm font-semibold text-brand">خوش آمدید</p>
                <h2 class="mt-2 text-3xl font-extrabold">ورود به کارنما</h2>
                <p class="mt-3 text-sm leading-7 text-slate-500">برای ادامه، اطلاعات حساب کاربری خود را وارد کنید.</p>
                @if($errors->any())
                    <div class="mt-6 rounded-2xl border border-rose-100 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ $errors->first() }}</div>
                @endif
                <form method="post" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                    @csrf
                    <label class="block text-sm font-semibold text-slate-700">ایمیل
                        <input type="email" name="email" value="{{ old('email') }}" autocomplete="username" required autofocus class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-left text-sm outline-none transition focus:border-brand focus:bg-white focus:ring-4 focus:ring-blue-100" dir="ltr" placeholder="name@example.com">
                    </label>
                    <label class="block text-sm font-semibold text-slate-700">گذرواژه
                        <input type="password" name="password" autocomplete="current-password" required class="mt-2 w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3.5 text-left text-sm outline-none transition focus:border-brand focus:bg-white focus:ring-4 focus:ring-blue-100" dir="ltr" placeholder="••••••••">
                    </label>
                    <label class="flex items-center gap-2 text-xs text-slate-500"><input type="checkbox" name="remember" value="1" class="rounded border-slate-300 text-brand focus:ring-brand"> مرا به خاطر بسپار</label>
                    <button class="w-full rounded-2xl bg-brand px-5 py-3.5 text-sm font-bold text-white shadow-lg shadow-blue-900/15 transition hover:bg-blue-800 focus:outline-none focus:ring-4 focus:ring-blue-200">ورود به سامانه</button>
                </form>
            </div>
        </section>
    </div>
</main>
</body>
</html>
