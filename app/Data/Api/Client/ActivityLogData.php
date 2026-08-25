<?php

namespace App\Data\Api\Client;

use App\Data\Api\ApiResource;
use App\Data\Api\IncludeContext;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

final class ActivityLogData extends ApiResource
{
    /** @var string[] */
    public static array $availableIncludes = ['actor'];

    /**
     * @param  array<string, mixed>  $properties
     */
    public function __construct(
        public string $id,
        public string $event,
        public bool $is_api,
        public ?string $ip,
        public ?string $description,
        public array $properties,
        public bool $has_additional_metadata,
        public string $timestamp,
    ) {}

    public static function getResourceName(): string
    {
        return ActivityLog::RESOURCE_NAME;
    }

    public static function fromModel(ActivityLog $model, Request $request): static
    {
        // Whether the user can view the IP address in the output, either because they are
        // the actor that performed the action or because they are an administrator.
        $canViewIp = $model->actor?->is($request->user()) || $request->user()->can('seeIps activityLog');

        return new self(
            // This is not for security, it is only to provide a unique identifier to
            // the front-end for each entry to improve rendering performance since there
            // is nothing else sufficiently unique to key off at this point.
            id: sha1((string) $model->id),
            event: $model->event,
            is_api: !is_null($model->api_key_id),
            ip: $canViewIp ? $model->ip : null,
            description: $model->description,
            properties: $model->wrapProperties(),
            has_additional_metadata: $model->hasAdditionalMetadata(),
            timestamp: $model->timestamp->toAtomString(),
        );
    }

    public static function includes(): array
    {
        return [
            'actor' => function (ActivityLog $model, IncludeContext $context): array {
                if (!$model->actor instanceof User) {
                    return $context->null();
                }

                return $context->item($model->actor, UserData::class);
            },
        ];
    }
}
