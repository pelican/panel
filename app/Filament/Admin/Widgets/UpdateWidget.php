<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TablerIcon;
use App\Services\Helpers\SoftwareVersionService;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UpdateWidget extends FormWidget
{
    protected static ?int $sort = 0;

    private SoftwareVersionService $softwareVersionService;

    public function mount(SoftwareVersionService $softwareVersionService): void
    {
        $this->softwareVersionService = $softwareVersionService;
    }

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        $latestVersion = $this->softwareVersionService->latestPanelVersion();

        if ($latestVersion === null && $this->softwareVersionService->currentComparableVersion() !== null) {
            return $schema
                ->components([
                    Section::make(trans('admin/dashboard.sections.intro-version-unavailable.heading'))
                        ->icon(TablerIcon::CloudOff)
                        ->iconColor('gray')
                        ->schema([
                            TextEntry::make('info')
                                ->hiddenLabel()
                                ->state(trans('admin/dashboard.sections.intro-version-unavailable.content', ['version' => $this->softwareVersionService->currentPanelVersion()])),
                        ]),
                ]);
        }

        $isLatest = $this->softwareVersionService->isLatestPanel();
        $isContainer = file_exists('/.dockerenv');

        return $schema
            ->components([
                $isLatest
                ? Section::make(trans('admin/dashboard.sections.intro-no-update.heading'))
                    ->icon(TablerIcon::Checkbox)
                    ->iconColor('success')
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-no-update.content', ['version' => $this->softwareVersionService->currentPanelVersion()])),
                    ])
                : Section::make(trans('admin/dashboard.sections.intro-update-available.heading'))
                    ->icon(TablerIcon::InfoCircle)
                    ->iconColor('warning')
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-update-available.' . ($isContainer ? 'content_container' : 'content'), ['latestVersion' => $latestVersion])),
                        Section::make(trans('admin/dashboard.sections.intro-update-available.button_changelog'))
                            ->icon(TablerIcon::Script)
                            ->collapsible()
                            ->collapsed()
                            ->schema([
                                TextEntry::make('changelog')
                                    ->hiddenLabel()
                                    ->state($this->softwareVersionService->latestPanelVersionChangelog())
                                    ->markdown(),
                            ]),
                    ])
                    ->headerActions([
                        Action::make('db_update')
                            ->label(trans('admin/dashboard.sections.intro-update-available.heading'))
                            ->icon(TablerIcon::ClipboardText)
                            ->url('https://pelican.dev/docs/panel/update', true)
                            ->color('warning'),
                    ]),
            ]);
    }
}
