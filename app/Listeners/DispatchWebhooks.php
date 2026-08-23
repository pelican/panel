<?php

namespace App\Listeners;

use App\Enums\WebhookScope;
use App\Events\ActivityLogged;
use App\Models\Server;
use App\Models\User;
use App\Models\WebhookConfiguration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class DispatchWebhooks
{
    /** @param array<mixed>|string|null $action */
    public function handle(mixed $event, array|string|null $action = null): void
    {
        if (is_string($event) && is_array($action)) {
            if (str_starts_with($event, 'eloquent.')) {
                $this->handleEloquentEvent($action[0], str($event)->between('eloquent.', ':'));

                return;
            }

            if ($event !== ActivityLogged::class) {
                $this->handleGenericClassEvent($event, $action);
            }

            return;
        }

        if ($event instanceof ActivityLogged) {
            $this->handleActivityLogged($event);
            $this->handleGlobalWebhooks($event);
        }
    }

    protected function handleEloquentEvent(Model $model, string $action): void
    {
        $modelClass = $model::class;
        $eventName = "eloquent.$action: $modelClass";

        $webhooks = WebhookConfiguration::query()
            ->where('scope', WebhookScope::Global)
            ->whereJsonContains('events', $eventName)
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $webhookData = [
            'event' => $eventName,
            'data' => $model->toArray(),
            'timestamp' => now()->toIso8601String(),
        ];

        if (!$this->hasPayloadContent($webhookData)) {
            return;
        }

        /** @var WebhookConfiguration $webhookConfig */
        foreach ($webhooks as $webhookConfig) {
            $webhookConfig->run($eventName, $webhookData);
        }
    }

    /** @param array<mixed> $payload */
    protected function handleGenericClassEvent(string $eventName, array $payload): void
    {
        if (!$this->eventIsWatched($eventName)) {
            return;
        }

        $matchingHooks = cache()->rememberForever("webhooks.$eventName", function () use ($eventName) {
            return WebhookConfiguration::query()
                ->where('scope', WebhookScope::Global)
                ->whereJsonContains('events', $eventName)
                ->get();
        });

        if ($matchingHooks->isEmpty()) {
            return;
        }

        $obj = $payload[0] ?? null;
        $webhookData = ['event' => $eventName, 'timestamp' => now()->toIso8601String()];
        if (is_object($obj)) {
            $webhookData['data'] = $obj->toArray();
        } elseif (is_array($obj)) {
            $webhookData['data'] = $obj;
        }

        foreach ($matchingHooks as $webhookConfig) {
            $webhookConfig->run($eventName, $webhookData);
        }
    }

    protected function handleActivityLogged(ActivityLogged $activityLogged): void
    {
        $eventName = $activityLogged->model->event;

        if ($eventName === null) {
            return;
        }

        if (!$activityLogged->isServerEvent()) {
            return;
        }

        $server = null;
        $morphClass = (new Server())->getMorphClass();
        foreach ($activityLogged->model->subjects as $subject) {
            if ($subject->subject_type === $morphClass && $subject->subject instanceof Server) {
                $server = $subject->subject;
                break;
            }
        }
        if (!$server && isset($activityLogged->model->properties['server'])) {
            $server = Server::find($activityLogged->model->properties['server']['id'] ?? null);
        }

        if (!$server) {
            return;
        }

        $webhooks = $server->webhookConfigurations()
            ->whereJsonContains('events', $eventName)
            ->get();

        if ($webhooks->isEmpty()) {
            return;
        }

        $webhookData = $this->buildActivityPayload($activityLogged);

        if (!$this->hasPayloadContent($webhookData)) {
            return;
        }

        foreach ($webhooks as $webhookConfig) {
            $webhookConfig->run($eventName, $webhookData);
        }
    }

    /** @return array<string, mixed> */
    protected function buildActivityPayload(ActivityLogged $activityLogged): array
    {
        $webhookData = [
            'event' => $activityLogged->model->event,
            'description' => $activityLogged->model->description,
            'ip' => $activityLogged->model->ip,
            'timestamp' => $activityLogged->model->timestamp->toIso8601String(),
        ];

        if ($activityLogged->model->actor_id) {
            $actor = $activityLogged->model->actor;
            $webhookData['actor'] = [
                'id' => $activityLogged->model->actor_id,
                'type' => $activityLogged->model->actor_type,
                'username' => $actor instanceof User ? $actor->username : null,
            ];
        }

        if ($activityLogged->model->properties->isNotEmpty()) {
            $webhookData['properties'] = $activityLogged->model->properties->toArray();
        }

        if ($activityLogged->model->subjects->isNotEmpty()) {
            $webhookData['subjects'] = $activityLogged->model->subjects->map(fn ($subject) => [
                'id' => $subject->subject_id,
                'type' => $subject->subject_type,
            ])->toArray();
        }

        return $webhookData;
    }

    protected function handleGlobalWebhooks(ActivityLogged $activityLogged): void
    {
        $eventName = $activityLogged->model->event;
        $activityLoggedClass = ActivityLogged::class;

        if ($eventName === null) {
            return;
        }

        $matchingHooks = collect();

        if ($this->eventIsWatched($eventName)) {
            $matchingHooks = $matchingHooks->merge(
                cache()->rememberForever("webhooks.$eventName", fn () => WebhookConfiguration::query()
                    ->where('scope', WebhookScope::Global)
                    ->whereJsonContains('events', $eventName)
                    ->get())
            );
        }

        $matchingHooks = $matchingHooks->merge(
            cache()->rememberForever("webhooks.$activityLoggedClass", fn () => WebhookConfiguration::query()
                ->where('scope', WebhookScope::Global)
                ->whereJsonContains('events', $activityLoggedClass)
                ->get())
        )->unique('id');

        if ($matchingHooks->isEmpty()) {
            return;
        }

        $webhookData = $this->buildActivityPayload($activityLogged);

        if (!$this->hasPayloadContent($webhookData)) {
            return;
        }

        foreach ($matchingHooks as $webhookConfig) {
            $webhookConfig->run($eventName, $webhookData);
        }
    }

    protected function eventIsWatched(?string $eventName): bool
    {
        if ($eventName === null) {
            return false;
        }

        $watchedEvents = cache()->rememberForever('watchedWebhooks', function () {
            return WebhookConfiguration::where('scope', WebhookScope::Global)
                ->pluck('events')
                ->flatten()
                ->unique()
                ->values()
                ->all();
        });

        return in_array($eventName, $watchedEvents);
    }

    /** @param array<mixed> $webhookData */
    protected function hasPayloadContent(array $webhookData): bool
    {
        return collect($webhookData)
            ->except('event')
            ->reject(fn (mixed $value) => match (true) {
                is_array($value) => empty($value),
                $value instanceof Collection => $value->isEmpty(),
                default => $value === null || $value === '',
            })
            ->isNotEmpty();
    }
}
