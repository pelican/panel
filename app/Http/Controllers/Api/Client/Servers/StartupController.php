<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Data\Api\Client\EggVariableData;
use App\Exceptions\Model\DataValidationException;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\Startup\GetStartupRequest;
use App\Http\Requests\Api\Client\Servers\Startup\UpdateStartupVariableRequest;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Services\Servers\StartupCommandService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[Group('Server - Startup')]
class StartupController extends ClientApiController
{
    /**
     * StartupController constructor.
     */
    public function __construct(
        private StartupCommandService $startupCommandService,
    ) {
        parent::__construct();
    }

    /**
     * List startup variables
     *
     * Returns the startup information for the server including all the variables.
     *
     * @return array<array-key, mixed>
     */
    public function index(GetStartupRequest $request, Server $server): array
    {
        $startup = $this->startupCommandService->handle($server);

        return $this->response->collection(
            $server->variables()->where('user_viewable', true)->orderBy('sort')->get()
        )
            ->transformWith(EggVariableData::class)
            ->addMeta([
                'startup_command' => $startup,
                'docker_images' => $server->egg->docker_images,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }

    /**
     * Update startup variable
     *
     * Updates a single variable for a server.
     *
     * @return array<array-key, mixed>
     *
     * @throws ValidationException
     * @throws DataValidationException
     */
    public function update(UpdateStartupVariableRequest $request, Server $server): array
    {
        $variable = $server->variables()->where('env_variable', $request->input('key'))->first();

        throw_if(!$variable || !$variable->user_viewable, new BadRequestHttpException('The environment variable you are trying to edit does not exist.'));

        throw_unless($variable->user_editable, new BadRequestHttpException('The environment variable you are trying to edit is read-only.'));

        $original = $variable->server_value;

        // Revalidate the variable value using the egg variable specific validation rules for it.
        $request->validate(['value' => $variable->rules]);

        ServerVariable::query()->updateOrCreate([
            'server_id' => $server->id,
            'variable_id' => $variable->id,
        ], [
            'variable_value' => $request->input('value') ?? '',
        ]);

        $variable = $variable->refresh();
        $variable->server_value = $request->input('value');

        $startup = $this->startupCommandService->handle($server);

        if ($original !== $request->input('value')) {
            Activity::event('server:startup.edit')
                ->subject($variable)
                ->property([
                    'variable' => $variable->env_variable,
                    'old' => $original,
                    'new' => $request->input('value') ?? '',
                ])
                ->log();
        }

        return $this->response->item($variable)
            ->transformWith(EggVariableData::class)
            ->addMeta([
                'startup_command' => $startup,
                'raw_startup_command' => $server->startup,
            ])
            ->toArray();
    }
}
