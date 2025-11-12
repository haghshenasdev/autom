<?php

namespace App\Filament\Widgets;

namespace App\Filament\Widgets;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Saade\FilamentFullCalendar\Actions\DeleteAction;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Data\EventData;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{

    public Model | string | null $model = Task::class;

    public function fetchEvents(array $fetchInfo): array
    {
        return Task::query()
            ->where('started_at', '>=', $fetchInfo['start'])
            ->get()
            ->map(
                fn (Task $event) => EventData::make()
                    ->id($event->id)
                    ->title(($event->group->contains('id', 1) ? '🧰 ' :
                            ($event->group->contains('id', 33) ? '📝 ' : ($event->group->contains('id', 2) ? '🕹️ ' : ''))
                        ) . $event->name)
                    ->start($event->started_at)
                    ->end($event->ended_at)
                    ->backgroundColor($event->completed ? 'green' : 'blue')
                    ->url(
                        url: TaskResource::getUrl(name: 'edit', parameters: ['record' => $event]),
                        shouldOpenUrlInNewTab: true
                    )
            )
            ->toArray();
    }

    public function getFormSchema(): array
    {
        return Task::formSchema();
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    public static function canView(): bool
    {
        return false;
    }

}
