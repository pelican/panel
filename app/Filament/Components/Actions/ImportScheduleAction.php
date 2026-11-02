<?php

namespace App\Filament\Components\Actions;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Exceptions\Http\HttpForbiddenException;
use App\Models\Server;
use App\Services\Schedules\Sharing\ScheduleImporterService;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ImportScheduleAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'import';
    }

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Server $server */
        $server = Filament::getTenant();

        $this->label(trans('filament-actions::import.modal.actions.import.label'));

        $this->authorize(fn () => user()?->can(SubuserPermission::ScheduleCreate, $server));

        $this->schema([
            Tabs::make('Tabs')
                ->contained(false)
                ->tabs([
                    Tab::make('file')
                        ->label(trans('server/schedule.import_action.file'))
                        ->icon(TablerIcon::FileUpload)
                        ->schema([
                            FileUpload::make('files')
                                ->hiddenLabel()
                                ->hint(trans('server/schedule.import_action.schedule_help'))
                                ->acceptedFileTypes(['application/json'])
                                ->preserveFilenames()
                                ->previewable(false)
                                ->storeFiles(false)
                                ->multiple(true),
                        ]),
                    Tab::make('url')
                        ->label(trans('server/schedule.import_action.url'))
                        ->icon(TablerIcon::WorldUpload)
                        ->schema([
                            Repeater::make('urls')
                                ->hiddenLabel()
                                ->itemLabel(fn (array $state) => str($state['url'])->afterLast('/schedule-')->before('.json')->headline())
                                ->hint(trans('server/schedule.import_action.url_help'))
                                ->addActionLabel(trans('server/schedule.import_action.add_url'))
                                ->grid(2)
                                ->reorderable(false)
                                ->addable(true)
                                ->deletable(fn (array $state) => count($state) > 1)
                                ->schema([
                                    TextInput::make('url')
                                        ->live()
                                        ->label(trans('server/schedule.import_action.url'))
                                        ->url()
                                        ->endsWith('.json')
                                        ->validationAttribute(trans('server/schedule.import_action.url')),
                                ]),
                        ]),
                ]),
        ]);

        $this->action(function (array $data, ScheduleImporterService $service) use ($server) {
            $schedules = array_merge(collect($data['urls'])->flatten()->whereNotNull()->unique()->all(), Arr::wrap($data['files']));
            if (empty($schedules)) {
                return;
            }

            [$success, $failed] = [collect(), collect()];

            foreach ($schedules as $schedule) {
                if ($schedule instanceof TemporaryUploadedFile) {
                    $name = str($schedule->getClientOriginalName())->afterLast('schedule-')->before('.json')->headline();
                    $method = 'fromFile';
                } else {
                    $schedule = str($schedule);
                    $schedule = $schedule->contains('github.com') ? $schedule->replaceFirst('blob', 'raw') : $schedule;
                    $name = $schedule->afterLast('/schedule-')->before('.json')->headline();
                    $method = 'fromUrl';
                }
                try {
                    $service->$method($schedule, $server, user());
                    $success->push($name);
                } catch (Exception $exception) {
                    $failed->push($exception instanceof HttpForbiddenException ? "$name: {$exception->getMessage()}" : $name);
                    report($exception);
                }
            }

            if ($failed->count() > 0) {
                Notification::make()
                    ->title(trans('server/schedule.import_action.import_failed'))
                    ->body($failed->join(', '))
                    ->danger()
                    ->send();
            }
            if ($success->count() > 0) {
                Notification::make()
                    ->title(trans('server/schedule.import_action.import_success'))
                    ->body($success->join(', '))
                    ->success()
                    ->send();
            }
        });
    }
}
