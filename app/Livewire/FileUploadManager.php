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

/**
 * Mounted once per server panel page and persisted across SPA navigation, so uploads keep
 * running while the user browses elsewhere. The queue and the uploads themselves live in
 * resources/js/file-uploader.js; this only exposes what has to happen on the server.
 */
class FileUploadManager extends Component
{
    public function getUploadSizeLimit(string $serverUuid): int
    {
        return $this->resolveServer($serverUuid)->node->upload_size * 1024 * 1024;
    }

    /**
     * Wings creates missing parent directories when writing a file, so this is only called for
     * directories that would otherwise be lost: the empty ones in a dragged folder tree.
     *
     * @throws ConnectionException
     */
    public function createFolder(string $serverUuid, string $folderPath, string $basePath): void
    {
        $server = $this->resolveServer($serverUuid);

        $this->assertPathIsSafe($folderPath);
        $this->assertPathIsSafe($basePath);

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

    /**
     * Wings jails paths to the server root, but this component takes arbitrary strings from the
     * browser, so refuse the obvious escapes before they get that far.
     */
    private function assertPathIsSafe(string $path): void
    {
        if (!str_contains($path, "\0") && !in_array('..', explode('/', $path), true)) {
            return;
        }

        abort(422, 'Invalid path.');
    }

    public function render(): View
    {
        return view('livewire.file-upload-manager');
    }
}
