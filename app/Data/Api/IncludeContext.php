<?php

namespace App\Data\Api;

use App\Models\ApiKey;
use App\Models\Server;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Http\Request;

/**
 * Carries the request, the parsed include paths, and the current position in the
 * include tree while the envelope renders a resource. Include resolvers use it to
 * authorize and to build nested envelope fragments.
 */
class IncludeContext
{
    /**
     * @param  string[]  $requestedIncludes  dot paths, already trimmed to the recursion limit with parents expanded
     * @param  string[]  $path  the include names leading to the current scope
     */
    public function __construct(
        public readonly Request $request,
        public readonly array $requestedIncludes = [],
        public readonly array $path = [],
    ) {}

    public function user(): ?User
    {
        return $this->request->user();
    }

    /**
     * Whether the API key on the request may touch the given resource, mirroring
     * what BaseTransformer::authorize did for application API includes.
     */
    public function allowsAdmin(string $resource): bool
    {
        $token = $this->user()?->currentAccessToken();
        if (!$token instanceof ApiKey || !in_array($token->key_type, [ApiKey::TYPE_ACCOUNT, ApiKey::TYPE_APPLICATION])) {
            return false;
        }

        if ($token->key_type === ApiKey::TYPE_ACCOUNT) {
            return $this->user()->isRootAdmin();
        }

        return AdminAcl::check($token, $resource);
    }

    /**
     * Whether the requesting user holds the given ability on the server, mirroring
     * BaseClientTransformer::authorize for client API includes.
     */
    public function allowsClient(string $ability, ?Server $server = null): bool
    {
        return (bool) $this->user()?->can($ability, [$server]);
    }

    public function isRequested(string $include): bool
    {
        return in_array(implode('.', [...$this->path, $include]), $this->requestedIncludes, true);
    }

    public function descend(string $include): self
    {
        return new self($this->request, $this->requestedIncludes, [...$this->path, $include]);
    }

    /**
     * @param  class-string<ApiResource>  $dataClass
     * @return array<mixed>
     */
    public function item(mixed $model, string $dataClass, ?string $resourceKey = null): array
    {
        return Envelope::item($model, $dataClass, $this, $resourceKey);
    }

    /**
     * @param  iterable<mixed>  $models
     * @param  class-string<ApiResource>  $dataClass
     * @return array<mixed>
     */
    public function collection(iterable $models, string $dataClass, ?string $resourceKey = null): array
    {
        return Envelope::collection($models, $dataClass, $this, $resourceKey);
    }

    /**
     * Wrap already transformed attributes, for includes that have no backing model
     * like the database password.
     *
     * @param  array<mixed>  $attributes
     * @return array<mixed>
     */
    public function rawItem(array $attributes, string $resourceKey): array
    {
        return [
            'object' => $resourceKey,
            'attributes' => $attributes,
        ];
    }

    /**
     * @return array{object: string, attributes: null}
     */
    public function null(): array
    {
        return [
            'object' => 'null_resource',
            'attributes' => null,
        ];
    }
}
