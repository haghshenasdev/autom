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
    @include('newui.partials.spa-head')
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { width: 100%; max-width: 100%; overflow-x: clip; }
        body { font-family: Vazirmatn, sans-serif; background: #f5f7fb; }
        .safe-bottom { padding-bottom: max(1rem, env(safe-area-inset-bottom)); }
        .record-value { overflow-wrap: anywhere; word-break: break-word; }
        .record-layout, .record-layout > *, .record-header, .record-header > * { min-width: 0; max-width: 100%; }
    </style>
</head>
<body class="min-h-screen text-ink antialiased">
<div id="newui-spa"><div id="newui-page">
@php
    $dateText = function ($value) {
        if (!$value) return 'ثبت نشده';
        try { return \Morilog\Jalali\Jalalian::fromDateTime($value)->format('Y/m/d H:i'); }
        catch (\Throwable $exception) { return \Illuminate\Support\Carbon::parse($value)->format('Y/m/d H:i'); }
    };
    $pairs = match ($type) {
        'task' => [
            ['عنوان فعالیت', $record->name], ['وضعیت', $record->completed ? 'انجام شده' : 'انجام نشده'],
            ['نامه مرتبط', $record->letter?->subject ?? 'ندارد'], ['صورت‌جلسه مرتبط', $record->minutes?->title ?? 'ندارد'],
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

<main class="mx-auto min-h-screen w-full max-w-5xl min-w-0 overflow-x-clip px-4 pb-28 pt-5 sm:px-6 sm:pt-8 lg:pb-12">
    <header class="record-header mb-6 flex flex-wrap items-center justify-between gap-3">
        <a href="{{ url('/newui') }}" class="flex min-w-0 items-center gap-3"><img src="{{ asset('karnama2.png') }}" alt="کارنما" class="h-11 w-11 shrink-0 rounded-2xl bg-white p-1 shadow-sm"><span class="min-w-0"><span class="block text-xs text-muted">کارنما</span><span class="block break-words font-extrabold">{{ $title }}</span></span></a>
        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ $backUrl }}" class="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-xs font-bold text-slate-600 hover:border-blue-200 hover:text-brand">بازگشت</a>
            @if($type === 'letter')<button type="button" data-open-letter-timeline data-url="{{ route('newui.letters.timeline', $record->id) }}" class="rounded-xl border border-blue-100 bg-blue-50 px-3 py-2.5 text-xs font-bold text-brand hover:bg-blue-100">نمایش تایم‌لاین</button>@endif
            @if($canEdit)<a href="{{ route('newui.record', ['type' => $type, 'recordId' => $record->id]) }}?edit=1" class="rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-blue-800">ویرایش</a>@endif
        </div>
    </header>

    @if($type === 'letter')
        <dialog data-letter-timeline-dialog class="w-[min(92vw,42rem)] max-h-[85vh] overflow-hidden rounded-3xl p-0 shadow-2xl backdrop:bg-slate-950/40">
            <div class="flex items-start justify-between gap-4 border-b border-slate-100 p-5"><div><p class="text-xs text-muted">روند نامه</p><h2 class="mt-1 font-extrabold">تایم‌لاین تغییرات نامه</h2></div><button type="button" data-close-letter-timeline class="grid h-9 w-9 place-items-center rounded-xl bg-slate-100 text-slate-600" aria-label="بستن">×</button></div>
            <div data-letter-timeline-content class="max-h-[65vh] space-y-4 overflow-y-auto p-5"><p class="text-sm text-muted">برای نمایش رویدادها دکمه تایم‌لاین را بزنید.</p></div>
        </dialog>
    @endif

    @if(session('success'))<div class="mb-4 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm font-semibold text-blue-800">{{ session('success') }}</div>@endif

    @if($editing)
        @php($fieldClass = 'mt-1.5 w-full rounded-xl border-slate-200 bg-white text-sm focus:border-brand focus:ring-brand')
        <form method="post" action="{{ route('newui.record.update', ['type' => $type, 'recordId' => $record->id]) }}" class="mb-6 space-y-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-8">
            @csrf
            <div><h1 class="text-xl font-extrabold">ویرایش {{ $title }} شماره {{ $record->id }}</h1><p class="mt-1 text-sm text-muted">فیلدها را تغییر دهید و ذخیره کنید.</p></div>
            @if($errors->any())<div class="rounded-xl bg-rose-50 p-3 text-sm text-rose-700">لطفاً خطاهای فرم را اصلاح کنید.</div>@endif
            @if($type === 'task')
                <label class="block text-sm font-semibold">عنوان فعالیت<textarea name="name" required maxlength="255" class="{{ $fieldClass }}" rows="2">{{ old('name', $record->name) }}</textarea></label>
                <label class="block text-sm font-semibold">توضیحات<textarea name="description" class="{{ $fieldClass }}" rows="4">{{ old('description', $record->description) }}</textarea></label>
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block text-sm font-semibold">زمان شروع<input type="datetime-local" name="started_at" value="{{ old('started_at', $record->started_at ? \Illuminate\Support\Carbon::parse($record->started_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">مهلت انجام<input type="datetime-local" name="ended_at" value="{{ old('ended_at', $record->ended_at ? \Illuminate\Support\Carbon::parse($record->ended_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">تاریخ تکمیل<input type="datetime-local" name="completed_at" value="{{ old('completed_at', $record->completed_at ? \Illuminate\Support\Carbon::parse($record->completed_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">درصد پیشرفت<input type="number" min="0" max="100" name="progress" value="{{ old('progress', $record->progress) }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">وضعیت<select name="status" class="{{ $fieldClass }}"><option value="">بدون وضعیت</option>@foreach(\App\Models\Task::getStatusListDefine() as $id => $name)<option value="{{ $id }}" @selected(old('status', $record->status) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">اعتبار (ریال)<input type="number" min="0" name="amount" value="{{ old('amount', $record->amount) }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">شهر<select name="city_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['cities'] as $id => $name)<option value="{{ $id }}" @selected(old('city_id', $record->city_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">دستگاه مرتبط<select name="organ_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['organs'] as $id => $name)<option value="{{ $id }}" @selected(old('organ_id', $record->organ_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    @if(auth()->user()->can('restore_any_task'))<label class="block text-sm font-semibold">مسئول<select name="Responsible_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['users'] as $id => $name)<option value="{{ $id }}" @selected(old('Responsible_id', $record->Responsible_id) == $id)>{{ $name }}</option>@endforeach</select></label>@endif
                    <label class="block text-sm font-semibold">دسته‌بندی فعالیت<select multiple name="task_group_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['taskGroups'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('task_group_ids', $record->group->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">دستورکارها<select multiple name="project_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['projects'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('project_ids', $record->project->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">صورت‌جلسه مرتبط<select name="minutes_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['minutes'] as $id => $name)<option value="{{ $id }}" @selected(old('minutes_id', $record->minutes_id) == $id)>{{ $id }} - {{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">نامه مرتبط<select name="letter_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['letters'] as $id => $name)<option value="{{ $id }}" @selected(old('letter_id', $record->letter_id) == $id)>{{ $id }} - {{ $name }}</option>@endforeach</select></label>
                </div>
                <div class="flex flex-wrap gap-5"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="completed" value="1" @checked(old('completed', $record->completed))> انجام شده</label><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="repeat" value="1" @checked(old('repeat', $record->repeat))> تکرارشونده</label></div>
            @elseif($type === 'letter')
                <label class="block text-sm font-semibold">موضوع<input name="subject" required value="{{ old('subject', $record->subject) }}" class="{{ $fieldClass }}"></label>
                <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-semibold">شماره مکاتبه<input name="mokatebe" value="{{ old('mokatebe', $record->mokatebe) }}" class="{{ $fieldClass }}"></label><label class="block text-sm font-semibold">تاریخ<input type="datetime-local" name="created_at" value="{{ old('created_at', $record->created_at ? \Illuminate\Support\Carbon::parse($record->created_at)->format('Y-m-d\TH:i') : '') }}" class="{{ $fieldClass }}"></label>
                    <label class="block text-sm font-semibold">نوع نامه<select name="kind" class="{{ $fieldClass }}">@foreach(\App\Models\Letter::getKindListDefine() as $id => $name)<option value="{{ $id }}" @selected(old('kind', $record->kind) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">نوع/دسته‌بندی<select name="type_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['types'] as $id => $name)<option value="{{ $id }}" @selected(old('type_id', $record->type_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">گیرنده<select name="organ_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['organs'] as $id => $name)<option value="{{ $id }}" @selected(old('organ_id', $record->organ_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">دفتر<select name="daftar_id" class="{{ $fieldClass }}"><option value="">انتخاب نشده</option>@foreach($editOptions['organs'] as $id => $name)<option value="{{ $id }}" @selected(old('daftar_id', $record->daftar_id) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">نامه پیرو<select name="peiroow_letter_id" class="{{ $fieldClass }}"><option value="">ندارد</option>@foreach($editOptions['letters'] as $id => $name)<option value="{{ $id }}" @selected(old('peiroow_letter_id', $record->peiroow_letter_id) == $id)>{{ $id }} - {{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">وضعیت<select name="status" class="{{ $fieldClass }}"><option value="">بدون وضعیت</option>@foreach(\App\Models\Letter::getStatusListDefine() as $id => $name)<option value="{{ $id }}" @selected(old('status', $record->status) == $id)>{{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">دستورکارها<select multiple name="project_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['projects'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('project_ids', $record->projects->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label></div>
                <label class="block text-sm font-semibold">خلاصه<textarea name="summary" class="{{ $fieldClass }}" rows="3">{{ old('summary', $record->summary) }}</textarea></label><label class="block text-sm font-semibold">توضیحات<textarea name="description" class="{{ $fieldClass }}" rows="4">{{ old('description', $record->description) }}</textarea></label>
            @else
                <label class="block text-sm font-semibold">عنوان جلسه<input name="title" required value="{{ old('title', $record->title) }}" class="{{ $fieldClass }}"></label><label class="block text-sm font-semibold">متن صورت‌جلسه<textarea name="text" class="{{ $fieldClass }}" rows="8">{{ old('text', $record->text) }}</textarea></label>
                <div class="grid gap-4 sm:grid-cols-2"><label class="block text-sm font-semibold">تاریخ جلسه<input type="date" name="date" required value="{{ old('date', $record->date ? \Illuminate\Support\Carbon::parse($record->date)->format('Y-m-d') : '') }}" class="{{ $fieldClass }}"></label><label class="block text-sm font-semibold">فعالیت جلسه<select name="task_id" class="{{ $fieldClass }}">@foreach($editOptions['tasks'] as $id => $name)<option value="{{ $id }}" @selected(old('task_id', $record->task_id) == $id)>{{ $id }} - {{ $name }}</option>@endforeach</select></label>
                    <label class="block text-sm font-semibold">امضاکنندگان<select multiple name="organ_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['organs'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('organ_ids', $record->organ->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">دستورکارها<select multiple name="project_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['projects'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('project_ids', $record->projects->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label><label class="block text-sm font-semibold">دسته‌بندی صورت‌جلسه<select multiple name="group_ids[]" class="{{ $fieldClass }} min-h-28">@foreach($editOptions['minuteGroups'] as $id => $name)<option value="{{ $id }}" @selected(collect(old('group_ids', $record->group->pluck('id')->all()))->contains($id))>{{ $name }}</option>@endforeach</select></label></div>
            @endif
            <div class="flex flex-wrap gap-3 border-t border-slate-100 pt-4"><button class="rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white">ذخیره تغییرات</button><a href="{{ route('newui.record', ['type' => $type, 'recordId' => $record->id]) }}" class="rounded-xl border border-slate-200 px-5 py-3 text-sm font-bold text-slate-600">انصراف</a></div>
        </form>
    @else
    <section class="mb-5 overflow-hidden rounded-[2rem] bg-gradient-to-l from-[#12316f] via-[#1d4ed8] to-[#3986f6] p-6 text-white shadow-card sm:p-8">
        <div class="record-header flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0 max-w-3xl"><span class="inline-block max-w-full break-words rounded-full bg-white/15 px-3 py-1 text-[11px]">{{ $title }} شماره {{ $record->id }}</span><h1 class="mt-4 break-words text-2xl font-extrabold leading-relaxed sm:text-3xl">{{ $type === 'task' ? $record->name : ($type === 'letter' ? $record->subject : $record->title) }}</h1>
                <p class="mt-2 text-sm text-blue-50/90">{{ $type === 'task' ? 'جزئیات و وضعیت پیگیری فعالیت' : ($type === 'letter' ? 'جزئیات و اطلاعات دبیرخانه' : 'اطلاعات جلسه، امضاکنندگان و موارد پیگیری') }}</p>
            </div>
            <span class="grid h-14 w-14 place-items-center rounded-2xl bg-white/15 text-white">@include('newui.partials.icon', ['name' => $type === 'task' ? 'check' : ($type === 'letter' ? 'mail' : 'document'), 'class' => 'h-7 w-7'])</span>
        </div>
    </section>

    <div class="record-layout grid min-w-0 w-full items-start gap-5 {{ $fileUrl ? 'lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]' : '' }}">
        @if($fileUrl)
            <section class="order-1 min-w-0 w-full max-w-full overflow-hidden rounded-[2rem] border border-slate-100 bg-white p-4 shadow-card sm:p-7 lg:order-2 lg:sticky lg:top-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-3"><div class="min-w-0"><h2 class="font-extrabold">پیوست {{ $type === 'letter' ? 'نامه' : 'صورت‌جلسه' }}</h2><p class="mt-1 text-xs text-muted">پیش‌نمایش فایل ذخیره‌شده</p></div><a href="{{ $fileUrl }}" target="_blank" class="shrink-0 rounded-xl bg-brand px-4 py-2.5 text-xs font-bold text-white hover:bg-blue-800">باز کردن فایل</a></div>
            @if(in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true))
                <div data-preview-shell class="relative min-h-48 overflow-hidden rounded-2xl bg-slate-50">
                    <div data-preview-loading class="absolute inset-0 z-10 grid place-items-center bg-slate-50/95 text-sm font-semibold text-slate-600"><span class="flex items-center gap-3"><span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>&#1583;&#1585; &#1581;&#1575;&#1604; &#1576;&#1575;&#1585;&#1711;&#1584;&#1575;&#1585;&#1740; &#1662;&#1740;&#1588;&#8204;&#1606;&#1605;&#1575;&#1740;&#1588;&#8230;</span></div>
                    <img data-preview-content src="{{ $fileUrl }}" alt="File preview" class="mx-auto block max-h-[70vh] w-full max-w-full rounded-2xl object-contain">
                </div>
            @elseif($fileExtension === 'pdf')
                <div data-preview-shell class="relative min-h-[65vh] overflow-hidden rounded-2xl bg-slate-50">
                    <div data-preview-loading class="absolute inset-0 z-10 grid place-items-center bg-slate-50/95 text-sm font-semibold text-slate-600"><span class="flex items-center gap-3"><span class="h-5 w-5 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700"></span>&#1583;&#1585; &#1581;&#1575;&#1604; &#1576;&#1575;&#1585;&#1711;&#1584;&#1575;&#1585;&#1740; &#1662;&#1740;&#1588;&#8204;&#1606;&#1605;&#1575;&#1740;&#1588;&#8230;</span></div>
                    <iframe data-preview-content src="{{ $fileUrl }}" title="File preview" class="relative z-0 block h-[65vh] w-full min-w-0 max-w-full border border-slate-100 bg-slate-50"></iframe>
                </div>
            @else
                <div class="rounded-2xl bg-slate-50 p-8 text-center text-sm text-muted">برای این نوع فایل، از دکمهٔ «باز کردن فایل» استفاده کنید.</div>
            @endif
            </section>
        @endif

        <section class="order-2 min-w-0 w-full max-w-full overflow-hidden rounded-[2rem] border border-slate-100 bg-white p-4 shadow-card sm:p-7 lg:order-1">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3"><div class="min-w-0"><h2 class="font-extrabold">اطلاعات ثبت‌شده</h2><p class="mt-1 text-xs text-muted">نمایش بر اساس دسترسی حساب کاربری شما</p></div><span class="max-w-full break-words rounded-full bg-blue-50 px-3 py-1.5 text-[11px] font-bold text-blue-700">{{ $dateText($record->updated_at) }}</span></div>
            <dl class="grid min-w-0 grid-cols-1 gap-3 xl:grid-cols-2">
                @foreach($pairs as [$label, $value])
                    <div class="min-w-0 overflow-hidden rounded-2xl bg-slate-50/80 p-4 {{ in_array($label, ['توضیحات', 'خلاصه', 'متن صورت‌جلسه'], true) ? 'xl:col-span-2' : '' }}">
                        <dt class="text-[11px] font-semibold text-muted">{{ $label }}</dt>
                        <dd class="record-value mt-2 whitespace-pre-line text-sm font-semibold leading-7 text-slate-800">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    @if($type === 'task' && ($record->letter || $record->minutes))
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7">
            <h2 class="mb-4 font-extrabold">نامه و صورت‌جلسه مرتبط</h2>
            <div class="space-y-2">
                @if($record->letter)
                    <a href="{{ url('/newui/letter/'.$record->letter->id) }}" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span>نامه: {{ $record->letter->subject }}</span><span class="shrink-0 text-xs text-brand">باز کردن ←</span></a>
                @endif
                @if($record->minutes)
                    <a href="{{ url('/newui/minute/'.$record->minutes->id) }}" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span>صورت‌جلسه: {{ $record->minutes->title }}</span><span class="shrink-0 text-xs text-brand">باز کردن ←</span></a>
                @endif
            </div>
        </section>
    @endif

    @if($type === 'letter' && $record->Appendix->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">ضمیمه‌ها</h2><div class="space-y-2">
            @foreach($record->Appendix as $appendix)
                <a href="{{ url('/private-show/'.str_replace(DIRECTORY_SEPARATOR, '/', $appendix->getFilePath($record->id))) }}" target="_blank" class="flex min-w-0 items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span class="min-w-0 break-words">{{ $appendix->title ?: 'فایل ضمیمه' }}</span><span class="shrink-0 text-xs text-brand">پیش‌نمایش / دریافت ←</span></a>
            @endforeach
        </div></section>
    @endif

    @if(in_array($type, ['task', 'minute'], true) && $record->appendix_others->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">فایل‌های پیوست</h2><div class="space-y-2">
            @foreach($record->appendix_others as $appendix)
                @if($appendix->file)
                    <a href="{{ url('/appendix-other-show/'.str_replace(DIRECTORY_SEPARATOR, '/', $appendix->getFilePath())) }}" target="_blank" class="flex min-w-0 items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span class="min-w-0 break-words">{{ $appendix->title ?: 'فایل پیوست' }}</span><span class="shrink-0 text-xs text-brand">پیش‌نمایش / دریافت ←</span></a>
                @endif
            @endforeach
        </div></section>
    @endif

    @if(in_array($type, ['letter', 'minute'], true) && $record->tasks->isNotEmpty())
        <section class="mt-5 rounded-[2rem] border border-slate-100 bg-white p-5 shadow-card sm:p-7"><h2 class="mb-4 font-extrabold">فعالیت‌های مرتبط</h2><div class="space-y-2">
            @foreach($record->tasks as $task)<a href="{{ url('/newui/task/'.$task->id) }}" class="flex min-w-0 items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4 text-sm font-semibold hover:bg-blue-50"><span class="min-w-0 break-words"><span class="block">{{ $task->name }}</span><span class="mt-1 block text-xs font-normal text-muted">مسئول: {{ $task->responsible?->name ?? 'تعیین نشده' }}</span></span><span class="shrink-0 text-xs text-muted">{{ $task->completed ? 'انجام شده' : 'در انتظار' }} ←</span></a>@endforeach
        </div></section>
    @endif
    @endif
</main>
<nav class="safe-bottom fixed inset-x-0 bottom-0 z-20 border-t border-slate-200/80 bg-white/95 px-4 pt-2 shadow-[0_-8px_30px_rgba(23,36,58,.06)] backdrop-blur sm:hidden"><a href="{{ $backUrl }}" class="mx-auto flex max-w-sm items-center justify-center gap-2 rounded-xl py-2 text-xs font-bold text-brand">← بازگشت به فهرست</a></nav>
<script>
(() => {
    const dialog = document.querySelector('[data-letter-timeline-dialog]');
    const timelineButton = document.querySelector('[data-open-letter-timeline]');
    const timelineContent = dialog?.querySelector('[data-letter-timeline-content]');
    if (dialog && timelineButton && timelineContent && !timelineButton.dataset.bound) {
        timelineButton.dataset.bound = '1';
        timelineButton.addEventListener('click', async () => {
            timelineContent.replaceChildren();
            const loading = document.createElement('p');
            loading.className = 'text-sm text-muted';
            loading.textContent = 'در حال دریافت رویدادها…';
            timelineContent.appendChild(loading);
            dialog.showModal();
            try {
                const response = await fetch(timelineButton.dataset.url, { headers: { Accept: 'application/json' } });
                const payload = await response.json();
                if (!response.ok) throw new Error(payload.message || 'دریافت تایم‌لاین ممکن نشد.');
                timelineContent.replaceChildren();
                (payload.data || []).forEach((event) => {
                    const item = document.createElement('article');
                    item.className = 'relative border-r-2 border-blue-200 pr-4';
                    const heading = document.createElement('h3');
                    heading.className = 'font-bold text-slate-800';
                    heading.textContent = event.title || 'رویداد نامه';
                    const date = document.createElement('p');
                    date.className = 'mt-1 text-[11px] text-muted';
                    date.textContent = event.created_at ? new Intl.DateTimeFormat('fa-IR', { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(event.created_at)) : '';
                    const description = document.createElement('p');
                    description.className = 'mt-2 whitespace-pre-line break-words text-sm leading-7 text-slate-600';
                    description.textContent = event.description || '';
                    item.append(heading, date, description);
                    if (event.user) {
                        const actor = document.createElement('p');
                        actor.className = 'mt-2 text-xs text-slate-500';
                        actor.textContent = 'ثبت‌کننده: ' + event.user;
                        item.appendChild(actor);
                    }
                    if (event.changes && Object.keys(event.changes).length) Object.values(event.changes).forEach((change) => {
                        const detail = document.createElement('p');
                        detail.className = 'mt-1 text-xs text-slate-500';
                        detail.textContent = `${change.label || 'تغییر'}: ${change.old ?? '—'} ← ${change.new ?? '—'}`;
                        item.appendChild(detail);
                    });
                    timelineContent.appendChild(item);
                });
                if (!timelineContent.childElementCount) {
                    const empty = document.createElement('p');
                    empty.className = 'text-sm text-muted';
                    empty.textContent = 'رویدادی برای این نامه ثبت نشده است.';
                    timelineContent.appendChild(empty);
                }
            } catch (error) {
                timelineContent.replaceChildren();
                const message = document.createElement('p');
                message.className = 'text-sm text-rose-600';
                message.textContent = error.message || 'خطا در دریافت تایم‌لاین.';
                timelineContent.appendChild(message);
            }
        });
        dialog.querySelector('[data-close-letter-timeline]')?.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => { if (event.target === dialog) dialog.close(); });
    }
    document.querySelectorAll('[data-preview-content]').forEach((preview) => {
        const loader = preview.closest('[data-preview-shell]')?.querySelector('[data-preview-loading]');
        const finish = () => loader?.classList.add('hidden');
        preview.addEventListener('load', finish, { once: true });
        preview.addEventListener('error', finish, { once: true });
        if (preview instanceof HTMLImageElement && preview.complete) finish();
    });
})();
</script>
<div id="newui-vue-root"></div>
</div>
@include('newui.partials.spa-router')
</body>
</html>
