<?php

namespace App\Filament\Resources\UserSettings;

use App\Filament\Resources\UserSettings\Pages\CreateUserSetting;
use App\Filament\Resources\UserSettings\Pages\EditUserSetting;
use App\Filament\Resources\UserSettings\Pages\ListUserSettings;
use App\Filament\Resources\UserSettings\Schemas\UserSettingForm;
use App\Filament\Resources\UserSettings\Tables\UserSettingsTable;
use App\Models\UserSetting;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserSettingResource extends Resource
{
    protected static ?string $model = UserSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return UserSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Filament::auth()->id();

        if ($userId !== null) {
            UserSetting::ensureForUser($userId);
        }

        return parent::getEloquentQuery()
            ->when($userId !== null, fn (Builder $query): Builder => $query->where('user_id', $userId));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUserSettings::route('/'),
            'create' => CreateUserSetting::route('/create'),
            'edit' => EditUserSetting::route('/{record}/edit'),
        ];
    }
}
