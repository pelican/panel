<?php

namespace App\Http\Controllers\Api\Application\Servers;

use App\Data\Api\Application\ServerData;
use App\Exceptions\DisplayException;
use App\Exceptions\Model\DataValidationException;
use App\Http\Controllers\Api\Application\ApplicationApiController;
use App\Http\Requests\Api\Application\Servers\UpdateServerBuildConfigurationRequest;
use App\Http\Requests\Api\Application\Servers\UpdateServerDetailsRequest;
use App\Models\Server;
use App\Services\Servers\BuildModificationService;
use App\Services\Servers\DetailsModificationService;
use Dedoc\Scramble\Attributes\Group;

#[Group('Server', weight: 2)]
class ServerDetailsController extends ApplicationApiController
{
    /**
     * ServerDetailsController constructor.
     */
    public function __construct(
        private BuildModificationService $buildModificationService,
        private DetailsModificationService $detailsModificationService
    ) {
        parent::__construct();
    }

    /**
     * Update details
     *
     * Update the details for a specific server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DisplayException
     * @throws DataValidationException
     */
    public function details(UpdateServerDetailsRequest $request, Server $server): array
    {
        /** @var array<array-key, mixed> $validated */
        $validated = $request->validated();

        $updated = $this->detailsModificationService->returnUpdatedModel()->handle(
            $server,
            $validated,
        );

        return $this->response->item($updated)
            ->transformWith(ServerData::class)
            ->toArray();
    }

    /**
     * Update build
     *
     * Update the build details for a specific server.
     *
     * @return array<array-key, mixed>
     *
     * @throws DisplayException
     * @throws DataValidationException
     */
    public function build(UpdateServerBuildConfigurationRequest $request, Server $server): array
    {
        $server = $this->buildModificationService->handle($server, $request->validated());

        return $this->response->item($server)
            ->transformWith(ServerData::class)
            ->toArray();
    }
}
