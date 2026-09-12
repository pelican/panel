<?php

namespace App\Filament\Components\Actions;

use App\Traits\Filament\LogsAdminActivity;
use Filament\Actions\DeleteAction;
use Illuminate\Database\Eloquent\Model;

class LoggedDeleteAction extends DeleteAction
{
    use LogsAdminActivity;

    protected function setUp(): void
    {
        parent::setUp();

        // The record is deleted but still in memory here.
        $this->after(fn (Model $record) => static::logAdminActivity('delete', $record));
    }
}
