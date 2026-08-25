<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Api\Application\ServerData;
use App\Exceptions\Model\DataValidationException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Servers\UpdateServerStartupRequest;
use App\Models\Server;
use App\Models\User;
use App\Services\Servers\StartupModificationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

#[Group('Server', weight: 3)]
class StartupController extends ApplicationApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(private StartupModificationService $modificationService)
    {
        parent::__construct();
    }

    /**
     * Update startup
     *
     * Update the startup and environment settings for a specific server.
     *
     * @return array<array-key, mixed>
     *
     * @throws ValidationException
     * @throws ConnectionException
     * @throws DataValidationException
     */
    public function index(UpdateServerStartupRequest $request, Server $server): array
    {
        $server = $this->modificationService
            ->setUserLevel(User::USER_LEVEL_ADMIN)
            ->handle($server, $request->validated());

        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->toArray();
    }
}
