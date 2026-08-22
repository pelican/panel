<?php

namespace App\Livewire;

use App\Enums\SubuserPermission;
use App\Exceptions\Repository\FileExistsException;
use App\Facades\Activity;
use App\Models\Server;
use App\Repositories\Daemon\DaemonFileRepository;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Client\ConnectionException;
use Livewire\Component;

class FileUploadManager extends Component
{
    public function getUploadSizeLimit(string $serverUuid): int
    {
        $server = $this->resolveServer($serverUuid);

        return $server->node->upload_size * 1024 * 1024;
    }

    /**
     * @throws ConnectionException
     */
    public function createFolder(string $serverUuid, string $folderPath, string $basePath): void
    {
        $server = $this->resolveServer($serverUuid);

        try {
            (new DaemonFileRepository())->setServer($server)->createDirectory($folderPath, $basePath);

            Activity::event('server:file.create-directory')
                ->subject($server)
                ->property(['directory' => $basePath, 'name' => $folderPath])
                ->log();
        } catch (FileExistsException) {
            // Ignore if the folder already exists.
        } catch (ConnectionException $e) {
            Notification::make()
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    /**
     * @param  string[]  $files
     */
    public function logUploadedFiles(string $serverUuid, array $files, string $directory): void
    {
        $server = $this->resolveServer($serverUuid);

        Activity::event('server:files.uploaded')
            ->subject($server)
            ->property('directory', $directory)
            ->property('files', collect($files))
            ->log();
    }

    private function resolveServer(string $serverUuid): Server
    {
        $server = Server::query()->where('uuid', $serverUuid)->first();

        abort_if(is_null($server), 404);

        abort_unless(user()?->can(SubuserPermission::FileCreate, $server), 403, 'You do not have permission to upload files.');

        return $server;
    }

    public function render(): View
    {
        return view('livewire.file-upload-manager');
    }
}
