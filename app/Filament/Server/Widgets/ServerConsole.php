<?php

namespace App\Filament\Server\Widgets;

use App\Enums\NodeJwtScope;
use App\Enums\SubuserPermission;
use App\Exceptions\Http\HttpForbiddenException;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Models\User;
use App\Services\Nodes\NodeJWTService;
use App\Services\Servers\GetUserPermissionsService;
use Filament\Actions\Action;
use Filament\Widgets\Widget;
use Illuminate\Support\Arr;
use Livewire\Attributes\On;
use Livewire\Attributes\Session;

class ServerConsole extends Widget
{
    protected string $view = 'filament.components.server-console';

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    public ?Server $server = null;

    public ?User $user = null;

    /** @var string[] */
    #[Session(key: 'server.{server.id}.history')]
    public array $history = [];

    public int $historyIndex = 0;

    public string $input = '';

    private GetUserPermissionsService $getUserPermissionsService;

    private NodeJWTService $nodeJWTService;

    public function boot(GetUserPermissionsService $getUserPermissionsService, NodeJWTService $nodeJWTService): void
    {
        $this->getUserPermissionsService = $getUserPermissionsService;
        $this->nodeJWTService = $nodeJWTService;
    }

    protected function getToken(): string
    {
        throw_if(!$this->user || !$this->server || $this->user->cannot(SubuserPermission::WebsocketConnect, $this->server), new HttpForbiddenException('You do not have permission to connect to this server\'s websocket.'));

        $permissions = $this->getUserPermissionsService->handle($this->server, $this->user);

        return $this->nodeJWTService
            ->setExpiresAt(now()->addMinutes(10)->toImmutable())
            ->setScopes(NodeJwtScope::Websocket)
            ->setUser($this->user)
            ->setClaims([
                'server_uuid' => $this->server->uuid,
                'permissions' => $permissions,
            ])
            ->handle($this->server->node, $this->user->id . $this->server->uuid)->toString();
    }

    protected function getSocket(): string
    {
        $socket = str_replace(['https://', 'http://'], ['wss://', 'ws://'], $this->server->node->getConnectionAddress());
        $socket .= sprintf('/api/servers/%s/ws', $this->server->uuid);

        return $socket;
    }

    protected function authorizeSendCommand(): bool
    {
        return $this->user->can(SubuserPermission::ControlConsole, $this->server);
    }

    protected function canSendCommand(): bool
    {
        return $this->authorizeSendCommand() && !$this->server->isInConflictState() && $this->server->retrieveStatus()->isStartingOrRunning();
    }

    public function up(): void
    {
        $this->historyIndex = min($this->historyIndex + 1, count($this->history) - 1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function down(): void
    {
        $this->historyIndex = max($this->historyIndex - 1, -1);

        $this->input = $this->history[$this->historyIndex] ?? '';
    }

    public function enter(): void
    {
        if (!empty($this->input) && $this->canSendCommand()) {
            $this->dispatch('sendServerCommand', command: $this->input);

            $this->history = Arr::prepend($this->history, $this->input);
            $this->historyIndex = -1;

            $this->input = '';
        }
    }

    #[On('token-request')]
    public function tokenRequest(): void
    {
        $this->dispatch('sendAuthRequest', token: $this->getToken());
    }

    #[On('store-stats')]
    public function storeStats(string $data): void
    {
        $data = json_decode($data);

        $timestamp = now()->getTimestamp();

        foreach ($data as $key => $value) {
            $cacheKey = "servers.{$this->server->id}.$key";
            $cachedStats = cache()->get($cacheKey, []);

            $cachedStats[$timestamp] = $value;

            cache()->put($cacheKey, array_slice($cachedStats, -120), now()->addMinute());
        }
    }

    #[On('websocket-error')]
    public function websocketError(): void
    {
        AlertBanner::make('websocket_error')
            ->title(trans('server/console.websocket_error.title'))
            ->body(trans('server/console.websocket_error.body'))
            ->actions(fn () => user()->can('update', $this->server->node) ? [
                Action::make('exclude_troubleshooting')
                    ->label('Open troubleshooting guide')
                    ->color('gray')
                    ->url('https://pelican.dev/docs/troubleshooting/#wings-connection-issues', true),
            ] : [])
            ->danger()
            ->send();
    }
}
