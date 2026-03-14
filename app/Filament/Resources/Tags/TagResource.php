<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\Tags\RelationManagers\CustomersRelationManager;
use App\Filament\Resources\Tags\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\Tags\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Tags\RelationManagers\ProjectActivitiesRelationManager;
use App\Filament\Resources\Tags\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Tags\RelationManagers\RecurringServicesRelationManager;
use App\Filament\Resources\Tags\Schemas\TagForm;
use App\Filament\Resources\Tags\Tables\TagsTable;
use App\Models\Tag;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TagResource extends Resource
{
    protected static ?string $model = Tag::class;

    protected static ?string $cluster = ConfigurationCluster::class;

    protected static string|BackedEnum|null $navigationIcon = null;

    protected static ?int $navigationSort = 30;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return TagForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TagsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            CustomersRelationManager::class,
            ProjectsRelationManager::class,
            LeadsRelationManager::class,
            ProjectActivitiesRelationManager::class,
            RecurringServicesRelationManager::class,
            NotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTags::route('/'),
            'create' => CreateTag::route('/create'),
            'edit' => EditTag::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        $ownerId = Filament::auth()->id();

        return parent::getRecordRouteBindingEloquentQuery()
            ->when($ownerId !== null, fn (Builder $query): Builder => $query->where('owner_id', $ownerId));
    }
}
