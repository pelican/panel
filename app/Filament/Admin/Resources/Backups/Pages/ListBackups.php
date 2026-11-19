<?php

namespace App\Filament\Admin\Resources\Backups\Pages;

use App\Filament\Admin\Resources\Backups\BackupResource;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Resources\Pages\ListRecords;

class ListBackups extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = BackupResource::class;
}
