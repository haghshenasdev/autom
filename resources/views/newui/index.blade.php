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
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = { theme: { extend: {
            fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
            colors: { ink: '#17243a', muted: '#718096', brand: '#1d4ed8', 'brand-soft': '#eaf1ff', canvas: '#f5f7fb' },
            boxShadow: { card: '0 12px 36px rgba(23, 36, 58, .06)' }
        } } };
    </script>
    <style>
        * { -webkit-tap-highlight-color: transparent; }
        body { font-family: Vazirmatn, sans-serif; background: #f5f7fb; }
        .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
        .line-clamp-2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        input, button, a { font: inherit; }
    </style>
</head>
<body class="min-h-screen text-ink antialiased">
@php
    $nav = [
        ['id' => 'home', 'label' => 'پیشخوان', 'icon' => 'home'],
        ['id' => 'tasks', 'label' => 'فعالیت‌ها', 'icon' => 'check'],
        ['id' => 'letters', 'label' => 'نامه‌ها', 'icon' => 'mail'],
        ['id' => 'minutes', 'label' => 'صورت‌جلسه‌ها', 'icon' => 'document'],
    ];
    $dateText = function ($value) {
        if (!$value) return 'بدون تاریخ';
        try { return \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d'); }
        catch (\Throwable $exception) { return \Illuminate\Support\Carbon::parse($value)->format('Y/m/d'); }
    };
    $showTask = fn ($record) => url('/newui/task/'.$record->id);
    $showLetter = fn ($record) => url('/newui/letter/'.$record->id);
    $showMinutes = fn ($record) => url('/newui/minute/'.$record->id);
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
        'home' => 'پیشخوان', 'tasks' => 'فعالیت‌ها', 'letters' => 'نامه‌ها', 'minutes' => 'صورت‌جلسه‌ها',
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
                @if($item['id'] === 'letters' && !$canLetters) @continue @endif
                @if($item['id'] === 'minutes' && !$canMinutes) @continue @endif
                <a href="{{ url('/newui') }}{{ $item['id'] === 'home' ? '' : '?view='.$item['id'] }}"
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

    <main class="mx-auto w-full max-w-7xl px-4 pb-28 pt-5 sm:px-6 lg:px-10 lg:pb-10 lg:pt-8">
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

        <form action="{{ url('/newui') }}" method="get" class="mb-6 flex gap-2 rounded-2xl border border-slate-200/80 bg-white p-2 shadow-sm">
            @if($activeView !== 'home') <input type="hidden" name="view" value="{{ $activeView }}"> @endif
            @if($activeView === 'tasks') <input type="hidden" name="task_status" value="{{ $taskStatus }}"> @endif
            <span class="grid w-10 shrink-0 place-items-center text-slate-400">@include('newui.partials.icon', ['name' => 'search', 'class' => 'h-5 w-5'])</span>
            <input name="q" value="{{ $search }}" placeholder="جست‌وجو در {{ $sectionTitle }}..." class="min-w-0 flex-1 border-0 bg-transparent text-sm outline-none ring-0 placeholder:text-slate-400 focus:ring-0">
            <button class="rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white transition hover:bg-blue-800">جست‌وجو</button>
        </form>

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
                        <a href="{{ $showTask($task) }}" class="group flex items-start gap-3 rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:items-center sm:p-5">
                            <span class="mt-0.5 grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-amber-50 text-amber-600">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-5 w-5'])</span>
                            <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold group-hover:text-brand">{{ $task->name }}</span><span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-muted"><span>{{ $task->project->pluck('name')->filter()->take(2)->implode('، ') ?: 'بدون دستورکار' }}</span><span>مهلت: {{ $dateText($task->ended_at) }}</span></span>
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
                                <a href="{{ $showMinutes($minute) }}" class="flex items-center gap-3 rounded-2xl border border-slate-100 bg-white p-4 transition hover:border-blue-200">
                                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'document', 'class' => 'h-5 w-5'])</span>
                                    <span class="min-w-0 flex-1"><span class="block truncate text-sm font-bold">{{ $minute->title }}</span><span class="mt-1 block text-[11px] text-muted">{{ $minute->typer?->name ?? 'بدون نویسنده' }} · {{ $dateText($minute->date) }}</span></span><span class="text-slate-300">←</span>
                                </a>
                            @empty <p class="rounded-2xl bg-white p-5 text-center text-xs text-muted">صورت‌جلسه‌ای برای نمایش نیست.</p> @endforelse
                        </div>
                    </section>
                @endif
            </div>
        @else
            <section class="mb-5 flex flex-wrap items-end justify-between gap-3">
                <div><p class="text-xs text-muted">مدیریت و پیگیری</p><h2 class="mt-1 text-xl font-extrabold">{{ $sectionTitle }}</h2></div>
                @if($canCreateCurrent)<a href="{{ $createUrl }}" class="inline-flex items-center gap-2 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-lg shadow-blue-900/10 transition hover:bg-blue-800"><span class="text-lg leading-none">+</span> ثبت مورد جدید</a>@endif
            </section>

            @if($activeView === 'tasks')
                <div class="mb-5 flex gap-2 overflow-x-auto pb-1">
                    @foreach(['pending' => 'انجام‌نشده', 'done' => 'انجام‌شده', 'all' => 'همه'] as $statusKey => $statusLabel)
                        <a href="{{ url('/newui?view=tasks&task_status='.$statusKey.($search !== '' ? '&q='.urlencode($search) : '')) }}" class="shrink-0 rounded-full px-4 py-2 text-xs font-bold {{ $taskStatus === $statusKey ? 'bg-brand text-white shadow-sm' : 'border border-slate-200 bg-white text-slate-500' }}">{{ $statusLabel }}</a>
                    @endforeach
                </div>
            @endif

            @if($items->isEmpty())
                <div class="rounded-3xl border border-dashed border-slate-300 bg-white px-5 py-14 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-slate-50 text-slate-400">@include('newui.partials.icon', ['name' => 'search', 'class' => 'h-6 w-6'])</span><p class="mt-4 font-bold">موردی پیدا نشد</p><p class="mt-1 text-xs text-muted">عبارت جست‌وجو یا فیلتر خود را تغییر دهید.</p></div>
            @else
                <div class="space-y-3">
                    @foreach($items as $item)
                        @if($activeView === 'tasks')
                            <a href="{{ $showTask($item) }}" class="group block rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5">
                                <div class="flex items-start gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl {{ $item->completed ? 'bg-blue-50 text-blue-600' : 'bg-amber-50 text-amber-600' }}">@include('newui.partials.icon', ['name' => 'check', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block font-bold group-hover:text-brand">{{ $item->name }}</span><span class="mt-1 block line-clamp-2 text-xs leading-6 text-muted">{{ $item->description ?: 'توضیحی ثبت نشده است.' }}</span></span><span class="text-xs text-slate-300 group-hover:text-brand">←</span></div>
                                <div class="mt-4 flex flex-wrap items-center gap-2 border-t border-slate-100 pt-3 text-[11px] text-muted"><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->project->pluck('name')->filter()->take(2)->implode('، ') ?: 'بدون دستورکار' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">مهلت: {{ $dateText($item->ended_at) }}</span><span class="mr-auto rounded-full px-2.5 py-1 {{ $item->completed ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700' }}">{{ $item->completed ? 'انجام شده' : 'در انتظار انجام' }}</span></div>
                                @if($item->progress)<div class="mt-3 flex items-center gap-2"><span class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><span class="block h-full rounded-full bg-brand" style="width: {{ min(100, max(0, $item->progress)) }}%"></span></span><span class="text-[10px] text-muted">{{ $item->progress }}٪</span></div>@endif
                            </a>
                        @elseif($activeView === 'letters')
                            <a href="{{ $showLetter($item) }}" class="group flex items-start gap-3 rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'mail', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block font-bold group-hover:text-blue-700">{{ $item->subject }}</span><span class="mt-1 block line-clamp-2 text-xs leading-6 text-muted">{{ $item->summary ?: ($item->description ?: 'خلاصه‌ای ثبت نشده است.') }}</span><span class="mt-3 flex flex-wrap gap-2 text-[11px] text-muted"><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->kind ? 'صادره' : 'وارده' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->type?->name ?? 'بدون نوع' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $dateText($item->created_at) }}</span></span></span><span class="text-xs text-slate-300 group-hover:text-blue-600">←</span></a>
                        @else
                            <a href="{{ $showMinutes($item) }}" class="group flex items-start gap-3 rounded-3xl border border-slate-100 bg-white p-4 shadow-sm transition hover:border-blue-200 hover:shadow-card sm:p-5"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-2xl bg-blue-50 text-blue-600">@include('newui.partials.icon', ['name' => 'document', 'class' => 'h-5 w-5'])</span><span class="min-w-0 flex-1"><span class="block font-bold group-hover:text-blue-700">{{ $item->title }}</span><span class="mt-1 block line-clamp-2 text-xs leading-6 text-muted">{{ \Illuminate\Support\Str::limit(strip_tags($item->text ?? ''), 150) ?: 'متنی برای این صورت‌جلسه ثبت نشده است.' }}</span><span class="mt-3 flex flex-wrap gap-2 text-[11px] text-muted"><span class="rounded-full bg-slate-50 px-2.5 py-1">نویسنده: {{ $item->typer?->name ?? 'نامشخص' }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $dateText($item->date) }}</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->tasks_count }} فعالیت</span><span class="rounded-full bg-slate-50 px-2.5 py-1">{{ $item->approves_count }} مصوبه</span></span></span><span class="text-xs text-slate-300 group-hover:text-blue-600">←</span></a>
                        @endif
                    @endforeach
                </div>
                <div class="mt-6">{{ $items->links() }}</div>
            @endif
        @endif
    </main>
</div>

<nav class="safe-bottom fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/95 px-2 pt-2 shadow-[0_-8px_30px_rgba(23,36,58,.06)] backdrop-blur lg:hidden">
    <div class="mx-auto flex max-w-lg items-center justify-around">
        @foreach($nav as $item)
            @if($item['id'] === 'tasks' && !$canTasks) @continue @endif
            @if($item['id'] === 'letters' && !$canLetters) @continue @endif
            @if($item['id'] === 'minutes' && !$canMinutes) @continue @endif
            <a href="{{ url('/newui') }}{{ $item['id'] === 'home' ? '' : '?view='.$item['id'] }}" class="flex min-w-[64px] flex-col items-center gap-1 rounded-xl px-2 py-1.5 text-[10px] font-semibold {{ $activeView === $item['id'] ? 'text-brand' : 'text-slate-400' }}">
                @include('newui.partials.icon', ['name' => $item['icon'], 'class' => 'h-5 w-5'])<span>{{ $item['label'] }}</span>
            </a>
        @endforeach
    </div>
</nav>
</body>
</html>
