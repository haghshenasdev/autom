<x-filament-panels::page>

    @if($state == null)
    <x-filament::card>
        <div class="flex items-center gap-1.5">
            <!-- عکس -->
            <div class="mr-4">
                <img src="{{ asset('images/bale_bot.svg') }}" alt="QR Code" class="w-48 h-48 object-cover">
            </div>

            <!-- متن -->
            <div>
                <p class="text-lg mb-2">QR Code را با گوشی اسکن کنید یا از طریق آدرس <a
                        href="https://ble.ir/karnama_Bot" target="_blank" class="">@karnama_Bot</a> وارد ربات بله شوید و
                    کد زیر را بعد از استارت برای ربات ارسال نمایید.</p>
                <div class="flex items-center gap-3 p-3 border-2">
                    <!-- دکمه برای ایجاد کد -->
                    <div class="mt-4">
                        <x-filament::button wire:click="createCode">ایجاد کد</x-filament::button>
                    </div>
                    <p class="text-gray-700">کد: <span class="font-bold">{{$code}}</span></p>
                </div>
                <label class="mt-3">
                    <x-filament::input.checkbox wire:model="is_sendnotif"/>
                    <span> دریافت اعلانات سیستم در پیام رسان بله</span>
                </label>
            </div>
        </div>
    </x-filament::card>
    @else
        <x-filament::section>
            <x-slot name="heading">
                کاربر با موفقیت به ربات بله متصل شده است .
            </x-slot>

            <p>نام کاربری : <a href="https://ble.ir/{{ $data['bale_username'] }}">{{ $data['bale_username'] }}</a></p>
            <p>آیدی بله : <a href="https://web.bale.ai/chat?uid={{ $data['bale_id'] }}">{{ $data['bale_id'] }}</a></p>
            <label class="mt-3">
                <x-filament::input.checkbox wire:model="is_sendnotif"/>
                <span> دریافت اعلانات سیستم در پیام رسان بله</span>
            </label>
            <div class="mt-4">
                <x-filament::button wire:click="remove">حذف دسترسی</x-filament::button>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
