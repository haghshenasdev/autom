<?php

namespace App\Filament\Pages;

use App\Models\BaleUser;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Bale extends Page
{

    use HasPageShield;

    public $code = null;
    public $is_sendnotif = true;

    public $state = null;

    public $data;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-bottom-center';

    protected static string $view = 'filament.pages.bale';

    protected static ?string $navigationGroup = 'سیستم';

    protected static ?int $navigationSort = 5;

    protected static ?string $title = 'تعامل با پیامرسان بله';

    public function mount(): void
    {
        // فرض کنید user_id برابر با کاربر احراز شده است
        $baleUser = BaleUser::where('user_id', auth()->id())->first();

        // اگر کاربر وجود داشت، کد را از دیتابیس بخوانید
        if ($baleUser) {
            $this->code = $baleUser->bale_username; // یا هر فیلدی که کد شما در آن ذخیره شده است
            $this->is_sendnotif = $baleUser->is_sendnotif;
            $this->state = $baleUser->state;
            $this->data = $baleUser;
        }
    }

    public function createCode()
    {
        $this->code = random_int(1000,9999);
        BaleUser::query()->updateOrCreate(
            ['user_id' => auth()->id()], // شرایط جستجو
            ['bale_username' => $this->code,'is_sendnotif' => $this->is_sendnotif] // داده‌هایی که باید به‌روز یا ایجاد شوند
        );
    }

    public function remove()
    {
        BaleUser::query()->find($this->data['id'])->delete();
        Notification::make()
            ->title('دسترسی حذف شد')
            ->success()
            ->send();
        $this->reset();
    }
}
