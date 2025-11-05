<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}
        <x-filament::button type="submit" class="mt-3">
            ارسال
        </x-filament::button>
        <x-filament::button wire:click="submitBale" class="mt-3">
            ارسال در بله
        </x-filament::button>
    </form>
</x-filament-panels::page>
