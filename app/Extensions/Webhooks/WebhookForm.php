<?php

namespace App\Extensions\Webhooks;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Facades\WebhookTypes;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;

/**
 * Shared form pieces so the admin and server panels stay in sync while the
 * available webhook types are driven entirely by the registry.
 */
class WebhookForm
{
    public static function typeSelector(): ToggleButtons
    {
        return ToggleButtons::make('type')
            ->label(trans('admin/webhook.type'))
            ->live()
            ->inline()
            ->options(fn (?string $state) => self::withUnavailableType($state, WebhookTypes::getOptions(), fn (string $type) => trans('admin/webhook.unavailable_type_option', ['type' => $type])))
            ->icons(fn (?string $state) => self::withUnavailableType($state, WebhookTypes::getIcons(), fn () => TablerIcon::PuzzleOff))
            ->colors(fn (?string $state) => self::withUnavailableType($state, WebhookTypes::getColors(), fn () => 'gray'))
            ->default(WebhookTypeService::Default);
    }

    /**
     * Append the currently stored type to a registry driven list when no plugin provides it.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private static function withUnavailableType(?string $state, array $values, callable $fallback): array
    {
        if (filled($state) && !array_key_exists($state, $values)) {
            $values[$state] = $fallback($state);
        }

        return $values;
    }

    /**
     * The payload editor for the selected type, or a notice when no plugin provides it.
     */
    public static function payloadSection(?string $type, WebhookScope $scope): Component
    {
        $schema = WebhookTypes::get($type);

        if (!$schema) {
            return Callout::make(trans('admin/webhook.unavailable_type'))
                ->description(trans('admin/webhook.unavailable_type_text', ['type' => $type ?? '?']))
                ->icon(TablerIcon::PuzzleOff)
                ->warning()
                ->columnSpanFull();
        }

        $section = Section::make()
            ->columnSpanFull()
            ->schema($schema->getFormComponents($scope));

        if ($previewComponent = $schema->getPreviewComponent()) {
            $section
                ->aside()
                ->poll('15s')
                ->view('filament.components.webhook-preview-section')
                ->viewData([
                    'previewComponent' => $previewComponent,
                    'previewFields' => $schema->getPreviewFields(),
                    'previewScope' => $scope->value,
                ]);
        }

        return $section;
    }
}
