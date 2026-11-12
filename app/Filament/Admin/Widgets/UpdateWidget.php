<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TablerIcon;
use App\Filament\Admin\Pages\Dashboard;
use App\Services\Helpers\PanelUpdateService;
use App\Services\Helpers\SoftwareVersionService;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Checkbox;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Support\Facades\Cookie;

class UpdateWidget extends FormWidget implements HasActions
{
    // Needed to mount the update_now action's confirmation modal, the other header actions are only links.
    use InteractsWithActions;

    protected static ?int $sort = 0;

    private SoftwareVersionService $softwareVersionService;

    private PanelUpdateService $panelUpdateService;

    // boot() rather than mount(), the form is rebuilt on every request once the update action is used.
    public function boot(SoftwareVersionService $softwareVersionService, PanelUpdateService $panelUpdateService): void
    {
        $this->softwareVersionService = $softwareVersionService;
        $this->panelUpdateService = $panelUpdateService;
    }

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        $latestVersion = $this->softwareVersionService->latestPanelVersion();

        if ($latestVersion === null && $this->softwareVersionService->currentComparableVersion() !== null) {
            return $schema
                ->components([
                    Section::make(trans('admin/dashboard.sections.intro-version-unavailable.heading'))
                        ->icon(TablerIcon::CloudOff)
                        ->iconColor('gray')
                        ->schema([
                            TextEntry::make('info')
                                ->hiddenLabel()
                                ->state(trans('admin/dashboard.sections.intro-version-unavailable.content', ['version' => $this->softwareVersionService->currentPanelVersion()])),
                        ]),
                ]);
        }

        $isLatest = $this->softwareVersionService->isLatestPanel();
        $isContainer = is_running_in_docker();

        return $schema
            ->components([
                $isLatest
                ? Section::make(trans('admin/dashboard.sections.intro-no-update.heading'))
                    ->icon(TablerIcon::Checkbox)
                    ->iconColor('success')
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-no-update.content', ['version' => $this->softwareVersionService->currentPanelVersion()])),
                    ])
                : Section::make(trans('admin/dashboard.sections.intro-update-available.heading'))
                    ->key('update-available')
                    ->icon(TablerIcon::InfoCircle)
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-update-available.' . ($isContainer ? 'content_container' : 'content'), ['latestVersion' => $latestVersion])),
                        Section::make(trans('admin/dashboard.sections.intro-update-available.button_changelog'))
                            ->icon(TablerIcon::Script)
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('changelog')
                                    ->hiddenLabel()
                                    ->state($this->softwareVersionService->latestPanelVersionChangelog())
                                    ->markdown(),
                            ]),
                    ])
                    ->headerActions([
                        $this->updateAction($latestVersion, $isContainer),
                        Action::make('db_update')
                            ->label(trans('admin/dashboard.sections.intro-update-available.button_guide'))
                            ->icon(TablerIcon::ClipboardText)
                            ->button()
                            ->url('https://pelican.dev/docs/panel/update', true)
                            ->color('warning'),
                    ]),
            ]);
    }

    private function updateAction(?string $latestVersion, bool $isContainer): Action
    {
        $visible = !$isContainer && $latestVersion !== null && user()?->isRootAdmin();
        $preflight = $visible ? $this->panelUpdateService->preflight($latestVersion) : null;

        return Action::make('update_now')
            ->label(trans('admin/update.button'))
            ->icon(TablerIcon::CloudDownload)
            ->button()
            ->color('success')
            ->visible($visible)
            ->authorize(fn () => (bool) user()?->isRootAdmin())
            ->disabled(filled($preflight['errors'] ?? null))
            ->tooltip($preflight['errors'][0] ?? null)
            ->requiresConfirmation()
            ->modalIcon(TablerIcon::CloudDownload)
            ->modalHeading(trans('admin/update.modal.heading', ['version' => $latestVersion]))
            ->modalDescription(trans('admin/update.modal.description', [
                'from' => $this->softwareVersionService->currentPanelVersion(),
                'to' => $latestVersion,
            ]))
            ->schema([
                TextEntry::make('warnings')
                    ->label(trans('admin/update.modal.warnings'))
                    ->state(implode(PHP_EOL, $preflight['warnings'] ?? []))
                    ->visible(filled($preflight['warnings'] ?? null)),
                Checkbox::make('force_plugins')
                    ->label(trans('admin/update.modal.plugins', ['version' => $latestVersion, 'plugins' => implode(', ', $preflight['plugins'] ?? [])]))
                    ->accepted()
                    ->visible(filled($preflight['plugins'] ?? null)),
                Checkbox::make('skip_db_backup')
                    ->label(trans('admin/update.modal.no_backup'))
                    ->accepted()
                    ->visible($preflight['needs_backup_confirmation'] ?? false),
            ])
            ->action(function (array $data, PanelUpdateService $panelUpdateService) use ($latestVersion) {
                try {
                    $secret = $panelUpdateService->launch(user(), (string) $latestVersion, (bool) ($data['force_plugins'] ?? false), (bool) ($data['skip_db_backup'] ?? false));
                } catch (Exception $exception) {
                    report($exception);

                    Notification::make()
                        ->danger()
                        ->title(trans('admin/update.notifications.failed'))
                        ->body($exception->getMessage())
                        ->send();

                    return;
                }

                // Lets this admin keep using the panel, and watch the progress, while it is in maintenance mode.
                Cookie::queue(MaintenanceModeBypassCookie::create($secret));

                Notification::make()
                    ->success()
                    ->title(trans('admin/update.notifications.started'))
                    ->body(trans('admin/update.notifications.started_body'))
                    ->send();

                $this->redirect(Dashboard::getUrl(panel: 'admin'));
            });
    }
}
