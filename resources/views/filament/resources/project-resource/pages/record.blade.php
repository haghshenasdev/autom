<x-filament-panels::page>
    {{-- فرم بالای ویجت‌ها --}}
        {{ $this->form }}

    {{-- ویجت‌ها --}}
    <x-filament-widgets::widgets
        :widgets="$this->mygetHeaderWidgets()"
        :columns="2"
    />
</x-filament-panels::page>
