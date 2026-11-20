<?php

namespace App\Filament\Components\Actions;

use App\Enums\EggFormat;
use App\Enums\TablerIcon;
use App\Models\Egg;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\Alignment;

class ExportEggAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'export';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel();

        $this->tooltip(trans('filament-actions::export.modal.actions.export.label'));

        $this->icon(TablerIcon::Download);

        $this->tableIcon(TablerIcon::Download);

        $this->authorize(fn () => user()?->can('export egg'));

        $this->modalHeading(fn (Egg $egg) => trans('filament-actions::export.modal.actions.export.label') . '  ' . $egg->name);

        $this->modalIcon($this->icon);

        $this->schema([
            TextEntry::make('label')
                ->hiddenLabel()
                ->state(fn (Egg $egg) => trans('admin/egg.export.modal', ['egg' => $egg->name])),
        ]);

        $this->modalFooterActionsAlignment(Alignment::Center);

        $this->modalFooterActions([
            Action::make('exclude_json')
                ->label(trans('admin/egg.export.as', ['format' => 'json']))
                ->url(fn (Egg $egg) => route('api.application.eggs.eggs.export', ['egg' => $egg, 'format' => EggFormat::JSON->value]), true)
                ->close(),
            Action::make('exclude_yaml')
                ->label(trans('admin/egg.export.as', ['format' => 'yaml']))
                ->url(fn (Egg $egg) => route('api.application.eggs.eggs.export', ['egg' => $egg, 'format' => EggFormat::YAML->value]), true)
                ->close(),
        ]);
    }
}
