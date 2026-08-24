<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Enums\ServerState;
use App\Enums\SubuserPermission;
use App\Models\Server;
use App\Services\Servers\StartupCommandService;
use Illuminate\Http\Request;
use Spatie\LaravelData\Optional;

final class ServerData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['egg', 'subusers'];

    /** @var string[] */
    public static array $defaultIncludes = ['allocations', 'variables'];

    /**
     * @param  array{ip: string, alias: string|null, port: int}  $sftp_details
     * @param  array<string, mixed>  $limits
     * @param  string[]|null  $egg_features
     * @param  array<string, int|null>  $feature_limits
     */
    public function __construct(
        public bool $server_owner,
        public string $identifier,
        public int $internal_id,
        public string $uuid,
        public string $name,
        public string $node,
        public bool $is_node_under_maintenance,
        public array $sftp_details,
        public string|null|Optional $description,
        public array $limits,
        public string $invocation,
        public string $docker_image,
        public ?array $egg_features,
        public array $feature_limits,
        public ?ServerState $status,
        public bool $is_suspended,
        public bool $is_installing,
        public bool $is_transferring,
    ) {}

    public static function getResourceName(): string
    {
        return Server::RESOURCE_NAME;
    }

    public static function fromModel(Server $model, StartupCommandService $service, Request $request): static
    {
        $user = $request->user();

        return new self(
            server_owner: $user->id === $model->owner_id,
            identifier: $model->uuid_short,
            internal_id: $model->id,
            uuid: $model->uuid,
            name: $model->name,
            node: $model->node->name,
            is_node_under_maintenance: $model->node->isUnderMaintenance(),
            sftp_details: [
                'ip' => $model->node->fqdn,
                'alias' => $model->node->daemon_sftp_alias,
                'port' => $model->node->daemon_sftp,
            ],
            description: config('panel.editable_server_descriptions') ? $model->description : Optional::create(),
            limits: [
                'memory' => $model->memory,
                'swap' => $model->swap,
                'disk' => $model->disk,
                'io' => $model->io,
                'cpu' => $model->cpu,
                'threads' => $model->threads,
                // This field is deprecated, please use "oom_killer".
                'oom_disabled' => !$model->oom_killer,
                'oom_killer' => $model->oom_killer,
            ],
            invocation: $service->handle($model, hideAllValues: !$user->can(SubuserPermission::StartupRead, $model)),
            docker_image: $model->image,
            egg_features: $model->egg->inherit_features,
            feature_limits: [
                'databases' => $model->database_limit,
                'allocations' => $model->allocation_limit,
                'backups' => $model->backup_limit,
            ],
            status: $model->status,
            // This field is deprecated, please use "status".
            is_suspended: $model->isSuspended(),
            // This field is deprecated, please use "status".
            is_installing: !$model->isInstalled(),
            is_transferring: !is_null($model->transfer),
        );
    }

    public static function includes(): array
    {
        return [
            // If the user doesn't have read permissions for the allocations we only return
            // the primary server allocation, and any notes associated with it are hidden.
            // This avoids permission regression without hiding information the frontend
            // needs to make sense when browsing or searching results.
            'allocations' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsClient(SubuserPermission::AllocationRead->value, $server)) {
                    $primary = clone $server->allocation;
                    $primary->notes = null;

                    return $context->collection([$primary], AllocationData::class);
                }

                return $context->collection($server->allocations, AllocationData::class);
            },
            'variables' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsClient(SubuserPermission::StartupRead->value, $server)) {
                    return $context->null();
                }

                return $context->collection($server->variables->where('user_viewable', true), EggVariableData::class);
            },
            'egg' => function (Server $server, IncludeContext $context): array {
                return $context->item($server->egg, EggData::class);
            },
            'subusers' => function (Server $server, IncludeContext $context): array {
                if (!$context->allowsClient(SubuserPermission::UserRead->value, $server)) {
                    return $context->null();
                }

                return $context->collection($server->subusers, SubuserData::class);
            },
        ];
    }
}
