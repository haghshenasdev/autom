<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f7fb">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>کارنما | پیشخوان</title>
    <link rel="icon" href="{{ asset('karnama2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
            colors: { ink: '#17243a', muted: '#718096', brand: '#1d4ed8', 'brand-soft': '#eaf1ff', canvas: '#f5f7fb' },
            boxShadow: { card: '0 12px 36px rgba(23, 36, 58, .06)' }
        } } };
    </script>
    @include('newui.partials.spa-head')
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: Vazirmatn, sans-serif; background: #f5f7fb; }
        .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        input, button, a { font: inherit; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] [data-filter-chevron] { transform: rotate(180deg); }
        .datepicker-plot-area { z-index: 100 !important; font-family: Vazirmatn, sans-serif !important; }
    </style>
</head>
<body class="min-h-screen text-ink antialiased">
<div id="newui-spa"><div id="newui-page">
@php
    $nav = [
        ['id' => 'home', 'label' => 'پیشخوان', 'icon' => 'home'],
        ['id' => 'cartable', 'label' => 'کارپوشه', 'icon' => 'briefcase'],
        ['id' => 'tasks', 'label' => 'فعالیت‌ها', 'icon' => 'check'],
        ['id' => 'letters', 'label' => 'نامه‌ها', 'icon' => 'mail'],
        ['id' => 'minutes', 'label' => 'صورت‌جلسه‌ها', 'icon' => 'document'],
        ['id' => 'calendar', 'label' => 'تقویم', 'icon' => 'calendar'],
        ['id' => 'assistant', 'label' => 'دستیار هوشمند', 'icon' => 'spark'],
    ];
    $dateText = function ($value) {
        if (!$value) return 'بدون تاریخ';
        try { return \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d'); }
        catch (\Throwable $exception) { return \Illuminate\Support\Carbon::parse($value)->format('Y/m/d'); }
    };
    $showTask = fn ($record) => url('/newui/task/'.$record->id);
    $showLetter = fn ($record) => url('/newui/letter/'.$record->id);
    $showMinutes = fn ($record) => url('/newui/minute/'.$record->id);
    $avatarUrl = fn ($person) => $person?->getFilamentAvatarUrl();
    $createUrl = match ($activeView) {
        'tasks' => \App\Filament\Resources\TaskResource::getUrl('create'),
        'letters' => \App\Filament\Resources\LetterResource::getUrl('create'),
        'minutes' => \App\Filament\Resources\MinutesResource::getUrl('create'),
        default => null,
    };
    $canCreateCurrent = match ($activeView) {
        'tasks' => $canCreateTask,
        'letters' => $canCreateLetter,
        'minutes' => $canCreateMinutes,
        default => false,
    };
    $sectionTitle = [
        'home' => 'پیشخوان', 'cartable' => 'کارپوشه', 'tasks' => 'فعالیت‌ها', 'letters' => 'نامه‌ها', 'minutes' => 'صورت‌جلسه‌ها', 'calendar' => 'تقویم فعالیت‌ها',
    ][$activeView];
@endphp

<div class="min-h-screen lg:flex">
    <aside class="hidden lg:flex lg:w-72 lg:flex-col lg:border-l lg:border-slate-200/80 lg:bg-white lg:px-5 lg:py-7">
        <a href="{{ url('/newui') }}" class="mb-10 flex items-center gap-3 px-2">
            <img src="{{ asset('karnama.png') }}" alt="کارنما" class="h-14 w-full object-contain object-right">
        </a>
        <p class="mb-3 px-3 text-[11px] font-bold tracking-wider text-slate-400">فضای کاری</p>
        <nav class="space-y-1">
            @foreach($nav as $item)
                @if($item['id'] === 'tasks' && !$canTasks) @continue @endif
                @if($item['id'] === 'cartable' && !$canCartable && !$canTasks) @continue @endif
                @if($item['id'] === 'calendar' && !$canTasks) @continue @endif
                @if($item['id'] === 'assistant' && !$canAssistant) @continue @endif
                @if($item['id'] === 'letters' && !$canLetters) @continue @endif
                @if($item['id'] === 'minutes' && !$canMinutes) @continue @endif
                <a href="{{ $item['id'] === 'assistant' ? url('/newui/assistant') : url('/newui').($item['id'] === 'home' ? '' : '?view='.$item['id']) }}"
                   class="flex items-center gap-3 rounded-2xl px-4 py-3 text-sm font-semibold transition {{ $activeView === $item['id'] ? 'bg-brand-soft text-brand' : 'text-slate-600 hover:bg-slate-50' }}">
                    @include('newui.partials.icon', ['name' => $item['icon'], 'class' => 'h-5 w-5'])
                    <span>{{ $item['label'] }}</span>
                    @if($item['id'] === 'tasks') <span class="mr-auto rounded-full bg-white px-2 py-0.5 text-xs text-brand">{{ $pendingTaskCount }}</span> @endif
                </a>
            @endforeach
        </nav>
        <div class="mt-auto rounded-3xl bg-slate-900 p-5 text-white">
            <span class="mb-4 grid h-10 w-10 place-items-center rounded-2xl bg-white/10 text-blue-300">
                @include('newui.partials.icon', ['name' => 'spark', 'class' => 'h-5 w-5'])
            </span>
            <p class="font-bold">همه چیز مرتب‌تر</p>
            <p class="mt-1 text-xs leading-6 text-slate-300">فعالیت‌ها و اسناد روزانه‌ات را از یک جا دنبال کن.</p>
            <a href="{{ url('/admin') }}" class="mt-4 inline-flex items-center gap-2 text-xs font-bold text-blue-300">رفتن به پنل کامل <span>←</span></a>
        </div>
    </aside>

    <main class="mx-auto min-w-0 w-full max-w-7xl overflow-x-clip px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-10 lg:pt-8">
        <header class="mb-7 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3 lg:hidden">
                <span class="grid h-11 w-11 place-items-center overflow-hidden rounded-2xl bg-white shadow-sm"><img src="{{ asset('karnama2.png') }}" alt="کارنما" class="h-10 w-10 object-contain"></span>
                <div><p class="text-xs text-muted">کارنما</p><h1 class="text-base font-extrabold">{{ $sectionTitle }}</h1></div>
            </div>
            <div class="hidden lg:block">
                <p class="text-sm text-muted">سلام {{ auth()->user()->name }}، خوش آمدی</p>
                <h1 class="mt-1 text-2xl font-extrabold">{{ $sectionTitle }}</h1>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ url('/admin') }}" class="hidden rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:border-brand hover:text-brand sm:inline-flex">پنل فیلامنت</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button class="hidden rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-xs font-bold text-slate-600 transition hover:border-rose-200 hover:text-rose-600 sm:inline-flex">خروج</button></form>
                <div class="flex items-center gap-2 rounded-full border border-slate-200 bg-white py-1 pl-3 pr-1">
                    @if(auth()->user()->avatar_url)
                        <img src="{{ url('/profiles/'.auth()->user()->avatar_url) }}" alt="" class="h-9 w-9 rounded-full object-cover">
                    @else
                        <span class="grid h-9 w-9 place-items-center rounded-full bg-brand-soft text-sm font-bold text-brand">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                    @endif
                    <span class="hidden max-w-28 truncate text-xs font-bold sm:block">{{ auth()->user()->name }}</span>
                </div>
            </div>
        </header>

        <form action="{{ url('/newui') }}" method="get" class="mb-6 flex min-w-0 gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm">
            @if($activeView !== 'home') <input type="hidden" name="view" value="{{ $activeView }}"> @endif
            @if($activeView === 'tasks') <input type="hidden" name="task_status" value="{{ $taskStatus }}"> @endif
            @if($activeView === 'cartable') <input type="hidden" name="cartable_checked" value="{{ $cartableChecked }}"><input type="hidden" name="task_due" value="{{ $taskDue }}"> @endif
            <span class="grid w-10 shrink-0 place-items-center text-slate-400">@include('newui.partials.icon', ['name' => 'search', 'class' => 'h-5 w-5'])</span>
            <input name="q" value="{{ $search }}" placeholder="جست‌وجو در {{ $sectionTitle }}..." class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none ring-0 placeholder:text-slate-400 focus:ring-0">
            <button class="shrink-0 rounded-xl bg-brand px-3 py-2 text-xs font-bold text-white transition hover:bg-blue-800 sm:px-4">جست‌وجو</button>
        </form>

        @if(in_array($activeView, ['tasks', 'letters', 'minutes', 'calendar', 'cartable'], true))
            <details class="mb-6 min-w-0 overflow-visible rounded-3xl border border-slate-100 bg-white shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-4 text-sm font-bold marker:hidden sm:px-5">
                    <span class="flex min-w-0 items-center gap-2"><span class="text-brand">☷</span><span>فیلتر نتایج</span></span>
                    <span class="shrink-0 rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-semibold text-brand">باز کردن فیلترها <span data-filter-chevron class="mr-1 inline-block transition-transform">⌄</span></span>
                </summary>
                <form action="{{ url('/newui') }}" method="get" class="min-w-0 border-t border-slate-100 p-4 sm:p-5">
                    <input type="hidden" name="view" value="{{ $activeView }}">
                    @if($search !== '') <input type="hidden" name="q" value="{{ $search }}"> @endif
                    @if($activeView === 'cartable')
                        <div class="mb-3 grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2">
                            @if($canCartable)<label class="min-w-0 text-xs font-semibold text-slate-600">نامه‌های کارپوشه<select name="cartable_checked" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm"><option value="unread" @selected($cartableChecked === 'unread')>خوانده‌نشده</option><option value="checked" @selected($cartableChecked === 'checked')>خوانده‌شده</option><option value="all" @selected($cartableChecked === 'all')>همه نامه‌ها</option></select></label>@endif
                            @if($canTasks)<label class="min-w-0 text-xs font-semibold text-slate-600">فعالیت‌ها<select name="task_due" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm">@foreach(['open' => 'باز', 'overdue' => 'عقب‌افتاده', 'today' => 'امروز', 'upcoming' => 'آینده', 'without_deadline' => 'بدون مهلت', 'completed' => 'انجام‌شده', 'all' => 'همه'] as $key => $label)<option value="{{ $key }}" @selected($taskDue === $key)>{{ $label }}</option>@endforeach</select></label>@endif
                        </div>
                    @endif
                    <div class="grid min-w-0 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    @if(in_array($activeView, ['tasks', 'calendar'], true))
                        <label class="min-w-0 text-xs font-semibold text-slate-600">وضعیت انجام
                            <select name="task_status" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand">
                                @foreach(['pending' => 'انجام‌نشده‌ها', 'done' => 'انجام‌شده‌ها', 'all' => 'همه'] as $key => $label)
                                    <option value="{{ $key }}" @selected($taskStatus === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">دسته‌بندی
                            <select name="group_id" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="">همه دسته‌ها</option>@foreach($filterOptions['groups'] as $id => $name)<option value="{{ $id }}" @selected(($filters['group_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
                        </label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">دستورکار
                            <select name="project_id" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="">همه دستورکارها</option>@foreach($filterOptions['projects'] as $id => $name)<option value="{{ $id }}" @selected(($filters['project_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
                        </label>
                        @if($activeView === 'tasks')
                            <label class="min-w-0 text-xs font-semibold text-slate-600">مهلت از تاریخ<input type="text" name="date_from" value="{{ $filterDateValues['date_from'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                            <label class="min-w-0 text-xs font-semibold text-slate-600">مهلت تا تاریخ<input type="text" name="date_to" value="{{ $filterDateValues['date_to'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                        @endif
                    @elseif($activeView === 'letters')
                        <label class="min-w-0 text-xs font-semibold text-slate-600">نوع نامه
                            <select name="letter_kind" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="all">همه</option><option value="incoming" @selected(($filters['letter_kind'] ?? '') === 'incoming')>وارده</option><option value="outgoing" @selected(($filters['letter_kind'] ?? '') === 'outgoing')>صادره</option></select>
                        </label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">دسته‌بندی
                            <select name="letter_type_id" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="">همه دسته‌ها</option>@foreach($filterOptions['letterTypes'] as $id => $name)<option value="{{ $id }}" @selected(($filters['letter_type_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
                        </label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">وضعیت
                            <select name="letter_status" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="">همه وضعیت‌ها</option>@foreach($filterOptions['letterStatuses'] as $id => $name)<option value="{{ $id }}" @selected(($filters['letter_status'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
                        </label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">از تاریخ<input type="text" name="date_from" value="{{ $filterDateValues['date_from'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">تا تاریخ<input type="text" name="date_to" value="{{ $filterDateValues['date_to'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                    @elseif($activeView === 'minutes')
                        @if($filterOptions['authors']->isNotEmpty())
                            <label class="min-w-0 text-xs font-semibold text-slate-600">نویسنده
                                <select name="author_id" class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-sm focus:border-brand focus:ring-brand"><option value="">همه نویسندگان</option>@foreach($filterOptions['authors'] as $id => $name)<option value="{{ $id }}" @selected(($filters['author_id'] ?? '') == $id)>{{ $name }}</option>@endforeach</select>
                            </label>
                        @endif
                        <label class="min-w-0 text-xs font-semibold text-slate-600">از تاریخ جلسه<input type="text" name="date_from" value="{{ $filterDateValues['date_from'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                        <label class="min-w-0 text-xs font-semibold text-slate-600">تا تاریخ جلسه<input type="text" name="date_to" value="{{ $filterDateValues['date_to'] }}" placeholder="۱۴۰۵/۰۱/۰۱" inputmode="numeric" dir="ltr" autocomplete="off" data-jalali-datepicker class="mt-1.5 w-full rounded-xl border-slate-200 bg-slate-50 text-left text-sm focus:border-brand focus:ring-brand"></label>
                    @endif
                        <div class="flex items-end gap-2"><button class="flex-1 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-800">اعمال فیلتر</button><a href="{{ url('/newui?view='.$activeView) }}" class="rounded-xl border border-slate-200 px-4 py-2.5 text-xs font-bold text-slate-500">پاک‌کردن</a></div>
                    </div>
                </form>
            </details>
        @endif

        @if($activeView === 'home')
            <section class="relative mb-6 overflow-hidden rounded-[2rem] bg-gradient-to-l from-[#12316f] via-[#1d4ed8] to-[#3986f6] px-6 py-7 text-white shadow-card sm:px-8 sm:py-9">
                <div class="relative z-10 max-w-xl">
                    <span class="inline-flex items-center gap-2 rounded-full bg-white/15 px-3 py-1.5 text-[11px] font-semibold text-blue-50"><span class="h-1.5 w-1.5 rounded-full bg-blue-200"></span> فضای کاری شخصی</span>
                    <h2 class="mt-4 text-2xl font-extrabold leading-relaxed sm:text-3xl">امروزت را با تمرکز شروع کن، {{ auth()->user()->name }}!</h2>
                    <p class="mt-2 max-w-lg text-sm leading-7 text-blue-50/90">کارهای در انتظار و تازه‌ترین اسناد سازمان را یک‌جا ببین.</p>
                    @if($canTasks)<a href="{{ url('/newui?view=tasks') }}" class="mt-5 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-xs font-extrabold text-brand shadow-lg transition hover:-translate-y-0.5">مشاهده کارهای من <span>←</span></a>@endif
                </div>
                <div class="pointer-events-none absolute -left-8 -top-16 h-64 w-64 rounded-full border-[36px] border-white/5"></div>
                <div class="pointer-events-none absolute -left-2 bottom-[-7rem] h-64 w-64 rounded-full border-[1px] border-white/10"></div>
                <div class="pointer-events-none absolute left-[18%] top-10 hidden h-16 w-16 rotate-12 rounded-2xl border border-white/15 bg-white/5 sm:block"></div>
            </section>

            <section class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                @if($canTasks)
                    <a href="{{ url('/newui?view=tasks') }}" class="rounded-3xl border border-slate-100 bg-white p-4 shadow-card transition hover:-translate-y-1 sm:p-5">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-amber-50 text-amber-600">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-5 w-5'])</span>
                        <p class="mt-4 text-2xl font-extrabold">{{ number_format($pendingTaskCount) }}</p><p class="mt-1 text-xs text-muted">فعالیت انجام‌نشده</p>
                    </a>
                @endif
                @if($canTasks)
                    <a href="{{ url('/newui?view=tasks') }}" class="rounded-3xl border border-slate-100 bg-white p-4 shadow-card transition hover:-translate-y-1 sm:p-5">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-brand-soft text-brand">@include('newui.partials.icon', ['name' => 'briefcase', 'class' => 'h-5 w-5'])</span>
                        <p class="mt-4 text-2xl font-extrabold">{{ number_format($taskCount) }}</p><p class="mt-1 text-xs text-muted">کل فعالیت‌های من</p>
                    </a>
                @endif
                @if($canLetters)
                    <a href="{{ url('/newui?view=letters') }}" class="rounded-3xl border border-slate-100 bg-white p-4 shadow-card transition hover:-translate-y-1 sm:p-5">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5'])</span>
                        <p class="mt-4 text-2xl font-extrabold">{{ number_format($letterCount) }}</p><p class="mt-1 text-xs text-muted">نامه‌های قابل دسترس</p>
                    </a>
                @endif
                @if($canMinutes)
                    <a href="{{ url('/newui?view=minutes') }}" class="rounded-3xl border border-slate-100 bg-white p-4 shadow-card transition hover:-translate-y-1 sm:p-5">
                        <span class="grid h-10 w-10 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'document', 'class' => 'h-5 w-5'])</span>
                        <p class="mt-4 text-2xl font-extrabold">{{ number_format($minuteCount) }}</p><p class="mt-1 text-xs text-muted">صورت‌جلسه‌ها</p>
                    </a>
                @endif
            </section>

            <div class="mb-4 flex items-center justify-between">
                <div><h2 class="text-lg font-extrabold">کارهای پیش رو</h2><p class="mt-1 text-xs text-muted">فعالیت‌های انجام‌نشدهٔ شما</p></div>
                @if($canTasks)<a href="{{ url('/newui?view=tasks') }}" class="text-xs font-bold text-brand">همه فعالیت‌ها <span>←</span></a>@endif
            </div>
            @if(!$canTasks)
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-10 text-center text-sm text-muted">برای نمایش اطلاعات، دسترسی لازم در پنل تعریف نشده است.</div>
            @elseif($items['tasks']->isEmpty())
                <div class="rounded-3xl border border-slate-100 bg-white px-5 py-10 text-center shadow-sm"><span class="mx-auto grid h-12 w-12 place-items-center rounded-2xl bg-brand-soft text-brand">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-6 w-6'])</span><p class="mt-3 font-bold">فعلاً کار انجام‌نشده‌ای نداری</p><p class="mt-1 text-xs text-muted">وقتی فعالیتی به شما واگذار شود اینجا می‌بینید.</p></div>
            @else
                <div class="space-y-3">
                    @foreach($items['tasks'] as $task)
                        <a href="{{ $showTask($task) }}" class="group flex min-w-0 items-start gap-3 overflow-hidden rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:items-center sm:p-5">
                            <span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-amber-50 text-amber-600">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-5 w-5'])</span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold group-hover:text-brand">{{ $task->name }}</span><span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted"><span class="break-words">{{ $task->project->pluck('name')->filter()->take(2)->implode('، ') ?: 'بدون دستورکار' }}</span><span>مهلت: {{ $dateText($task->ended_at) }}</span></span>
                                @if($task->responsible)
                                    <span class="mt-2 inline-flex max-w-full items-center gap-2 text-[11px] text-muted">
                                        @if($avatarUrl($task->responsible)) <img src="{{ $avatarUrl($task->responsible) }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover"> @else <span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand">{{ mb_substr($task->responsible->name ?? '؟', 0, 1) }}</span> @endif
                                        <span class="truncate">مسئول: {{ $task->responsible->name }}</span>
                                    </span>
                                @endif
                                @if($task->progress)<span class="mt-3 flex items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $task->progress)) }}%"></span></span><span class="text-[10px] text-muted">{{ $task->progress }}٪</span></span>@endif
                            </span>
                            <span class="hidden self-center text-slate-300 group-hover:text-brand sm:block">←</span>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="mt-9 grid gap-6 lg:grid-cols-2">
                @if($canLetters)
                    <section>
                        <div class="mb-4 flex items-center justify-between"><div><h2 class="font-extrabold">نامه‌های اخیر</h2><p class="mt-1 text-xs text-muted">آخرین تغییرات دبیرخانه</p></div><a href="{{ url('/newui?view=letters') }}" class="text-xs font-bold text-brand">مشاهده همه</a></div>
                        <div class="space-y-2">
                            @forelse($items['letters'] as $letter)
                                <a href="{{ $showLetter($letter) }}" class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 transition hover:border-blue-200">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5'])</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $letter->subject }}</span><span class="mt-1 block text-[11px] text-muted">{{ $letter->type?->name ?? 'نامه' }} · {{ $dateText($letter->created_at) }}</span></span><span class="text-slate-300">←</span>
                                </a>
                            @empty <p class="rounded-2xl bg-white p-5 text-center text-xs text-muted">نامه‌ای برای نمایش نیست.</p> @endforelse
                        </div>
                    </section>
                @endif
                @if($canMinutes)
                    <section>
                        <div class="mb-4 flex items-center justify-between"><div><h2 class="font-extrabold">صورت‌جلسه‌های اخیر</h2><p class="mt-1 text-xs text-muted">آخرین نشست‌های ثبت‌شده</p></div><a href="{{ url('/newui?view=minutes') }}" class="text-xs font-bold text-brand">مشاهده همه</a></div>
                        <div class="space-y-2">
                            @forelse($items['minutes'] as $minute)
                                <a href="{{ $showMinutes($minute) }}" class="flex min-w-0 items-center gap-3 overflow-hidden rounded-2xl border border-slate-100 bg-white p-4 transition hover:border-blue-200">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'document', 'class' => 'h-5 w-5'])</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $minute->title }}</span><span class="mt-1 flex min-w-0 items-center gap-2 text-[11px] text-muted">@if($avatarUrl($minute->typer))<img src="{{ $avatarUrl($minute->typer) }}" alt="" class="h-5 w-5 shrink-0 rounded-full object-cover">@else<span class="grid h-5 w-5 shrink-0 place-items-center rounded-full bg-brand-soft text-[10px] font-bold text-brand">{{ mb_substr($minute->typer?->name ?? '؟', 0, 1) }}</span>@endif<span class="truncate">{{ $minute->typer?->name ?? 'بدون نویسنده' }} · {{ $dateText($minute->date) }}</span></span></span><span class="shrink-0 text-slate-300">←</span>
                                </a>
                            @empty <p class="rounded-2xl bg-white p-5 text-center text-xs text-muted">صورت‌جلسه‌ای برای نمایش نیست.</p> @endforelse
                        </div>
                    </section>
                @endif
            </div>
        @elseif($activeView === 'cartable')
            @if(session('success'))<div class="mb-5 rounded-2xl border border-emerald-100 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">{{ session('success') }}</div>@endif
            <section class="mb-5 flex flex-wrap items-end justify-between gap-3"><div><p class="text-xs text-muted">نامه‌های ارجاع‌شده و پیگیری‌های من</p><h2 class="mt-1 text-xl font-extrabold">کارپوشه</h2></div></section>
            @if($canCartable)
                <section class="mb-8">
                    <div class="mb-3 flex items-center justify-between"><h3 class="font-extrabold">نامه‌های ارجاع‌شده</h3><span class="text-xs text-muted">{{ $cartableLetters->total() }} نامه</span></div>
                    @forelse($cartableLetters as $entry)
                        @if($entry->letter)
                            <article class="mb-3 flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5">
                                <a href="{{ $showLetter($entry->letter) }}" class="min-w-0 flex-1"><span class="block break-words font-bold text-slate-800 hover:text-brand">{{ $entry->letter->subject ?: 'نامه شماره '.$entry->letter->id }}</span><span class="mt-1 block text-xs text-muted">{{ $entry->letter->kind ? 'صادره' : 'وارده' }} · {{ $dateText($entry->letter->created_at) }} · {{ $entry->letter->organ?->name }}</span></a>
                                @if($canUpdateCartable)<form method="post" action="{{ route('newui.cartable.letters.checked', $entry->id) }}" class="shrink-0">@csrf<input type="hidden" name="checked" value="{{ $entry->checked ? 0 : 1 }}"><button class="rounded-xl px-3 py-2 text-xs font-bold {{ $entry->checked ? 'bg-amber-50 text-amber-700' : 'bg-blue-50 text-blue-700' }}">{{ $entry->checked ? 'علامت‌گذاری به‌عنوان خوانده‌نشده' : 'علامت‌گذاری به‌عنوان خوانده‌شده' }}</button></form>@endif
                            </article>
                        @endif
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-muted">نامه‌ای در این بخش نیست.</div>
                    @endforelse
                    <div class="mt-4">{{ $cartableLetters->links() }}</div>
                </section>
            @endif
            @if($canTasks)
                <section>
                    <div class="mb-3 flex items-center justify-between"><h3 class="font-extrabold">فعالیت‌های من</h3><span class="text-xs text-muted">{{ $cartableTasks->total() }} فعالیت</span></div>
                    @forelse($cartableTasks as $task)
                        <article class="mb-3 rounded-3xl border border-slate-100 bg-white p-4 shadow-sm sm:p-5">
                            <div class="flex flex-wrap items-start justify-between gap-3"><div class="min-w-0 flex-1"><a href="{{ $showTask($task) }}" class="block break-words font-bold text-slate-800 hover:text-brand">{{ $task->name }}</a><span class="mt-1 block text-xs text-muted">مهلت: {{ $dateText($task->ended_at) }}@if($task->letter) · <a class="text-brand" href="{{ $showLetter($task->letter) }}">نامه: {{ $task->letter->subject ?: 'شماره '.$task->letter->id }}</a>@endif @if($task->minutes) · <a class="text-brand" href="{{ $showMinutes($task->minutes) }}">صورت‌جلسه: {{ $task->minutes->title }}</a>@endif</span></div><span class="rounded-full px-3 py-1 text-[11px] font-bold {{ $task->completed ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700' }}">{{ $task->completed ? 'انجام‌شده' : 'در حال انجام' }}</span></div>
                            @if($canUpdateTasks)<form method="post" action="{{ route('newui.cartable.tasks.update', $task->id) }}" class="mt-4 flex flex-wrap items-center gap-3 border-t border-slate-100 pt-4">@csrf
                                <label class="flex min-w-[180px] flex-1 items-center gap-3 text-xs text-slate-600"><span>پیشرفت</span><input name="progress" type="range" min="0" max="100" value="{{ $task->progress ?? 0 }}" class="h-2 flex-1 accent-blue-700" oninput="this.nextElementSibling.value=this.value+'%'"/><output class="w-10 text-left" dir="ltr">{{ $task->progress ?? 0 }}%</output></label>
                                <label class="flex items-center gap-2 text-xs font-semibold text-slate-600"><input type="hidden" name="completed" value="0"><input type="checkbox" name="completed" value="1" @checked($task->completed) class="rounded border-slate-300 text-brand focus:ring-brand"> انجام شد</label>
                                <button class="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white">ذخیره</button>
                            </form>@endif
                        </article>
                    @empty
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-6 text-center text-sm text-muted">فعالیتی برای نمایش نیست.</div>
                    @endforelse
                    <div class="mt-4">{{ $cartableTasks->links() }}</div>
                </section>
            @endif
        @elseif($activeView === 'calendar')
            <section class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs text-muted">برنامه‌ریزی و پیگیری</p><h2 class="mt-1 text-xl font-extrabold">تقویم فعالیت‌ها</h2></div>
                <span class="rounded-full bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-700">روی هر فعالیت بزنید تا جزئیات آن باز شود</span>
            </section>
            <section class="min-w-0 overflow-hidden rounded-3xl border border-slate-100 bg-white p-3 shadow-card sm:p-5">
                <div id="calendar-feedback" class="mb-3 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-800" role="status" aria-live="polite">در حال بارگذاری فعالیت‌ها…</div>
                <div id="newui-calendar" class="min-h-[520px] min-w-0"></div>
            </section>
            <style>
                #newui-calendar { min-width: 0; max-width: 100%; overflow-x: auto; --fc-border-color: #e8edf5; --fc-button-bg-color: #1d4ed8; --fc-button-border-color: #1d4ed8; --fc-button-hover-bg-color: #1e40af; --fc-button-hover-border-color: #1e40af; --fc-button-active-bg-color: #1e40af; --fc-button-active-border-color: #1e40af; --fc-today-bg-color: #eff6ff; }
                #newui-calendar .fc-toolbar { gap: .75rem; flex-wrap: wrap; }
                #newui-calendar .fc-toolbar-chunk { min-width: 0; }
                #newui-calendar .fc-scrollgrid { min-width: 0; }
                #newui-calendar .fc-toolbar-title { font-family: Vazirmatn, sans-serif; font-size: 1.05rem; font-weight: 800; }
                #newui-calendar .fc-button { border-radius: .75rem; font-family: Vazirmatn, sans-serif; font-size: .75rem; }
                #newui-calendar .fc-event { border-radius: .5rem; padding: 2px 4px; border: 0; }
                @media (max-width: 640px) { #newui-calendar .fc-toolbar-title { font-size: .9rem; } #newui-calendar .fc-toolbar-chunk { display: flex; justify-content: center; flex-wrap: wrap; } #newui-calendar .fc .fc-button { padding: .35rem .5rem; font-size: .7rem; } }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.22/index.global.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.22/locales-all.global.min.js"></script>
            <script>
                const initializeCalendar = function () {
                    const calendarElement = document.getElementById('newui-calendar');
                    if (!calendarElement) return;
                    if (calendarElement.dataset.vueInitialized === 'true') return;
                    calendarElement.dataset.vueInitialized = 'true';
                    const feedbackElement = document.getElementById('calendar-feedback');
                    if (!window.FullCalendar) {
                        feedbackElement.textContent = 'کتابخانهٔ تقویم بارگذاری نشد. اتصال اینترنت یا دسترسی به CDN را بررسی کنید.';
                        feedbackElement.className = 'mb-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800';
                        return;
                    }
                    const calendar = new FullCalendar.Calendar(calendarElement, {
                        locale: 'fa',
                        direction: 'rtl',
                        initialView: 'dayGridMonth',
                        height: 'auto',
                        fixedWeekCount: false,
                        headerToolbar: { start: 'prev,next today', center: 'title', end: 'dayGridMonth,timeGridWeek,listMonth' },
                        buttonText: { today: 'امروز', month: 'ماه', week: 'هفته', list: 'فهرست' },
                        events: async function (info) {
                            const params = new URLSearchParams({ start: info.startStr, end: info.endStr, task_status: @json($taskStatus) });
                            if (@json($search) !== '') params.set('q', @json($search));
                            @foreach(['group_id', 'project_id'] as $filterKey)
                                if (@json($filters[$filterKey] ?? null)) params.set(@json($filterKey), @json($filters[$filterKey] ?? null));
                            @endforeach
                            const response = await fetch('{{ url('/newui/calendar/events') }}?' + params.toString(), { headers: { 'Accept': 'application/json' } });
                            if (!response.ok) throw new Error('دریافت فعالیت‌ها ناموفق بود (کد ' + response.status + ').');
                            return await response.json();
                        },
                        eventsSet: function (events) {
                            feedbackElement.textContent = events.length ? events.length + ' فعالیت در این بازه نمایش داده می‌شود.' : 'در این بازه فعالیت زمان‌بندی‌شده‌ای پیدا نشد.';
                            feedbackElement.className = 'mb-3 rounded-xl bg-blue-50 px-3 py-2 text-xs text-blue-800';
                        },
                        eventSourceFailure: function (error) {
                            feedbackElement.textContent = error?.message || 'دریافت فعالیت‌ها با خطا روبه‌رو شد.';
                            feedbackElement.className = 'mb-3 rounded-xl bg-rose-50 px-3 py-2 text-xs text-rose-800';
                        },
                        eventClick: function (info) {
                            if (info.event.url) {
                                info.jsEvent.preventDefault();
                                if (window.NewUiSpa) window.NewUiSpa.navigate(info.event.url);
                                else window.location.href = info.event.url;
                            }
                        },
                    });
                    calendar.render();
                };
                if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeCalendar, { once: true });
                else initializeCalendar();
            </script>
        @else
            <section class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs text-muted">مدیریت و پیگیری</p><h2 class="mt-1 text-xl font-extrabold">{{ $sectionTitle }}</h2></div>
                @if($canCreateCurrent)<a href="{{ $createUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-blue-900/10 transition hover:bg-blue-800"><span class="text-lg leading-none">+</span> ثبت مورد جدید</a>@endif
            </section>

            @if($items->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-50 text-slate-400">@include('newui.partials.icon', ['name' => 'search', 'class' => 'h-6 w-6'])</span><p class="mt-4 font-bold">موردی پیدا نشد</p><p class="mt-1 text-xs text-muted">عبارت جست‌وجو یا فیلتر خود را تغییر دهید.</p></div>
            @else
                <div class="space-y-3">
                    @foreach($items as $item)
                        @if($activeView === 'tasks')
                            <a href="{{ $showTask($item) }}" class="group block min-w-0 overflow-hidden rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5">
                                <div class="flex min-w-0 items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl {{ $item->completed ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600' }}">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block break-words font-bold group-hover:text-brand">{{ $item->name }}</span><span class="mt-1 block line-clamp-2 break-words text-xs leading-6 text-muted">{{ $item->description ?: 'توضیحی ثبت نشده است.' }}</span>@if($item->responsible)<span class="mt-2 inline-flex max-w-full items-center gap-2 text-[11px] text-muted">@if($avatarUrl($item->responsible))<img src="{{ $avatarUrl($item->responsible) }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover">@else<span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand">{{ mb_substr($item->responsible->name ?? '؟', 0, 1) }}</span>@endif<span class="truncate">مسئول: {{ $item->responsible->name }}</span></span>@endif</span><span class="shrink-0 text-xs text-slate-300 group-hover:text-brand">←</span></div>
                                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[11px] text-muted"><span class="max-w-full break-words rounded-full bg-slate-50 px-2.5 py-1">{{ $item->project->pluck('name')->filter()->take(2)->implode('، ') ?: 'بدون دستورکار' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">مهلت: {{ $dateText($item->ended_at) }}</span><span class="mr-auto rounded-full px-2.5 py-1 {{ $item->completed ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">{{ $item->completed ? 'انجام شده' : 'در انتظار انجام' }}</span></div>
                                @if($item->progress)<div class="mt-3 flex items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $item->progress)) }}%"></span></span><span class="text-[10px] text-muted">{{ $item->progress }}٪</span></div>@endif
                            </a>
                        @elseif($activeView === 'letters')
                            <a href="{{ $showLetter($item) }}" class="group flex min-w-0 items-start gap-3 overflow-hidden rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block break-words font-bold group-hover:text-blue-700">{{ $item->subject }}</span><span class="mt-1 block line-clamp-2 break-words text-xs leading-6 text-muted">{{ $item->summary ?: ($item->description ?: 'خلاصه‌ای ثبت نشده است.') }}</span><span class="mt-3 flex flex-wrap gap-2 text-[11px] text-muted"><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->kind ? 'صادره' : 'وارده' }}</span><span class="max-w-full break-words rounded-full bg-slate-50 px-2.5 py-1">{{ $item->type?->name ?? 'بدون نوع' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $dateText($item->created_at) }}</span></span></span><span class="shrink-0 text-xs text-slate-300 group-hover:text-blue-600">←</span></a>
                        @else
                            <a href="{{ $showMinutes($item) }}" class="group flex min-w-0 items-start gap-3 overflow-hidden rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'document', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block break-words font-bold group-hover:text-blue-700">{{ $item->title }}</span><span class="mt-1 block line-clamp-2 break-words text-xs leading-6 text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($item->text ?? ''), 150) ?: 'متنی برای این صورت‌جلسه ثبت نشده است.' }}</span><span class="mt-3 inline-flex max-w-full items-center gap-2 text-[11px] text-muted">@if($avatarUrl($item->typer))<img src="{{ $avatarUrl($item->typer) }}" alt="" class="h-6 w-6 shrink-0 rounded-full object-cover">@else<span class="grid h-6 w-6 shrink-0 place-items-center rounded-full bg-brand-soft font-bold text-brand">{{ mb_substr($item->typer?->name ?? '؟', 0, 1) }}</span>@endif<span class="truncate">نویسنده: {{ $item->typer?->name ?? 'نامشخص' }}</span></span><span class="mt-3 flex flex-wrap gap-2 text-[11px] text-muted"><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $dateText($item->date) }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->tasks_count }} فعالیت</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->approves_count }} مصوبه</span></span></span><span class="shrink-0 text-xs text-slate-300 group-hover:text-blue-600">←</span></a>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6">{{ $items->links() }}</div>
            @endif
        @endif
    </main>
</div>

<nav class="safe-bottom fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/95 px-2 pt-2 shadow-[0_-8px_30px_rgba(23,36,58,.06)] backdrop-blur lg:hidden">
    <div class="mx-auto flex max-w-lg items-center justify-start gap-1 overflow-x-auto">
        @foreach($nav as $item)
            @if($item['id'] === 'tasks' && !$canTasks) @continue @endif
            @if($item['id'] === 'cartable' && !$canCartable && !$canTasks) @continue @endif
                @if($item['id'] === 'calendar' && !$canTasks) @continue @endif
            @if($item['id'] === 'assistant' && !$canAssistant) @continue @endif
            @if($item['id'] === 'letters' && !$canLetters) @continue @endif
            @if($item['id'] === 'minutes' && !$canMinutes) @continue @endif
            <a href="{{ $item['id'] === 'assistant' ? url('/newui/assistant') : url('/newui').($item['id'] === 'home' ? '' : '?view='.$item['id']) }}" class="flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-semibold {{ $activeView === $item['id'] ? 'text-brand' : 'text-slate-400' }}">
                @include('newui.partials.icon', ['name' => $item['icon'], 'class' => 'h-5 w-5'])<span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-date@1.1.0/dist/persian-date.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js"></script>
<script>
    const initializeJalaliPickers = function () {
        if (!window.jQuery || !jQuery.fn.persianDatepicker) return;
        jQuery('[data-jalali-datepicker]').each(function () {
            if (this.dataset.vueInitialized === 'true') return;
            this.dataset.vueInitialized = 'true';
            jQuery(this).persianDatepicker({
            format: 'YYYY/MM/DD',
            initialValue: false,
            autoClose: true,
            calendarType: 'persian',
            calendar: { persian: { locale: 'fa', showHint: false, leapYearMode: 'algorithmic' }, gregorian: { enabled: false } },
            toolbox: { calendarSwitch: { enabled: false } },
            timePicker: { enabled: false }
            });
        });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initializeJalaliPickers, { once: true });
    else initializeJalaliPickers();
</script>
</div>
<div id="newui-vue-root"></div>
</div>
@include('newui.partials.spa-router')
</body>
</html>
