<?php

namespace App\Filament\Resources\BacklogItems\Pages;

use App\Filament\Resources\BacklogItems\BacklogItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateBacklogItem extends CreateRecord
{
    protected static string $resource = BacklogItemResource::class;
}
