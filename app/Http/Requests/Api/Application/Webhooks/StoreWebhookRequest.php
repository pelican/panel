<?php

namespace App\Http\Requests\Api\Application\Webhooks;

use App\Enums\WebhookScope;
use App\Facades\WebhookTypes;
use App\Http\Requests\Api\Application\ApplicationApiRequest;
use App\Models\WebhookConfiguration;
use App\Services\Acl\Api\AdminAcl;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWebhookRequest extends ApplicationApiRequest
{
    protected ?string $resource = WebhookConfiguration::RESOURCE_NAME;

    protected int $permission = AdminAcl::WRITE;

    /** @return array<string, string|array<string|\Stringable|ValidationRule>> */
    public function rules(): array
    {
        return array_merge($this->baseRules(), $this->payloadRules());
    }

    /**
     * Rules the selected type declares for its own payload, namespaced under `payload`.
     *
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        $schema = WebhookTypes::get($this->resolveType());

        if (!$schema) {
            return [];
        }

        $rules = [];
        foreach ($schema->getPayloadRules() as $key => $rule) {
            $rules["payload.$key"] = $rule;
        }

        return $rules;
    }

    protected function resolveType(): ?string
    {
        if ($type = $this->scalarInput('type')) {
            return $type;
        }

        return WebhookTypes::detect($this->scalarInput('endpoint'));
    }

    /**
     * Request input is attacker controlled, so anything that is not a plain scalar is
     * treated as absent here and left for the normal rules to reject with a 422.
     */
    protected function scalarInput(string $key): ?string
    {
        $value = $this->input($key);

        return is_scalar($value) && filled($value) ? (string) $value : null;
    }

    /** @return array<string, string|array<string|\Stringable|ValidationRule|Closure>> */
    protected function baseRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'description' => ['nullable', 'string', 'max:191'],
            'endpoint' => ['required', 'string', 'url', 'max:191'],
            // Types are registered at runtime, so the allowed values depend on which plugins are loaded
            'type' => ['sometimes', 'string', Rule::in(array_keys(WebhookTypes::getOptions()))],
            'scope' => ['sometimes', Rule::enum(WebhookScope::class)],
            'server_id' => ['nullable', 'integer', 'exists:servers,id'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['required', 'string'],
            'payload' => ['nullable', 'array'],
            // Header names are restricted to RFC 7230 token characters plus space, which
            // normalizes to a dash on save, so CRLF injection is rejected at the boundary
            'headers' => ['nullable', 'array', function (string $attribute, mixed $value, Closure $fail) {
                foreach (array_keys((array) $value) as $name) {
                    if (!preg_match('/\A[A-Za-z0-9 !#$%&\'*+.^_`|~-]+\z/', (string) $name)) {
                        $fail("The $attribute field contains an invalid header name.");
                    }
                }
            }],
            'headers.*' => ['string', 'regex:/\A[^\r\n]*\z/'],
        ];
    }

    /**
     * A server scoped webhook needs a server, and the events it may listen to differ
     * from the global ones, so both are checked against the resolved scope.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $scope = $this->resolveScope();

            if ($scope === WebhookScope::Server && !$this->hasServer()) {
                $validator->errors()->add('server_id', trans('validation.required', ['attribute' => 'server id']));
            }

            if ($scope === WebhookScope::Global && $this->filled('server_id')) {
                $validator->errors()->add('server_id', trans('validation.prohibited', ['attribute' => 'server id']));
            }

            $allowed = array_keys(WebhookConfiguration::filamentCheckboxList($scope));

            foreach ((array) $this->input('events', []) as $index => $event) {
                if (!in_array($event, $allowed, true)) {
                    $validator->errors()->add("events.$index", trans('validation.in', ['attribute' => "events.$index"]));
                }
            }
        });
    }

    protected function resolveScope(): WebhookScope
    {
        if ($scope = $this->scalarInput('scope')) {
            return WebhookScope::tryFrom($scope) ?? WebhookScope::Global;
        }

        return $this->hasServer() ? WebhookScope::Server : WebhookScope::Global;
    }

    protected function hasServer(): bool
    {
        return $this->filled('server_id');
    }

    /**
     * Attributes to persist. The resolved type's schema normalizes them the same way the
     * panel forms do before saving, header keys rewritten space to dash for example.
     *
     * @return array<string, mixed>
     */
    public function resolvedAttributes(): array
    {
        $data = $this->withDefaults($this->validated());

        if ($schema = WebhookTypes::get($data['type'] ?? $this->resolveType())) {
            $data = $schema->mutateFormDataBeforeSave($data);
        }

        return $data;
    }

    /**
     * Fills in the scope and type the panel would otherwise derive from the form.
     * Overridden on update to keep scope and type consistent with the stored record.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withDefaults(array $data): array
    {
        $data['scope'] ??= $this->resolveScope();
        $data['type'] ??= $this->resolveType();

        return $data;
    }
}
