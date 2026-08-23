<?php

namespace App\Filament\Components\Actions;

use App\Enums\TablerIcon;
use App\Filament\Server\Pages\Console;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;

class ViewConsoleAction extends Action
{
    protected ?Server $server = null;

    public static function getDefaultName(): ?string
    {
        return 'view_console';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->tooltip(trans('admin/server.view_console'));

        $this->icon(TablerIcon::Terminal);

        $this->url(fn (?Server $server) => Console::getUrl(panel: 'server', tenant: $this->getServer() ?? $server));

        $this->authorize(fn (?Server $server) => user()?->canAccessTenant($this->getServer() ?? $server));
    }

    public function server(?Server $server): static
    {
        $this->server = $server;

        return $this;
    }

    public function getServer(): ?Server
    {
        /** @var ?Server $tenant */
        $tenant = Filament::getTenant();

        return $this->server ?? $tenant;
    }
}
