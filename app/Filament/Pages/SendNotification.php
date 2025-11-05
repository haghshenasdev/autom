<?php

namespace App\Filament\Pages;

use App\Http\Controllers\BaleBotController;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Illuminate\Contracts\View\View;

class SendNotification extends Page implements HasForms
{
    use InteractsWithForms, HasPageShield;

    // عنوان‌های ناوبری/صفحه (اینها protected هستند و نباید مستقیماً دسترسی داده شوند)
    protected static ?string $navigationLabel = 'ارسال نوتیفیکیشن';
    protected static ?string $navigationIcon = 'heroicon-o-bell';
    protected static ?string $slug = 'send-notification';

    protected static ?string $navigationGroup = 'اطلاع رسانی';


    // اگر می‌خواهی عنوان صفحه را ستاتیک ست کنی:
    protected static ?string $title = 'ارسال نوتیفیکیشن';

    // استیت فرم را داخل این پراپرتی نگه می‌داریم تا با title صفحه تداخل نکند
    public ?array $data = [];

    protected static string $view = 'filament.pages.send-notification';

    public function mount(): void
    {
        $this->form->fill();
    }

    // تعریف فرم به سبک Filament 3
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('عنوان')
                    ->required(),

                Textarea::make('body')
                    ->label('توضیحات')
                    ->rows(5)   ,

                Select::make('recipient_id')
                    ->label('گیرنده')
                    ->options(User::query()->pluck('name', 'id'))
                    ->searchable()
                    ->required(),
            ])
            // نکته کلیدی: statePath تا Livewire سراغ $this->title نرود
            ->statePath('data');
    }

    public function submit(): void
    {

        Notification::make()
            ->title($this->data['title'])
            ->body($this->data['body'])
            ->sendToDatabase(User::find($this->data['recipient_id']));

        Notification::make()
            ->title('نوتیفیکیشن با موفقیت ارسال شد')
            ->success()
            ->send();

        // اگر خواستی بعد از ارسال فرم ریست شود:
        $this->form->fill([]);
    }

    public function submitBale(): void
    {

        $bale_bot = new BaleBotController();
        $message  = $this->data['title'] . "\n\n";
        $message .= $this->data['body'];
        $bale_bot->sendNotifBale($this->data['recipient_id'],$message);
        Notification::make()
            ->title('نوتیفیکیشن با موفقیت ارسال شد')
            ->success()
            ->send();

        // اگر خواستی بعد از ارسال فرم ریست شود:
        $this->form->fill([]);
    }

    public function create(): void
    {
        $this->submit();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('submit')
                ->label('ارسال')
                ->submit('submit'),
        ];
    }
}
