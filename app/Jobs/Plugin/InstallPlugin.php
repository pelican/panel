<?php

namespace App\Jobs\Plugin;

use App\Filament\Admin\Resources\Plugins\Pages\ListPlugins;
use App\Models\Plugin;
use App\Models\User;
use App\Services\Helpers\PluginService;
use Exception;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InstallPlugin implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user, public string $pluginId) {}

    public function handle(PluginService $pluginService): void
    {
        try {
            Plugin::refreshRows();
            $plugin = Plugin::findOrFail($this->pluginId);

            $pluginService->installPlugin($plugin);

            Notification::make()
                ->success()
                ->title(trans('admin/plugin.notifications.installed'))
                ->body($plugin->name)
                ->actions([
                    Action::make('goto_plugins')
                        ->label(trans('admin/plugin.notifications.goto_plugins'))
                        ->url(ListPlugins::getUrl(panel: 'admin')),
                ])
                ->sendToDatabase($this->user);
        } catch (Exception $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(trans('admin/plugin.notifications.install_error'))
                ->body($exception->getMessage())
                ->sendToDatabase($this->user);
        }
    }

    public function uniqueId(): string
    {
        return 'plugin:install:' . $this->pluginId;
    }
}
