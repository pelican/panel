<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use Boquizo\FilamentLogViewer\Actions\DeleteAction;
use Boquizo\FilamentLogViewer\Actions\DownloadAction;
use Boquizo\FilamentLogViewer\Actions\ViewLogAction;
use Boquizo\FilamentLogViewer\FilamentLogViewerPlugin;
use Boquizo\FilamentLogViewer\Pages\ListLogs as BaseListLogs;
use Boquizo\FilamentLogViewer\Tables\Columns\LevelColumn;
use Boquizo\FilamentLogViewer\Tables\Columns\NameColumn;
use Boquizo\FilamentLogViewer\UseCases\ParseDateUseCase;
use Boquizo\FilamentLogViewer\Utils\Level;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListLogs extends BaseListLogs
{
    protected string $view = 'filament.components.list-logs';

    public function getHeading(): string|null|Htmlable
    {
        return trans('admin/log.navigation.panel_logs');
    }

    public static function table(Table $table): Table
    {
        return parent::table($table)
            ->emptyStateHeading(trans('admin/log.empty_table'))
            ->emptyStateIcon(TablerIcon::Check)
            ->toolbarActions([
                BulkActionGroup::make([
                    self::exclude_downloadBulkAction(),
                    self::exclude_deleteBulkAction(),
                ]),
            ])
            ->columns([
                NameColumn::make('date'),
                LevelColumn::make(Level::ALL)
                    ->tooltip(trans('admin/log.total_logs')),
                LevelColumn::make(Level::Error)
                    ->tooltip(trans('admin/log.error')),
                LevelColumn::make(Level::Warning)
                    ->tooltip(trans('admin/log.warning')),
                LevelColumn::make(Level::Notice)
                    ->tooltip(trans('admin/log.notice')),
                LevelColumn::make(Level::Info)
                    ->tooltip(trans('admin/log.info')),
                LevelColumn::make(Level::Debug)
                    ->tooltip(trans('admin/log.debug')),
            ])
            ->recordActions([
                ViewLogAction::make()
                    ->icon(TablerIcon::FileDescription)->iconButton(),
                DownloadAction::make()
                    ->tooltip(function (array $record): string {
                        return trans('filament-log-viewer::log.table.actions.download.label', ['log' => ParseDateUseCase::execute((string) ($record['date'] ?? ''))]);
                    })
                    ->icon(TablerIcon::FileDownload)->iconButton(),
                Action::make('uploadLogs')
                    ->hiddenLabel()
                    ->tooltip(trans('admin/log.actions.upload_tooltip', ['url' => 'logs.pelican.dev']))
                    ->icon(TablerIcon::WorldUpload)
                    ->requiresConfirmation()
                    ->modalHeading(trans('admin/log.actions.upload_logs'))
                    ->modalDescription(fn ($record) => trans('admin/log.actions.upload_logs_description', ['file' => $record['date'], 'url' => 'https://logs.pelican.dev']))
                    ->action(function (array $record): void {
                        $prefix = config('filament-log-viewer.pattern.prefix', 'laravel-');
                        $extension = config('filament-log-viewer.pattern.extension', '.log');
                        $logPath = storage_path('logs/' . $prefix . (string) ($record['date'] ?? '') . $extension);

                        if (!file_exists($logPath)) {
                            Notification::make()
                                ->title(trans('admin/log.actions.log_not_found'))
                                ->body(trans('admin/log.actions.log_not_found_description', ['filename' => (string) ($record['date'] ?? '')]))
                                ->danger()
                                ->send();

                            return;
                        }

                        $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                        $totalLines = is_array($lines) ? count($lines) : 0;
                        $uploadLines = $totalLines <= 1000 ? ($lines ?: []) : array_slice($lines ?: [], -1000);
                        $content = implode("\n", (array) $uploadLines);

                        try {
                            $response = Http::timeout(10)
                                ->asMultipart()
                                ->attach('c', $content)
                                ->attach('e', '14d')
                                ->post('https://logs.pelican.dev');

                            if ($response->failed()) {
                                Notification::make()
                                    ->title(trans('admin/log.actions.failed_to_upload'))
                                    ->body(trans('admin/log.actions.failed_to_upload_description', ['status' => $response->status()]))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $data = $response->json();
                            $url = (string) ($data['url'] ?? '');

                            Notification::make()
                                ->title(trans('admin/log.actions.log_upload'))
                                ->body("{$url}")
                                ->success()
                                ->actions([
                                    Action::make('exclude_viewLogs')
                                        ->label(trans('admin/log.actions.view_logs'))
                                        ->url($url)
                                        ->openUrlInNewTab(true),
                                ])
                                ->persistent()
                                ->send();

                        } catch (\Exception $e) {
                            Notification::make()
                                ->title(trans('admin/log.actions.failed_to_upload'))
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        }
                    }),
                DeleteAction::make()
                    ->icon(TablerIcon::Trash)->iconButton(),
            ]);
    }

    private static function exclude_downloadBulkAction(): BulkAction
    {
        return BulkAction::make('exclude_download')
            ->label(trans('filament-log-viewer::log.table.actions.download.bulk.label'))
            ->icon(TablerIcon::FileZip)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(trans('filament-log-viewer::log.table.actions.download.bulk.label'))
            ->failureNotificationTitle(trans('filament-log-viewer::log.table.actions.download.bulk.error'))
            ->action(self::exclude_downloadBulkAction_getAction(...));
    }

    /**
     * @param  Collection<array-key, array<string, mixed>>  $records
     */
    private static function exclude_downloadBulkAction_getAction(
        BulkAction $action,
        Collection $records,
    ): ?BinaryFileResponse {
        try {
            $logs = $records->pluck('date')->all();

            return FilamentLogViewerPlugin::make()->downloadLogs($logs);
        } catch (\Exception) {
            $action->failure();

            return null;
        }
    }

    private static function exclude_deleteBulkAction(): DeleteBulkAction
    {
        return DeleteBulkAction::make('exclude_delete')
            ->modalHeading(trans('filament-log-viewer::log.table.actions.delete.bulk.label'))
            ->action(function (DeleteBulkAction $action) {
                $action->process(function (Collection $records): void {
                    /** @var array<string, mixed> $record */
                    foreach ($records as $record) {
                        FilamentLogViewerPlugin::make()->deleteLog((string) ($record['date'] ?? ''));
                    }
                });

                $action->success();
            });
    }
}
