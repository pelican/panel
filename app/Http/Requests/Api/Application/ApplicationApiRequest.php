<?php

namespace App\Http\Requests\Api\Application;

use App\Enums\RolePermissionModels;
use App\Exceptions\PanelException;
use App\Models\ApiKey;
use App\Models\Database;
use App\Models\DatabaseHost;
use App\Models\Role;
use App\Services\Acl\Api\AdminAcl;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Laravel\Sanctum\TransientToken;
use Webmozart\Assert\Assert;

abstract class ApplicationApiRequest extends FormRequest
{
    /**
     * The resource that should be checked when performing the authorization
     * function for this request.
     */
    protected ?string $resource;

    /**
     * The permission level that a given API key should have for accessing
     * the defined $resource during the request cycle.
     */
    protected int $permission = AdminAcl::NONE;

    /**
     * API resources whose name differs from the role permission model they map to.
     */
    private const ROLE_PERMISSION_MODELS = [
        Database::RESOURCE_NAME => RolePermissionModels::Database->value,
        DatabaseHost::RESOURCE_NAME => RolePermissionModels::DatabaseHost->value,
    ];

    /**
     * Determine if the current user is authorized to perform
     * the requested action against the API.
     *
     * @throws PanelException
     */
    public function authorize(): bool
    {
        throw_if(is_null($this->resource), new PanelException('An ACL resource must be defined on API requests.'));

        return $this->canAccess($this->resource, $this->permission);
    }

    /**
     * Determine if the current user may access the given resource at the given permission
     * level, either through their API key or, for panel sessions, their role permissions.
     */
    protected function canAccess(string $resource, int $permission): bool
    {
        /** @var TransientToken|ApiKey $token */
        $token = $this->user()->currentAccessToken();

        if ($token instanceof TransientToken) {
            return match ($permission) {
                default => false,
                AdminAcl::READ => $this->hasRolePermissions($resource, ['viewList', 'view']),
                AdminAcl::WRITE => $this->hasRolePermissions($resource, ['update']),
            };
        }

        if (!$token instanceof ApiKey) {
            return false;
        }

        if ($this->user()->isRootAdmin() && $token->key_type === ApiKey::TYPE_ACCOUNT) {
            return true;
        }

        return AdminAcl::check($token, $resource, $permission);
    }

    /**
     * Check the session user holds every given role permission for the resource, skipping
     * prefixes the resource's role model doesn't define (plugins have no "view").
     *
     * @param  string[]  $prefixes
     */
    private function hasRolePermissions(string $resource, array $prefixes): bool
    {
        $model = self::ROLE_PERMISSION_MODELS[$resource] ?? $resource;
        $prefixes = array_intersect($prefixes, Role::getPermissionList()[$model] ?? []);

        return !empty($prefixes) && collect($prefixes)->every(fn (string $prefix) => $this->user()->can("$prefix $model"));
    }

    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return [];
    }

    /**
     * Helper method allowing a developer to easily hook into this logic without having
     * to remember what the method name is called or where to use it. By default this is
     * a no-op.
     */
    public function withValidator(Validator $validator): void
    {
        // do nothing
    }

    /**
     * Returns the named route parameter and asserts that it is a real model that
     * exists in the database.
     *
     * @template T of \Illuminate\Database\Eloquent\Model
     *
     * @param  class-string<T>  $expect
     * @return T
     *
     * @noinspection PhpDocSignatureInspection
     */
    public function parameter(string $key, string $expect)
    {
        $value = $this->route()->parameter($key);

        Assert::isInstanceOf($value, $expect);
        Assert::isInstanceOf($value, Model::class);
        Assert::true($value->exists);

        /* @var T $value */
        return $value;
    }
}
