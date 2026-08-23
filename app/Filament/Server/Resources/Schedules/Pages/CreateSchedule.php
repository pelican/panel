<?php

namespace App\Filament\Server\Resources\Schedules\Pages;

use App\Facades\Activity;
use App\Filament\Server\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Models\Server;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSchedule extends CreateRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ScheduleResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreateFormAction(): Action
    {
        $hasFormWrapper = $this->hasFormWrapper();

        return Action::make('exclude_create')
            ->label(trans('filament-panels::resources/pages/create-record.form.actions.create.label'))
            ->submit($hasFormWrapper ? $this->getSubmitFormLivewireMethodName() : null)
            ->action($hasFormWrapper ? null : $this->getSubmitFormLivewireMethodName())
            ->keyBindings(['mod+s']);
    }

    protected function afterCreate(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->record;

        Activity::event('server:schedule.create')
            ->property('name', $schedule->name)
            ->log();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (!isset($data['server_id'])) {
            /** @var Server $server */
            $server = Filament::getTenant();

            $data['server_id'] = $server->id;
        }

        if (!isset($data['next_run_at'])) {
            $data['next_run_at'] = ScheduleResource::getNextRun(
                $data['cron_minute'],
                $data['cron_hour'],
                $data['cron_day_of_month'],
                $data['cron_month'],
                $data['cron_day_of_week']
            );
        }

        return $data;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }
}
