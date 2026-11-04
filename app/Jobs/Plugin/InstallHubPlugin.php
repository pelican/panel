<?php

namespace App\Jobs\Plugin;

use App\Models\Plugin;
use App\Models\User;
use App\Services\Helpers\PluginHubService;
use App\Services\Helpers\PluginService;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Downloads a free plugin from the Pelican Hub in the background, then queues its install.
 */
class InstallHubPlugin implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public User $user, public string $slug) {}

    public function handle(PluginHubService $hub, PluginService $pluginService): void
    {
        try {
            Plugin::refreshRows();

            $wasInstalled = Plugin::whereKey($this->slug)->exists() && $pluginService->isInstalled($this->slug);

            $id = $pluginService->downloadPluginFromHub($this->slug, $hub);

            Plugin::refreshRows();

            InstallPlugin::dispatch($this->user, $id, reinstall: $wasInstalled);
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->danger()
                ->title(trans('admin/plugin.notifications.install_error'))
                ->body("$this->slug: {$exception->getMessage()}")
                ->sendToDatabase($this->user);
        }
    }
}
