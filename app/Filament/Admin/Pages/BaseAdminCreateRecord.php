<?php

namespace App\Filament\Admin\Pages;

use App\Traits\Filament\LogsAdminActivity;
use Filament\Resources\Pages\CreateRecord;

abstract class BaseAdminCreateRecord extends CreateRecord
{
    use LogsAdminActivity;

    protected function afterCreate(): void
    {
        static::logAdminActivity('create', $this->getRecord());
    }
}
