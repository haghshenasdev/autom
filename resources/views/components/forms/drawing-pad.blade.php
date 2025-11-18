<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data
        x-init="
            (async () => {
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
                const btnDownload = el.querySelector('[data-action=download]');
                const btnShow = el.querySelector('[data-action=show]');

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

                @if($getState())
                    @php $state = $getState(); @endphp
                    @if(is_string($state) && str_starts_with($state, 'data:image'))
                        const prevImg = new Image();
                        prevImg.src = '{{ $state }}';
                        prevImg.onload = () => {
                            canvas.getContext('2d').drawImage(prevImg, 0, 0, 794, 1123);
                        };
                    @endif
                @endif

                penSize.addEventListener('input', e => {
                    const w = Number(e.target.value);
                    signaturePad.minWidth = w;
                    signaturePad.maxWidth = w;
                });

                let history = [];
                let step = -1;
                function saveStep() {
                    step++;
                    history = history.slice(0, step);
                    history.push(signaturePad.toData());
                }
                signaturePad.addEventListener('endStroke', saveStep);

                function setState(value) {
                    if (window.Livewire && $wire) {
                        $wire.set('{{ $getStatePath() }}', value);
                    }
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

                signaturePad.addEventListener('endStroke', () => {
                    const dataUrl = signaturePad.toDataURL('image/png');
                    setState(dataUrl);
                });

                btnDownload.addEventListener('click', () => {
                    const link = document.createElement('a');
                    link.href = signaturePad.toDataURL('image/png');
                    link.download = 'drawing.png';
                    link.click();
                });

                btnShow.addEventListener('click', () => {
                    if (input.value && !input.value.startsWith('data:image')) {
                        window.open('{{ env('APP_URL') }}/private-show2/' + input.value, '_blank');
                    }
                });
            })();
        "
    >
        {{-- نوار ابزار بالای صفحه با آیکون‌ها --}}
        <div class="flex gap-2 items-center justify-center mb-2">
            <button type="button" data-action="clear" class="p-2 border rounded">
                <x-filament::icon icon="heroicon-o-trash" class="w-5 h-5"/>
            </button>
            <button type="button" data-action="undo" class="p-2 border rounded">
                <x-filament::icon icon="heroicon-o-arrow-uturn-left" class="w-5 h-5"/>
            </button>
            <button type="button" data-action="redo" class="p-2 border rounded">
                <x-filament::icon icon="heroicon-o-arrow-uturn-right" class="w-5 h-5"/>
            </button>
            <button type="button" data-action="download" class="p-2 border rounded">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" class="w-5 h-5"/>
            </button>
            <button type="button" data-action="show" class="p-2 border rounded">
                <x-filament::icon icon="heroicon-o-eye" class="w-5 h-5"/>
            </button>
            <label class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-pencil" class="w-5 h-5"/>
                <input type="range" min="1" max="10" value="2">
            </label>
        </div>

        <canvas width="794" height="1123"
                style="border:1px solid #ccc; background:#fff; display:block; margin:0 auto;"></canvas>

        <input
            type="hidden"
            id="drawing-pad-input-{{ $getId() }}"
            name="{{ $getName() }}"
        {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
        value="{{ $getState() }}"
        >
    </div>
</x-dynamic-component>
