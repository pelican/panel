<?php

namespace App\Filament\Admin\Pages;

use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use App\Traits\ResolvesRecordDate;
use Boquizo\FilamentLogViewer\Actions\BackAction;
use Boquizo\FilamentLogViewer\Actions\DeleteAction;
use Boquizo\FilamentLogViewer\Actions\DownloadAction;
use Boquizo\FilamentLogViewer\Pages\ViewLog as BaseViewLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Http;

class ViewLogs extends BaseViewLog
{
    use ResolvesRecordDate;

    public function getHeaderActions(): array
    {
        $iconButton = user()?->getCustomization(CustomizationKey::ButtonStyle);

        return [
            BackAction::make()
                ->tooltip(trans('filament-log-viewer::log.table.actions.close.label'))
                ->icon(TablerIcon::ArrowLeft)
                ->color('gray')
                ->when($iconButton, fn ($action) => $action->iconButton()),
            DeleteAction::make(withTooltip: true)
                ->icon(TablerIcon::Trash)
                ->when($iconButton, fn ($action) => $action->iconButton()),
            DownloadAction::make(withTooltip: true)
                ->icon(TablerIcon::FileDownload)
                ->when($iconButton, fn ($action) => $action->iconButton()),
            Action::make('uploadLogs')
                ->hiddenLabel()
                ->icon(TablerIcon::WorldUpload)
                ->requiresConfirmation()
                ->tooltip(trans('admin/log.actions.upload_tooltip', ['url' => 'logs.pelican.dev']))
                ->modalHeading(trans('admin/log.actions.upload_logs'))
                ->modalDescription(fn () => trans('admin/log.actions.upload_logs_description', ['file' => $this->resolveRecordDate(), 'url' => 'https://logs.pelican.dev']))
                ->action(function () {
                    $prefix = config('filament-log-viewer.pattern.prefix', 'laravel-');
                    $extension = config('filament-log-viewer.pattern.extension', '.log');
                    $logPath = storage_path('logs/' . $prefix . $this->resolveRecordDate() . $extension);

                    if (!file_exists($logPath)) {
                        Notification::make()
                            ->title(trans('admin/log.actions.log_not_found'))
                            ->body(trans('admin/log.actions.log_not_found_description', ['filename' => $this->resolveRecordDate()]))
                            ->danger()
                            ->send();

                        return;
                    }

                    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    $totalLines = count($lines);
                    $uploadLines = $totalLines <= 1000 ? $lines : array_slice($lines, -1000);
                    $content = implode("\n", $uploadLines);

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
                        $url = $data['url'];

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
        ];
    }
}
