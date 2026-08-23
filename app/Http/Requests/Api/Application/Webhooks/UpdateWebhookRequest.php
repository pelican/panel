<?php

namespace App\Http\Requests\Api\Application\Webhooks;

use App\Enums\WebhookScope;
use App\Models\WebhookConfiguration;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateWebhookRequest extends StoreWebhookRequest
{
    /**
     * A PATCH only sends what it changes, so the fields it omits must not be required.
     *
     * @return array<string, string|array<string|\Stringable|ValidationRule>>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'endpoint', 'events'] as $field) {
            $rules[$field] = array_values(array_map(
                fn ($rule) => $rule === 'required' ? 'sometimes' : $rule,
                $rules[$field]
            ));
        }

        return $rules;
    }

    /**
     * A PATCH is validated against the configuration it would produce, not just the
     * fields it sends. Changing the type or the scope pulls the values the request keeps
     * into validation, so a webhook can never be saved in a state its own rules reject.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->has('payload') && $this->resolveType() !== $this->record()->type) {
            $this->merge(['payload' => $this->record()->payload ?? []]);
        }

        if (!$this->has('events') && $this->resolveScope() !== $this->record()->scope) {
            $this->merge(['events' => $this->record()->events ?? []]);
        }
    }

    /**
     * A type may require fields inside its payload, but that must not block an update
     * that only changes another attribute, so the rules apply only when a payload is sent.
     * prepareForValidation() supplies the stored payload when the type itself changes.
     *
     * @return array<string, mixed>
     */
    protected function payloadRules(): array
    {
        return $this->has('payload') ? parent::payloadRules() : [];
    }

    /**
     * Assigning a server implies the server scope, but clearing one does not imply the
     * global scope: the stored scope is kept so the request is rejected rather than
     * silently leaving a server scoped webhook with no server and unusable events.
     */
    protected function resolveScope(): WebhookScope
    {
        if ($scope = $this->scalarInput('scope')) {
            return WebhookScope::tryFrom($scope) ?? WebhookScope::Global;
        }

        if ($this->filled('server_id')) {
            return WebhookScope::Server;
        }

        return $this->record()->scope;
    }

    /**
     * An explicit `server_id: null` is a deliberate unassign, so it must not silently
     * fall back to the server already stored on the record.
     */
    protected function hasServer(): bool
    {
        if ($this->has('server_id')) {
            return parent::hasServer();
        }

        return $this->record()->server_id !== null;
    }

    /**
     * A stored row's type is always deliberate, including the default, so an update never
     * re-detects it from the endpoint. Only an explicit `type` changes it.
     */
    protected function resolveType(): ?string
    {
        return $this->scalarInput('type') ?? $this->record()->type;
    }

    /**
     * Scope and type are validated against values the request only implies, so those same
     * values are persisted, otherwise a record could be saved in a state its own
     * validation would reject.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function withDefaults(array $data): array
    {
        if (array_key_exists('server_id', $data) && !array_key_exists('scope', $data)) {
            $data['scope'] = $this->resolveScope();
        }

        // A webhook that ends up global has no server, whatever the record stored before
        if ($this->resolveScope() === WebhookScope::Global) {
            $data['server_id'] = null;
        }

        return $data;
    }

    protected function record(): WebhookConfiguration
    {
        return $this->parameter('webhook', WebhookConfiguration::class);
    }
}
