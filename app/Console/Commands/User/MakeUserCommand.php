<?php

namespace App\Console\Commands\User;

use App\Exceptions\Model\DataValidationException;
use App\Services\Users\UserCreationService;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MakeUserCommand extends Command
{
    protected $description = 'Creates a user on the system via the CLI.';

    protected $signature = 'p:user:make {--email=} {--username=} {--password=} {--admin=} {--no-password}';

    /**
     * Handle command request to create a new user.
     *
     * @throws Exception
     * @throws DataValidationException
     */
    public function handle(): int
    {
        try {
            DB::connection()->getPdo();
        } catch (Exception $exception) {
            $this->error($exception->getMessage());

            return 1;
        }

        /** @var UserCreationService $creationService */
        $creationService = $this->getLaravel()->make(UserCreationService::class);

        $root_admin = $this->option('admin') ?? $this->confirm(trans('command/messages.user.ask_admin'));
        $email = $this->option('email') ?? $this->ask(trans('command/messages.user.ask_email'));
        $username = $this->option('username') ?? $this->ask(trans('command/messages.user.ask_username'));

        if (is_null($password = $this->option('password')) && !$this->option('no-password')) {
            $this->warn(trans('command/messages.user.ask_password_help'));
            $this->line(trans('command/messages.user.ask_password_tip'));
            $password = $this->secret(trans('command/messages.user.ask_password'));
        }

        $user = $creationService->handle(compact('email', 'username', 'password', 'root_admin'));
        $this->table(['Field', 'Value'], [
            ['UUID', $user->uuid],
            ['Email', $user->email],
            ['Username', $user->username],
            ['Admin', $user->isRootAdmin() ? 'Yes' : 'No'],
        ]);

        return 0;
    }
}
