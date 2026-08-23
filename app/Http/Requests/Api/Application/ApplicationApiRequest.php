<?php

namespace App\Http\Requests\Api\Application;

use App\Exceptions\PanelException;
use App\Models\ApiKey;
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
     * Determine if the current user is authorized to perform
     * the requested action against the API.
     *
     * @throws PanelException
     */
    public function authorize(): bool
    {
        throw_if(is_null($this->resource), new PanelException('An ACL resource must be defined on API requests.'));

        /** @var TransientToken|ApiKey $token */
        $token = $this->user()->currentAccessToken();

        if ($token instanceof TransientToken) {
            return match ($this->permission) {
                default => false,
                AdminAcl::READ => $this->user()->can('viewList ' . $this->resource) && $this->user()->can('view ' . $this->resource),
                AdminAcl::WRITE => $this->user()->can('update ' . $this->resource),
            };
        }

        if ($this->user()->isRootAdmin() && $token->key_type === ApiKey::TYPE_ACCOUNT) {
            return true;
        }

        return AdminAcl::check($token, $this->resource, $this->permission);
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
        Assert::isInstanceOf($value, Model::class); // @phpstan-ignore staticMethod.alreadyNarrowedType
        Assert::true($value->exists);

        /* @var T $value */
        return $value;
    }
}
