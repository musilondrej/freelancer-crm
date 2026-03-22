<?php

namespace App\Providers;

use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Select::configureUsing(function (Select $select): void {
            $select->native(false);
        });

        Page::formActionsAlignment(Alignment::Right);

        CreateRecord::disableCreateAnother();

        CreateAction::configureUsing(fn (CreateAction $action): CreateAction => $action->createAnother(false));

        // Model::preventLazyLoading(! app()->isProduction());
    }
}
