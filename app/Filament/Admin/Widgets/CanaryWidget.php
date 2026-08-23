<?php

namespace App\Filament\Admin\Widgets;

use App\Enums\TablerIcon;
use Exception;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CanaryWidget extends FormWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return config('app.version') === 'canary';
    }

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(trans('admin/dashboard.sections.intro-developers.heading'))
                    ->icon(TablerIcon::Code)
                    ->iconColor('primary')
                    ->collapsible()
                    ->collapsed()
                    ->persistCollapsed()
                    ->schema([
                        TextEntry::make('info')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-developers.content')),
                        TextEntry::make('extra')
                            ->hiddenLabel()
                            ->state(trans('admin/dashboard.sections.intro-developers.extra_note')),
                    ])
                    ->headerActions([
                        Action::make('db_issues')
                            ->label(trans('admin/dashboard.sections.intro-developers.button_issues'))
                            ->icon(TablerIcon::BrandGithub)
                            ->url('https://github.com/pelican/panel/issues', true),
                    ]),
            ]);
    }
}
