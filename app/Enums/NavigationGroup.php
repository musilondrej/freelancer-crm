<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum NavigationGroup: string implements HasLabel
{
    case Projects = 'projects';
    case Leads = 'leads';
    case Customers = 'customers';
    case Reports = 'reports';

    public function getLabel(): string
    {
        return match ($this) {
            self::Projects => __('Projects'),
            self::Leads => __('Leads'),
            self::Customers => __('Customers'),
            self::Reports => __('Reports'),
        };
    }
}
