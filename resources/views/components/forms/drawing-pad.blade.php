<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data
        x-init="
            (async () => {
                // بارگذاری داینامیک کتابخانه فقط وقتی لازم شود
                if (!window.SignaturePad) {
                    await new Promise(resolve => {
                        const s = document.createElement('script');
                        s.src = 'https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js';
                        s.onload = resolve;
                        document.head.appendChild(s);
                    });
                }

                const el = $el;
                const canvas = el.querySelector('canvas');
                const input = el.querySelector('input[type=hidden]');
                const penSize = el.querySelector('input[type=range]');
                const btnClear = el.querySelector('[data-action=clear]');
                const btnUndo  = el.querySelector('[data-action=undo]');
                const btnRedo  = el.querySelector('[data-action=redo]');

                const signaturePad = new window.SignaturePad(canvas, {
                    penColor: 'black',
                    backgroundColor: 'white',
                    minWidth: 2,
                    maxWidth: 2,
                });

                function resizeCanvas() {
                    const ratio = Math.max(window.devicePixelRatio || 1, 1);
                    canvas.width = 794 * ratio;
                    canvas.height = 1123 * ratio;
                    const ctx = canvas.getContext('2d');
                    ctx.setTransform(1,0,0,1,0,0);
                    ctx.scale(ratio, ratio);
                    signaturePad.clear();

                    // اگر state قبلی مسیر فایل بود، بارگذاری کن
                    @if($getState())
                        @php
                            $state = $getState();
                            $isBase64 = is_string($state) && str_starts_with($state, 'data:image');
                        @endphp
                        @if(!$isBase64)
                            const img = new Image();
                            img.crossOrigin = 'anonymous';
                            img.src = '{{ Storage::disk($getRecord() ? ($field->getDisk() ?? 'public') : ($field->getDisk() ?? 'public'))->url($getState()) }}';
                            img.onload = () => {
                                ctx.drawImage(img, 0, 0, 794, 1123);
                            };
                        @endif
                    @endif
                }
                resizeCanvas();
                window.addEventListener('resize', resizeCanvas);

                // اگر state قبلی base64 بود، روی canvas بنویس (ریکاوری رسم)
                @if($getState())
                    @php
                        $state = $getState();
                    @endphp
                    @if(is_string($state) && str_starts_with($state, 'data:image'))
                        const prevImg = new Image();
                        prevImg.src = '{{ $state }}';
                        prevImg.onload = () => {
                            canvas.getContext('2d').drawImage(prevImg, 0, 0, 794, 1123);
                        };
                    @endif
                @endif

                // اندازه قلم
                penSize.addEventListener('input', e => {
                    const w = Number(e.target.value);
                    signaturePad.minWidth = w;
                    signaturePad.maxWidth = w;
                });

                // تاریخچه برای undo/redo
                let history = [];
                let step = -1;
                function saveStep() {
                    step++;
                    history = history.slice(0, step);
                    history.push(signaturePad.toData());
                }
                signaturePad.addEventListener('endStroke', saveStep);

                // Helper: ست کردن به Livewire و sync به input
                function setState(value) {
                    // روش ۱: ست مستقیم به State لایو‌وایر
                    if (window.Livewire && $wire) {
                        $wire.set('{{ $getStatePath() }}', value);
                    }
                    // روش ۲: بروزرسانی input + دیسpatch رویداد input (اگر wire:model استفاده شده)
                    input.value = value;
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }

                btnClear.addEventListener('click', () => {
                    signaturePad.clear();
                    setState('');
                    history = [];
                    step = -1;
                });

                btnUndo.addEventListener('click', () => {
                    if (step > 0) {
                        step--;
                        signaturePad.fromData(history[step]);
                        // بعد از تغییر، state را هم آپدیت کن
                        setState(signaturePad.toDataURL('image/png'));
                    }
                });

                btnRedo.addEventListener('click', () => {
                    if (step < history.length - 1) {
                        step++;
                        signaturePad.fromData(history[step]);
                        setState(signaturePad.toDataURL('image/png'));
                    }
                });

                // هر بار پایان رسم، state را ست کن
                signaturePad.addEventListener('endStroke', () => {
                    const dataUrl = signaturePad.toDataURL('image/png');
                    setState(dataUrl);
                });
            })();
        "
    >
        <canvas width="794" height="1123" style="border:1px solid #ccc; background:#fff; display:block; margin:0 auto;"></canvas>

        <div class="mt-2 flex gap-2 items-center justify-center">
            <button type="button" data-action="clear" class="px-3 py-1 border rounded">پاک کردن</button>
            <button type="button" data-action="undo" class="px-3 py-1 border rounded">Undo</button>
            <button type="button" data-action="redo" class="px-3 py-1 border rounded">Redo</button>
            <label class="flex items-center gap-2">
                <span>اندازه قلم:</span>
                <input type="range" min="1" max="10" value="2">
            </label>
        </div>

        {{-- توجه: این input به statePath بایند شده تا Livewire مقدار را بگیرد --}}
        <input
            type="hidden"
            id="drawing-pad-input-{{ $getId() }}"
            name="{{ $getName() }}"
        {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
        value="{{ $getState() }}"
        >
    </div>
</x-dynamic-component>
