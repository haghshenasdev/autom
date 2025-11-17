<x-filament-widgets::widget>
    <x-filament::section>
            <div class="p-4">
                <h3 class="text-lg font-semibold">پیشرفت پروژه{{ $selectedYear ? " در سال ".$selectedYear : '' }}</h3>
                <p class="text-sm text-gray-500 mb-5">درصد کار‌های انجام‌شده: {{ $progress }}%</p>

                <!-- Progress Bar -->
                <div class="mt-5">
                    <div class="w-2/3 mx-auto mt-10">
                        <div class="bg-gray-300 rounded-full h-6">
                            <div class="bg-primary-500 h-6 rounded-full" style="width: {{ $progress }}%;"></div>
                        </div>
                    </div>
                </div>
            </div>
    </x-filament::section>
</x-filament-widgets::widget>
