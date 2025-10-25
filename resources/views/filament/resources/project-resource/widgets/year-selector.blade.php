<x-filament-widgets::widget>
<x-filament::input.wrapper>
    <x-filament::input.select wire:model="selectedYear">
        <option value="">همه سال‌ها</option>
        @foreach($years as $year)
            <option value="{{ $year }}">{{ $year }}</option>
        @endforeach
    </x-filament::input.select>
</x-filament::input.wrapper>
</x-filament-widgets::widget>
