<?php

namespace App\Filament\Resources\LetterResource\RelationManagers;

use App\Filament\Resources\TaskResource;
use App\Models\Task;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class TasksRelationManager extends RelationManager
{
    protected static string $relationship = 'tasks';

    protected static ?string $label = 'فعالیت';

    protected static ?string $pluralLabel = 'فعالیت‌ها';

    protected static ?string $modelLabel = 'فعالیت';

    protected static ?string $title = 'فعالیت‌های مرتبط';

    public function form(Form $form): Form
    {
        $schema = Task::formSchema();
        $schema[] = Forms\Components\Select::make('project_id')
            ->label('دستورکار')
            ->relationship('project', 'name')
            ->searchable()
            ->preload()
            ->multiple();

        return $form->schema($schema);
    }

    public function table(Table $table): Table
    {
        return TaskResource::table($table);
    }
}
