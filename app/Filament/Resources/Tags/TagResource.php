<?php

namespace App\Filament\Resources\Tags;

use App\Filament\Clusters\Configuration\ConfigurationCluster;
use App\Filament\Resources\Tags\Pages\CreateTag;
use App\Filament\Resources\Tags\Pages\EditTag;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Filament\Resources\Tags\RelationManagers\CustomersRelationManager;
use App\Filament\Resources\Tags\RelationManagers\LeadsRelationManager;
use App\Filament\Resources\Tags\RelationManagers\NotesRelationManager;
use App\Filament\Resources\Tags\RelationManagers\ProjectsRelationManager;
use App\Filament\Resources\Tags\RelationManagers\RecurringServicesRelationManager;
use App\Filament\Resources\Tags\RelationManagers\TasksRelationManager;
use App\Filament\Resources\Tags\Schemas\TagForm;
use App\Filament\Resources\Tags\Tables\TagsTable;
use App\Models\Tag;
use App\Support\Filament\FilteredByOwner;
use BackedEnum;
use Filament\Resources\RelationManagers\RelationGroup;
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
            RelationGroup::make('CRM', [
                CustomersRelationManager::class,
                LeadsRelationManager::class,
            ]),
            RelationGroup::make('Delivery', [
                ProjectsRelationManager::class,
                TasksRelationManager::class,
                RecurringServicesRelationManager::class,
            ]),
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
        return FilteredByOwner::applyTo(parent::getEloquentQuery());
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return FilteredByOwner::applyTo(parent::getRecordRouteBindingEloquentQuery());
    }
}
