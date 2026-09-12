<?php

namespace App\Filament\Components\Actions;

use App\Traits\Filament\LogsAdminActivity;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

class LoggedDeleteBulkAction extends DeleteBulkAction
{
    use LogsAdminActivity;

    protected function setUp(): void
    {
        parent::setUp();

        // One event per deleted record; they're gone from the database but still in memory.
        $this->after(fn (Collection $records) => $records->each(fn (Model $record) => static::logAdminActivity('delete', $record)));
    }
}
