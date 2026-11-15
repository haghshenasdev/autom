<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f5f7fb">
    <title>{{ $title }} | کارنما</title>
    <link rel="icon" href="{{ asset('karnama2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'sans-serif'] }, colors: { brand: '#1d4ed8', 'brand-soft': '#eaf1ff', ink: '#17243a', muted: '#718096' }, boxShadow: { card: '0 12px 36px rgba(23, 36, 58, .06)' } } } };</script>
    <style>body{font-family:Vazirmatn,sans-serif;background:#f5f7fb}.safe-bottom{padding-bottom:max(1rem,env(safe-area-inset-bottom))}</style>
</head>
<body class="min-h-screen text-ink antialiased">
@php
    $dateText = function ($value) {
        if (!$value) return 'ثبت نشده';
        try { return \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d H:i'); }
        catch (\Throwable $exception) { return \Illuminate\Support\Carbon::parse($value)->format('Y/m/d H:i'); }
    };
    $pairs = match ($type) {
        'task' => [
            ['عنوان فعالیت', $record->name], ['وضعیت', $record->completed ? 'انجام شده' : 'انجام نشده'],
            ['درصد پیشرفت', is_null($record->progress) ? 'ثبت نشده' : $record->progress.'٪'],
            ['مسئول', $record->responsible?->name ?? 'تعیین نشده'],
            ['دستورکار', $record->project->pluck('name')->filter()->implode('، ') ?: 'بدون دستورکار'],
            ['دسته‌بندی', $record->task_group->pluck('name')->filter()->implode('، ') ?: 'بدون دسته‌بندی'],
            ['زمان شروع', $dateText($record->started_at)], ['مهلت انجام', $dateText($record->ended_at)],
            ['توضیحات', $record->description ?: 'توضیحی ثبت نشده است.'],
        ],
        'letter' => [
            ['موضوع', $record->subject], ['نوع نامه', $record->kind ? 'صادره' : 'وارده'],
            ['شماره مکاتبه', $record->mokatebe ?: 'ثبت نشده'], ['وضعیت', \App\Models\Letter::getStatusLabel($record->status)],
            ['دسته‌بندی', $record->type?->name ?? 'بدون نوع'], ['گیرنده', $record->organ?->name ?? 'ثبت نشده'],
            ['دفتر', $record->daftar?->name ?? 'ثبت نشده'], ['پیرو', $record->letter?->subject ?? 'ندارد'],
            ['دستورکار', $record->projects->pluck('name')->filter()->implode('، ') ?: 'ندارد'],
            ['خلاصه', $record->summary ?: 'خلاصه‌ای ثبت نشده است.'], ['توضیحات', $record->description ?: 'توضیحی ثبت نشده است.'],
            ['ثبت‌کننده', $record->user?->name ?? 'نامشخص'], ['تاریخ ثبت', $dateText($record->created_at)],
        ],
        default => [
            ['عنوان جلسه', $record->title], ['تاریخ جلسه', $dateText($record->date)],
            ['نویسنده', $record->typer?->name ?? 'نامشخص'], ['جلسه مرتبط با فعالیت', $record->task_creator?->name ?? 'ثبت نشده'],
            ['امضاکنندگان', $record->organ->pluck('name')->filter()->implode('، ') ?: 'ثبت نشده'],
            ['دستورکارها', $record->projects->pluck('name')->filter()->implode('، ') ?: 'ثبت نشده'],
            ['تعداد فعالیت‌ها', $record->tasks->count()], ['تعداد مصوبه‌ها', $record->approves->count()],
            ['متن صورت‌جلسه', $record->text ?: 'متنی ثبت نشده است.'],
        ],
    };
    $filePath = $type === 'letter' ? $record->getFilePath() : ($type === 'minute' ? $record->getFilePath() : null);
    $fileUrl = $filePath ? url(($type === 'letter' ? '/private-show/' : '/appendix-other-show/').str_replace(DIRECTORY_SEPARATOR, '/', $filePath)) : null;
    $fileExtension = strtolower(pathinfo((string) $record->file, PATHINFO_EXTENSION) ?: (string) $record->file);
@endphp

<main class="mx-auto min-h-screen px-4 pb-28 pt-5 sm:px-6 sm:pt-8 lg:pb-12">
    <header class="mb-6 flex items-center justify-between gap-3">
        <a href="{{ url('/newui') }}" class="flex items-center gap-3"><img src="{{ asset('karnama2.png') }}" alt="کارنما" class="h-11 w-11 rounded-2xl bg-white p-1 shadow-sm"><span><span class="block text-xs text-muted">کارنما</span><span class="block font-extrabold">{{ $title }}</span></span></a>
        <div class="flex items-center gap-2">
            <a href="{{ $backUrl }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-600 hover:border-blue-200 hover:text-brand">بازگشت</a>
            @if($canEdit)<a href="{{ $editUrl }}" class="rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-800">ویرایش</a>@endif
        </div>
    </header>

    @if(session('success'))<div class="mb-4 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm font-semibold text-blue-800">{{ session('success') }}</div>@endif

    <section class="mb-5 overflow-hidden rounded-[2rem] bg-gradient-to-l from-[#12316f] via-[#1d4ed8] to-[#3986f6] p-6 text-white shadow-card sm:p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="max-w-3xl"><span class="rounded-full bg-white/15 px-3 py-1 text-[11px]">{{ $title }} شماره {{ $record->id }}</span><h1 class="mt-4 text-2xl font-extrabold leading-relaxed sm:text-3xl">{{ $type === 'task' ? $record->name : ($type === 'letter' ? $record->subject : $record->title) }}</h1>
                <p class="mt-2 text-sm text-blue-50/90">{{ $type === 'task' ? 'جزئیات و وضعیت پیگیری فعالیت' : ($type === 'letter' ? 'جزئیات و اطلاعات دبیرخانه' : 'اطلاعات جلسه، امضاکنندگان و موارد پیگیری') }}</p>
            </div>
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white/15 text-white">@include('newui.partials.icon', ['name' => $type === 'task' ? 'check' : ($type === 'letter' ? 'mail' : 'document'), 'class' => 'h-7 w-7'])</span>
        </div>
    </section>

    <div class="grid items-start gap-5 {{ $fileUrl ? 'lg:grid-cols-2' : '' }}">
        @if($fileUrl)
            <section class="order-1 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7 lg:sticky lg:top-6">
            <div class="mb-4 flex items-center justify-between gap-3"><div><h2 class="font-extrabold">پیوست {{ $type === 'letter' ? 'نامه' : 'صورت‌جلسه' }}</h2><p class="mt-1 text-xs text-muted">پیش‌نمایش فایل ذخیره‌شده</p></div><a href="{{ $fileUrl }}" target="_blank" class="rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-800">باز کردن فایل</a></div>
            @if(in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
                <img src="{{ $fileUrl }}" alt="پیوست" class="mx-auto max-h-[70vh] w-full rounded-2xl bg-slate-50 object-contain">
            @elseif($fileExtension === 'pdf')
                <iframe src="{{ $fileUrl }}" title="پیش‌نمایش فایل" class="h-[65vh] w-full rounded-2xl border border-slate-100 bg-slate-50"></iframe>
            @else
                <div class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-muted">برای این نوع فایل، از دکمهٔ «باز کردن فایل» استفاده کنید.</div>
            @endif
            </section>
        @endif

        <section class="order-2 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7">
            <div class="mb-5 flex items-center justify-between"><div><h2 class="font-extrabold">اطلاعات ثبت‌شده</h2><p class="mt-1 text-xs text-muted">نمایش بر اساس دسترسی حساب کاربری شما</p></div><span class="rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-blue-700">{{ $dateText($record->updated_at) }}</span></div>
            <dl class="grid gap-3 sm:grid-cols-2">
                @foreach($pairs as [$label, $value])
                    <div class="rounded-2xl bg-slate-50/80 p-4 {{ in_array($label, ['توضیحات', 'خلاصه', 'متن صورت‌جلسه'], true) ? 'sm:col-span-2' : '' }}">
                        <dt class="text-[11px] font-semibold text-muted">{{ $label }}</dt>
                        <dd class="mt-2 whitespace-pre-line text-sm font-semibold leading-7 text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    @if($type === 'letter' && $record->Appendix->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">ضمیمه‌ها</h2><div class="space-y-2">
            @foreach($record->Appendix as $appendix)
                <a href="{{ url('/private-show/'.str_replace(DIRECTORY_SEPARATOR, '/', $appendix->getFilePath($record->id))) }}" target="_blank" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span>{{ $appendix->title ?: 'فایل ضمیمه' }}</span><span class="text-xs text-brand">پیش‌نمایش / دریافت ←</span></a>
            @endforeach
        </div></section>
    @endif

    @if(in_array($type, ['task', 'minute'], true) && $record->appendix_others->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">فایل‌های پیوست</h2><div class="space-y-2">
            @foreach($record->appendix_others as $appendix)
                @if($appendix->file)
                    <a href="{{ url('/appendix-other-show/'.str_replace(DIRECTORY_SEPARATOR, '/', $appendix->getFilePath())) }}" target="_blank" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span>{{ $appendix->title ?: 'فایل پیوست' }}</span><span class="text-xs text-brand">پیش‌نمایش / دریافت ←</span></a>
                @endif
            @endforeach
        </div></section>
    @endif

    @if($type === 'minute' && $record->tasks->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">فعالیت‌های مرتبط</h2><div class="space-y-2">
            @foreach($record->tasks as $task)<a href="{{ url('/newui/task/'.$task->id) }}" class="flex items-center justify-between rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span>{{ $task->name }}</span><span class="text-xs text-muted">{{ $task->completed ? 'انجام شده' : 'در انتظار' }} ←</span></a>@endforeach
        </div></section>
    @endif
</main>
<nav class="safe-bottom fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/95 px-4 pt-2 shadow-[0_-8px_30px_rgba(23,36,58,.06)] backdrop-blur sm:hidden"><a href="{{ $backUrl }}" class="mx-auto flex max-w-sm items-center justify-center gap-2 rounded-xl py-2 text-xs font-bold text-brand">← بازگشت به فهرست</a></nav>
</body>
</html>
