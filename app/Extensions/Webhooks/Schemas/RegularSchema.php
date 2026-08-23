<?php

namespace App\Extensions\Webhooks\Schemas;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Models\WebhookConfiguration;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\KeyValue;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Utilities\Set;

class RegularSchema extends BaseSchema
{
    public const DEFAULT_HEADERS = [
        'X-Webhook-Event' => '{{event}}',
    ];

    public function getId(): string
    {
        return 'regular';
    }

    public function getLabel(): string
    {
        return trans('admin/webhook.regular');
    }

    public function getIcon(): string|BackedEnum|null
    {
        return TablerIcon::WorldWww;
    }

    /** @return Component[] */
    public function getFormComponents(WebhookScope $scope): array
    {
        return [
            KeyValue::make('headers')
                ->label(trans('admin/webhook.headers'))
                ->columnSpanFull()
                ->default(fn () => self::DEFAULT_HEADERS)
                ->hintAction(
                    Action::make('reset_headers')
                        ->label(trans('admin/webhook.reset_headers'))
                        ->color('danger')
                        ->icon(TablerIcon::Trash)
                        ->action(fn (Set $set) => $set('headers', self::DEFAULT_HEADERS))
                ),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['headers']) && is_array($data['headers'])) {
            $headers = [];
            foreach ($data['headers'] as $key => $value) {
                $headers[str_replace(' ', '-', $key)] = $value;
            }

            $data['headers'] = $headers;
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function mutateFormDataBeforeFill(array $data): array
    {
        $data['headers'] ??= [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $eventData
     * @return array<string, string>
     */
    public function prepareHeaders(WebhookConfiguration $webhookConfiguration, array $payload, array $eventData): array
    {
        $headers = [];

        foreach ($webhookConfiguration->headers ?? [] as $key => $value) {
            $headers[$key] = $webhookConfiguration->replaceVars($eventData, $value);
        }

        return $headers;
    }
}
