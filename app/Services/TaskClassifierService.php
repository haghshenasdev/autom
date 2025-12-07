<?php

namespace App\Services;


class TaskClassifierService
{
    protected $modelPath = 'storage/app/models/task-model.model';

    public function train()
    {

    }

    public function predict(string $name): array
    {
        return [];
    }
}
