<?php

namespace App\Filament\Clusters\Configuration;

use BackedEnum;
use Filament\Clusters\Cluster;
use UnitEnum;

class ConfigurationCluster extends Cluster
{
    protected static ?string $navigationLabel = 'Configuration';

    protected static string|UnitEnum|null $navigationGroup = 'Setup';

    protected static ?int $navigationSort = 10;

    protected static string|BackedEnum|null $navigationIcon = null;
}
