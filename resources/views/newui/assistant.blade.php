<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#f5f7fb">
    <title>دستیار هوشمند | کارنما</title>
    <link rel="icon" href="{{ asset('karnama2.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Vazirmatn', 'sans-serif'] }, colors: { brand: '#1d4ed8', 'brand-soft': '#eaf1ff', ink: '#17243a', muted: '#718096' }, boxShadow: { card: '0 12px 36px rgba(23, 36, 58, .06)' } } } };</script>
    @include('newui.partials.spa-head')
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { width: 100%; max-width: 100%; overflow: hidden; }
        body { font-family: Vazirmatn, sans-serif; }
        .safe-bottom { padding-bottom: max(.75rem, env(safe-area-inset-bottom)); }
        .chat-scroll { scrollbar-width: thin; }
    </style>
</head>
<body class="min-h-screen bg-[#f5f7fb] text-ink antialiased">
<div id="newui-spa"><div id="newui-page">
<main class="mx-auto flex h-[100dvh] min-h-0 w-full max-w-5xl flex-col bg-white shadow-card sm:my-5 sm:h-[calc(100dvh-2.5rem)] sm:rounded-[2rem] sm:border sm:border-slate-100">
    <header class="flex shrink-0 items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-6 sm:py-4">
        <div class="flex min-w-0 items-center gap-3">
            <img src="{{ asset('karnama2.png') }}" alt="کارنما" class="h-11 w-11 shrink-0 rounded-2xl bg-slate-50 p-1">
            <div class="min-w-0"><p class="text-xs text-muted">کارنما</p><h1 class="truncate font-extrabold">دستیار هوشمند</h1></div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
            <button id="clear-chat" type="button" class="rounded-xl border border-slate-200 px-3 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50">گفت‌وگوی جدید</button>
            <a href="{{ url('/newui') }}" class="rounded-xl bg-brand px-3 py-2 text-xs font-bold text-white hover:bg-blue-800">بازگشت</a>
        </div>
    </header>

    <section id="messages" class="chat-scroll min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-5 sm:px-6" role="log" aria-live="polite">
        <div class="flex justify-start">
            <div class="max-w-[92%] rounded-2xl rounded-tr-sm bg-blue-50 px-4 py-3 text-sm leading-7 text-slate-800 sm:max-w-[80%]">
                سلام {{ auth()->user()->name }}! دربارهٔ نامه‌ها، فعالیت‌ها و صورت‌جلسه‌هایی که اجازهٔ دیدنشان را دارید سؤال کنید. مثلاً: «برای روستای موته در سال ۱۴۰۵ چه نامه‌هایی ثبت شده؟»
            </div>
        </div>
    </section>

    <div id="chat-status" class="hidden shrink-0 px-4 pb-2 text-xs text-rose-700 sm:px-6" role="status"></div>
    <form id="chat-form" class="safe-bottom shrink-0 border-t border-slate-100 bg-white px-3 py-3 sm:px-6 sm:py-4">
        <div class="flex items-end gap-2 rounded-2xl border border-slate-200 bg-slate-50 p-2 focus-within:border-blue-300 focus-within:ring-2 focus-within:ring-blue-100">
            <input id="assistant-file" type="file" accept=".pdf,.jpg,.jpeg,.png,.tif,.tiff,application/pdf,image/*" class="hidden">
            <button id="choose-file" type="button" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl border border-slate-200 bg-white text-slate-600" aria-label="انتخاب فایل برای ثبت" title="PDF یا تصویر"><span aria-hidden="true">📎</span></button>
            <textarea id="chat-input" rows="1" maxlength="1200" placeholder="سؤالت را دربارهٔ اطلاعات کارنما بنویس…" class="max-h-36 min-h-11 min-w-0 flex-1 resize-y border-0 bg-transparent px-2 py-2 text-sm leading-6 outline-none focus:ring-0" aria-label="متن پرسش"></textarea>
            <button id="send-button" type="submit" class="grid h-11 w-11 shrink-0 place-items-center rounded-xl bg-brand text-white transition hover:bg-blue-800 disabled:cursor-wait disabled:opacity-50" aria-label="ارسال پرسش">
                <svg viewBox="0 0 24 24" class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 14-7-4 14-3-6-7-1Z"/><path d="m12 13 4-8"/></svg>
            </button>
        </div>
        <p id="file-name" class="hidden mt-2 text-center text-[10px] text-slate-500"></p>
        <p class="mt-2 text-center text-[10px] leading-5 text-slate-400">فایل بدون نیاز به کپشن OCR می‌شود؛ متن استخراج‌شده برای پیشنهاد نوع و عنوان ثبت به GapGPT فرستاده می‌شود.</p>
    </form>
