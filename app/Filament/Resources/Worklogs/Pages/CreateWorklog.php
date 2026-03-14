<?php

namespace App\Filament\Resources\Worklogs\Pages;

use App\Filament\Resources\Worklogs\WorklogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWorklog extends CreateRecord
{
    protected static string $resource = WorklogResource::class;
}
