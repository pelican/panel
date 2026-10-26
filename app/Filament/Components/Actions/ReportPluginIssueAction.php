<?php

namespace App\Filament\Components\Actions;

use App\Enums\TablerIcon;
use App\Models\Plugin;
use App\Services\Helpers\PluginHubService;
use App\Services\Helpers\PluginIssueLink;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Js;
use Livewire\Component;

/**
 * Lets an admin report an issue with a plugin to its author. The record is
 * either an installed Plugin or a Hub plugin array from the browse page.
 */
class ReportPluginIssueAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'exclude_report_issue';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label(trans('admin/plugin.hub.report_issue'));

        $this->icon(TablerIcon::Bug);

        $this->color('gray');

        $this->authorize(fn () => user()?->can('viewList plugin'));

        $this->modalHeading(fn (Plugin|array $record) => trans('admin/plugin.hub.report_issue_heading', ['name' => $this->resolvePluginName($record)]));

        $this->modalDescription(trans('admin/plugin.hub.report_issue_description'));

        $this->modalSubmitActionLabel(trans('admin/plugin.hub.report_issue_submit'));

        $this->fillForm(function (Plugin|array $record, PluginHubService $hub) {
            $slug = $this->resolveSlug($record);

            return [
                'plugin_version' => Plugin::find($slug)->version ?? $hub->find($slug)['latest_version'] ?? null,
                'panel_version' => config('app.version'),
                'include_environment' => true,
            ];
        });

        $this->schema([
            TextInput::make('title')
                ->label(trans('admin/plugin.hub.issue_title'))
                ->required()
                ->maxLength(150),
            MarkdownEditor::make('description')
                ->label(trans('admin/plugin.hub.issue_description'))
                ->helperText(trans('admin/plugin.hub.issue_description_help'))
                ->required()
                ->maxLength(4000),
            TextInput::make('plugin_version')
                ->label(trans('admin/plugin.hub.plugin_version'))
                ->maxLength(255),
            TextInput::make('panel_version')
                ->label(trans('admin/plugin.hub.panel_version'))
                ->maxLength(255),
            Checkbox::make('include_environment')
                ->label(trans('admin/plugin.hub.include_environment')),
        ]);

        $this->extraModalFooterActions(function (Plugin|array $record, PluginHubService $hub) {
            $discord = $hub->find($this->resolveSlug($record))['discord_support_url'] ?? null;

            return [
                Action::make('exclude_discord_support')
                    ->label(trans('admin/plugin.hub.discord_support'))
                    ->icon(TablerIcon::BrandDiscord)
                    ->color('gray')
                    ->visible(is_string($discord) && str_starts_with($discord, 'https://'))
                    ->url($discord, true),
            ];
        });

        $this->action(function (array $data, Plugin|array $record, Component $livewire, PluginHubService $hub) {
            $slug = $this->resolveSlug($record);
            $installed = $record instanceof Plugin ? $record : Plugin::find($slug);

            $url = PluginIssueLink::for($data, $hub->find($slug), $installed);

            if ($url === null) {
                Notification::make()
                    ->danger()
                    ->title(trans('admin/plugin.hub.report_issue_unavailable'))
                    ->send();

                return;
            }

            $livewire->js('window.open(' . Js::from($url) . ", '_blank', 'noopener')");
        });
    }

    /** @param  Plugin|array<string, mixed>  $record */
    private function resolveSlug(Plugin|array $record): string
    {
        return $record instanceof Plugin ? $record->id : (string) ($record['slug'] ?? '');
    }

    /** @param  Plugin|array<string, mixed>  $record */
    private function resolvePluginName(Plugin|array $record): string
    {
        return $record instanceof Plugin ? $record->name : (string) ($record['title'] ?? $record['slug'] ?? '');
    }
}
