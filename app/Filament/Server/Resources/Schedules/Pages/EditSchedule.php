<?php

namespace App\Filament\Server\Resources\Schedules\Pages;

use App\Enums\ScheduleStatus;
use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Components\Actions\ExportScheduleAction;
use App\Filament\Server\Resources\Schedules\ScheduleResource;
use App\Models\Schedule;
use App\Services\Schedules\ProcessScheduleService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditSchedule extends EditRecord
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = ScheduleResource::class;

    protected function afterSave(): void
    {
        /** @var Schedule $schedule */
        $schedule = $this->record;

        Activity::event('server:schedule.update')
            ->property('name', $schedule->name)
            ->log();
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['timezone'] = user()->timezone ?? 'UTC';
        $data['next_run_at'] = ScheduleResource::getNextRun(
            $data['cron_minute'],
            $data['cron_hour'],
            $data['cron_day_of_month'],
            $data['cron_month'],
            $data['cron_day_of_week'],
            $data['timezone']
        );

        return $data;
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->after(function ($record) {
                    Activity::event('server:schedule.delete')
                        ->property('name', $record->name)
                        ->log();
                }),
            Action::make('run_now')
                ->hiddenLabel()
                ->icon(TablerIcon::Run)
                ->authorize(fn () => user()?->can(SubuserPermission::ScheduleUpdate, Filament::getTenant()))
                ->tooltip(fn (Schedule $schedule) => $schedule->tasks->count() === 0 ? trans('server/schedule.no_tasks') : ($schedule->status === ScheduleStatus::Processing ? ScheduleStatus::Processing->getLabel() : trans('server/schedule.run_now')))
                ->color(fn (Schedule $schedule) => $schedule->tasks->count() === 0 || $schedule->status === ScheduleStatus::Processing ? 'warning' : 'primary')
                ->disabled(fn (Schedule $schedule) => $schedule->tasks->count() === 0 || $schedule->status === ScheduleStatus::Processing)
                ->action(function (ProcessScheduleService $service, Schedule $schedule) {
                    $service->handle($schedule, true);

                    Activity::event('server:schedule.execute')
                        ->subject($schedule)
                        ->property('name', $schedule->name)
                        ->log();

                    $this->fillForm();
                }),
            ExportScheduleAction::make(),
            Action::make('save')
                ->hiddenLabel()
                ->action('save')
                ->keyBindings(['mod+s'])
                ->icon(TablerIcon::DeviceFloppy)
                ->tooltip(trans('server/schedule.save')),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getFormActions(): array
    {
        return [];
    }
}
