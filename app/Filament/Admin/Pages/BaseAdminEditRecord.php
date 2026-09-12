<?php

namespace App\Filament\Admin\Pages;

use App\Traits\Filament\LogsAdminActivity;
use Filament\Resources\Pages\EditRecord;

abstract class BaseAdminEditRecord extends EditRecord
{
    use LogsAdminActivity;

    /** @var array<string, mixed> */
    protected array $attributesBeforeSave = [];

    protected function beforeSave(): void
    {
        $this->attributesBeforeSave = $this->getRecord()->getAttributes();
    }

    protected function afterSave(): void
    {
        // getChanges() is cast-aware, so a no-op save produces nothing here.
        $changes = static::buildDiff($this->attributesBeforeSave, $this->getRecord()->getChanges());

        if ($changes === []) {
            return;
        }

        static::logAdminActivity('update', $this->getRecord(), ['changes' => $changes]);
    }
}