</main>

<script>
(() => {
    const messages = document.getElementById('messages');
    const form = document.getElementById('chat-form');
    const input = document.getElementById('chat-input');
    const button = document.getElementById('send-button');
    const status = document.getElementById('chat-status');
    const fileInput = document.getElementById('assistant-file');
    const fileName = document.getElementById('file-name');

    document.getElementById('choose-file').addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', () => {
        fileName.textContent = fileInput.files[0]?.name || '';
        fileName.classList.toggle('hidden', !fileInput.files.length);
    });

    async function analyzeUploadedFile(file) {
        const row = document.createElement('div');
        row.className = 'flex justify-start';
        const bubble = document.createElement('div');
        bubble.className = 'rounded-2xl bg-brand px-4 py-3 text-sm text-white';
        bubble.textContent = 'فایل: ' + file.name;
        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        button.disabled = true;
        status.classList.add('hidden');
        const payload = new FormData();
        payload.append('file', file);
        try {
            const response = await fetch('{{ route('newui.assistant.file') }}', {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                body: payload,
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'پردازش فایل ناموفق بود.');
            addDocumentDraft(data);
        } catch (error) {
            status.textContent = error.message || 'پردازش فایل ناموفق بود.';
            status.classList.remove('hidden');
        } finally {
            button.disabled = false;
            fileInput.value = '';
            fileName.textContent = '';
            fileName.classList.add('hidden');
        }
    }

    function addDocumentDraft(draft) {
        const row = document.createElement('div');
        row.className = 'flex justify-end';
        const card = document.createElement('div');
        card.className = 'w-full max-w-[96%] rounded-2xl bg-slate-50 p-4 text-sm text-slate-800 sm:max-w-[88%]';
        const heading = document.createElement('p');
        heading.className = 'font-bold';
        heading.textContent = 'متن فایل OCR شد؛ نوع و عنوان پیشنهادی را بررسی و در صورت نیاز ویرایش کنید.';
        const summary = document.createElement('p');
        summary.className = 'mt-2 whitespace-pre-wrap break-words text-xs leading-6 text-slate-700';
        summary.textContent = draft.summary || draft.reason || '';
        const preview = document.createElement('p');
        preview.className = 'mt-2 max-h-28 overflow-auto whitespace-pre-wrap break-words rounded-xl bg-white p-3 text-xs leading-6 text-slate-600';
        preview.textContent = draft.preview || '';
        const title = document.createElement('input');
        title.type = 'text'; title.maxLength = 255; title.value = (draft.title || '').slice(0, 255);
        title.className = 'mt-3 w-full rounded-xl border-slate-200 text-sm';
        title.setAttribute('aria-label', 'عنوان پیشنهادی');
        const type = document.createElement('select');
        type.className = 'mt-2 w-full rounded-xl border-slate-200 text-sm';
        [['letter', 'نامه'], ['minute', 'صورت‌جلسه']].forEach(([value, label]) => {
            const option = document.createElement('option'); option.value = value; option.textContent = label;
            option.selected = value === draft.type; type.appendChild(option);
        });
        const submit = document.createElement('button');
        submit.type = 'button'; submit.className = 'mt-3 rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white';
        submit.textContent = 'ثبت رکورد';
        submit.addEventListener('click', async () => {
            submit.disabled = true;
            try {
                const response = await fetch('{{ route('newui.assistant.file.register') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ token: draft.token, type: type.value, title: title.value.trim() }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'ثبت رکورد ناموفق بود.');
                const done = document.createElement('a'); done.href = result.url;
                done.className = 'mt-3 block rounded-xl bg-emerald-50 px-4 py-3 font-bold text-emerald-700';
                done.textContent = '✅ ' + result.message + ' مشاهده رکورد ←';
                submit.replaceWith(done);
            } catch (error) {
                status.textContent = error.message || 'ثبت رکورد ناموفق بود.';
                status.classList.remove('hidden'); submit.disabled = false;
            }
        });
        card.append(heading, summary, preview, title, type, submit);
        row.appendChild(card); messages.appendChild(row); messages.scrollTop = messages.scrollHeight;
    }

    function addMessage(role, text, results = [], pagination = null) {
        const row = document.createElement('div');
        row.className = role === 'user' ? 'flex justify-start' : 'flex justify-end';
        const bubble = document.createElement('div');
        bubble.className = role === 'user'
            ? 'max-w-[92%] whitespace-pre-wrap break-words rounded-2xl rounded-tr-sm bg-brand px-4 py-3 text-sm leading-7 text-white sm:max-w-[80%]'
            : 'max-w-[96%] min-w-0 rounded-2xl rounded-tl-sm bg-slate-50 px-4 py-3 text-sm leading-7 text-slate-800 sm:max-w-[88%]';
        const content = document.createElement('p');
        content.className = 'whitespace-pre-wrap break-words';
        content.textContent = text;
        bubble.appendChild(content);

        if (role === 'assistant' && results.length) {
            const list = document.createElement('div');
            list.className = 'mt-3 grid min-w-0 gap-2';
            results.forEach((result) => {
                const card = document.createElement('a');
                card.href = result.url;
                card.className = 'block min-w-0 rounded-xl border border-slate-200 bg-white p-3 text-right transition hover:border-blue-300 hover:bg-blue-50';
                const title = document.createElement('span');
                title.className = 'block break-words text-sm font-bold text-slate-800';
                title.textContent = result.title || 'مشاهده مورد';
                card.appendChild(title);
                if (result.meta) {
                    const meta = document.createElement('span');
                    meta.className = 'mt-1 block break-words text-[11px] text-muted';
                    meta.textContent = result.meta;
                    card.appendChild(meta);
                }
                if (result.summary) {
                    const summary = document.createElement('span');
                    summary.className = 'mt-2 block break-words text-xs leading-6 text-slate-600';
                    summary.textContent = result.summary;
                    card.appendChild(summary);
                }
                list.appendChild(card);
            });
            if (pagination && pagination.last_page > 1 && pagination.search_token) {
                const controls = document.createElement('div');
                controls.className = 'mt-2 flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2';
                const pageLabel = document.createElement('span');
                pageLabel.className = 'text-[11px] text-muted';
                pageLabel.textContent = `صفحه ${pagination.current_page} از ${pagination.last_page} · ${pagination.total} نتیجه`;
                const buttons = document.createElement('div');
                buttons.className = 'flex gap-2';
                [[pagination.current_page - 1, 'قبلی'], [pagination.current_page + 1, 'بعدی']].forEach(([page, label]) => {
                    if (page < 1 || page > pagination.last_page) return;
                    const control = document.createElement('button');
                    control.type = 'button';
                    control.className = 'rounded-lg border border-slate-200 px-3 py-1 text-[11px] font-bold text-brand hover:bg-blue-50';
                    control.textContent = label;
                    control.addEventListener('click', () => loadResultPage(pagination.search_token, page));
                    buttons.appendChild(control);
                });
                controls.append(pageLabel, buttons);
                list.appendChild(controls);
            }
            bubble.appendChild(list);
        }
        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    }

    async function loadResultPage(token, page) {
        button.disabled = true;
        status.textContent = '';
        status.classList.add('hidden');
        try {
            const response = await fetch('{{ route('newui.assistant.message') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ results_page: true, search_token: token, page }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'دریافت صفحه نتیجه ممکن نشد.');
            addMessage('assistant', data.text || 'نتایجی برای این صفحه وجود ندارد.', Array.isArray(data.results) ? data.results : [], data.pagination ? { ...data.pagination, search_token: data.search_token } : null);
        } catch (error) {
            status.textContent = error.message || 'دریافت صفحه نتیجه ممکن نشد.';
            status.classList.remove('hidden');
        } finally {
            button.disabled = false;
        }
    }

    function addConsentPrompt(data) {
        const row = document.createElement('div');
        row.className = 'flex justify-end';
        const card = document.createElement('div');
        card.className = 'max-w-[96%] min-w-0 rounded-2xl rounded-tl-sm border border-amber-200 bg-amber-50 p-4 text-sm leading-7 text-slate-800 sm:max-w-[88%]';
        const heading = document.createElement('p');
        heading.className = 'font-bold';
        heading.textContent = 'اجازهٔ ارسال این متن‌ها به ' + data.consent_destination + ' را می‌دهید؟';
        card.appendChild(heading);
        const explanation = document.createElement('p');
        explanation.className = 'mt-1 text-xs text-slate-600';
        explanation.textContent = 'فقط پرسش شما و عنوان، تاریخ/نوع و متن دقیقِ نمایش‌داده‌شده برای هر سند ارسال می‌شود. فایل‌های پیوست ارسال نمی‌شوند. تأیید فقط برای همین فهرست معتبر است و تا ۵ دقیقه استفاده می‌شود.';
        card.appendChild(explanation);
        (data.consent_preview || []).forEach((doc) => {
            const item = document.createElement('div');
            item.className = 'mt-3 rounded-xl border border-amber-100 bg-white p-3';
            const title = document.createElement('p');
            title.className = 'font-bold';
            title.textContent = doc.title || 'سند';
            item.appendChild(title);
            const meta = document.createElement('p');
            meta.className = 'text-[11px] text-slate-500';
            meta.textContent = doc.date_and_type || '';
            item.appendChild(meta);
            const content = document.createElement('p');
            content.className = 'mt-2 whitespace-pre-wrap break-words text-xs text-slate-700';
            content.textContent = doc.content_excerpt || 'متنی برای پیش‌نمایش ندارد.';
            item.appendChild(content);
            card.appendChild(item);
        });
        const actions = document.createElement('div');
        actions.className = 'mt-4 flex flex-wrap gap-2';
        const approve = document.createElement('button');
        approve.type = 'button';
        approve.className = 'rounded-xl bg-brand px-4 py-2 text-xs font-bold text-white hover:bg-blue-800';
        approve.textContent = 'بله، همین موارد ارسال شوند';
        const decline = document.createElement('button');
        decline.type = 'button';
        decline.className = 'rounded-xl border border-slate-200 px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-50';
        decline.textContent = 'خیر، ارسال نشود';
        decline.addEventListener('click', async () => {
            approve.disabled = true;
            decline.disabled = true;
            try {
                await fetch('{{ route('newui.assistant.message') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ cancel_content: true, consent_token: data.consent_token }),
                });
            } finally {
            addMessage('assistant', 'درخواست ارسال متن اسناد لغو شد؛ هیچ محتوایی برای GapGPT فرستاده نشد.');
            }
        });
        approve.addEventListener('click', async () => {
            approve.disabled = true;
            decline.disabled = true;
            button.disabled = true;
            try {
                const response = await fetch('{{ route('newui.assistant.message') }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json', 'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ share_content: true, consent_token: data.consent_token }),
                });
                const result = await response.json();
                if (!response.ok) throw new Error(result.message || 'ارسال پس از تأیید ناموفق بود.');
                addMessage('assistant', result.text || 'پاسخی دریافت نشد.', Array.isArray(result.results) ? result.results : []);
            } catch (error) {
                status.textContent = error.message || 'ارسال پس از تأیید ناموفق بود.';
                status.classList.remove('hidden');
            } finally {
                button.disabled = false;
            }
        });
        actions.append(approve, decline);
        card.appendChild(actions);
        row.appendChild(card);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
    }

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        if (fileInput.files.length) {
            await analyzeUploadedFile(fileInput.files[0]);
            return;
        }
        const question = input.value.trim();
        if (!question) return;

        addMessage('user', question);
        input.value = '';
        status.textContent = '';
        status.classList.add('hidden');
        button.disabled = true;

        try {
            const response = await fetch('{{ route('newui.assistant.message') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                },
                body: JSON.stringify({ message: question }),
            });
            const data = await response.json();
            if (!response.ok) throw new Error(data.message || 'ارسال پرسش ناموفق بود.');
            addMessage('assistant', data.text || 'پاسخی دریافت نشد.', Array.isArray(data.results) ? data.results : [], data.pagination ? { ...data.pagination, search_token: data.search_token } : null);
            if (data.consent_required && data.consent_token) addConsentPrompt(data);
        } catch (error) {
            status.textContent = error.message || 'ارتباط با دستیار برقرار نشد.';
            status.classList.remove('hidden');
        } finally {
            button.disabled = false;
            input.focus();
        }
    });

    document.getElementById('clear-chat').addEventListener('click', () => {
        messages.replaceChildren();
        addMessage('assistant', 'گفت‌وگوی تازه شروع شد. سؤالت را بنویس.');
        status.classList.add('hidden');
        input.focus();
    });
})();
</script>
</div>
<div id="newui-vue-root"></div>
</div>
@include('newui.partials.spa-router')
</body>
</html>
