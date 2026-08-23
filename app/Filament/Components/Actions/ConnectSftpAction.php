<?php

namespace App\Filament\Components\Actions;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Models\Server;
use Filament\Actions\Action;
use Filament\Facades\Filament;

class ConnectSftpAction extends Action
{
    protected ?string $username = null;

    protected ?string $directory = null;

    public static function getDefaultName(): ?string
    {
        return 'connect_sftp';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(trans('server/setting.server_info.sftp.action'));

        $this->authorize(function (?Server $server) {
            /** @var ?Server $server */
            $server ??= Filament::getTenant();

            return !is_null($server) && user()?->can(SubuserPermission::FileSftp, $server);
        });

        $this->color('success');

        $this->icon(TablerIcon::Plug);

        $this->url(function (?Server $server) {
            /** @var ?Server $server */
            $server ??= Filament::getTenant();

            if (is_null($server)) {
                return null;
            }

            /** @var Server $server */
            return $server->getSftpUrl($this->getUsername(), $this->getDirectory());
        });
    }

    public function username(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function directory(?string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function getDirectory(): ?string
    {
        return $this->directory;
    }
}
