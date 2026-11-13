<?php

namespace App\Filament\Admin\Resources\Nodes\Components;

use App\Enums\TablerIcon;
use App\Livewire\NodeMassActionTable;
use App\Models\Node;
use Filament\Actions\Action;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;

class NodeMassActions
{
    public static function tab(): Tab
    {
        return Tab::make('mass_actions')
            ->label(trans('admin/node.tabs.mass_actions'))
            ->icon(TablerIcon::ArrowsExchange)
            ->hidden(fn (string $operation) => $operation === 'view' || !user()?->can('update server'))
            ->schema([
                Section::make(trans('admin/node.tabs.mass_actions'))
                    ->description(trans('admin/node.mass_actions.help'))
                    ->columnSpanFull()
                    ->schema([
                        Actions::make([
                            self::action('start', TablerIcon::PlayerPlay, 'success'),
                            self::action('stop', TablerIcon::PlayerStop, 'danger'),
                            self::action('restart', TablerIcon::Refresh, 'warning'),
                            self::action(NodeMassActionTable::TRANSFER, TablerIcon::ArrowsExchange, 'primary')
                                ->modalDescription(trans('admin/node.transfer_batch.help'))
                                ->disabled(fn (Node $node) => user()?->accessibleNodes()->whereNot('id', $node->id)->doesntExist()),
                        ]),
                    ]),
            ]);
    }

    /**
     * The table in the modal has its own submit button: everything chosen lives on the server,
     * as a form holding every server of a large node takes longer to process than PHP allows.
     */
    private static function action(string $action, TablerIcon $icon, string $color): Action
    {
        return Action::make("exclude_{$action}Servers")
            ->label(trans("admin/node.mass_actions.$action"))
            ->modalHeading(trans("admin/node.mass_actions.$action"))
            ->icon($icon)
            ->color($color)
            ->modalWidth(Width::SevenExtraLarge)
            ->authorize(fn () => user()?->can('update server'))
            ->modalContent(fn (Node $node) => view('livewire.node-mass-action-modal', ['node' => $node, 'action' => $action]))
            ->modalFooterActions([]);
    }
}
