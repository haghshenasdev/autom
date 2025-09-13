<x-filament-panels::page>
    <form wire:submit="create">
        {{ $this->form }}

        <button type="submit" class="relative rounded-lg bg-custom-500 text-sm text-auto px-4 py-2">
                ارسال
        </button>
    </form>
</x-filament-panels::page>
