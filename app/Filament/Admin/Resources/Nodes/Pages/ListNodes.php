<?php

namespace App\Filament\Admin\Resources\Nodes\Pages;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Nodes\NodeResource;
use App\Filament\Components\Tables\Columns\NodeClientHealthColumn;
use App\Filament\Components\Tables\Columns\NodeHealthColumn;
use App\Filament\Components\Tables\Filters\TagsFilter;
use App\Models\Node;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ListNodes extends ListRecords
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;

    protected static string $resource = NodeResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->checkIfRecordIsSelectableUsing(fn (Node $node) => $node->servers_count <= 0)
            ->columns([
                TextColumn::make('uuid')
                    ->label(trans('admin/node.table.uuid'))
                    ->searchable()
                    ->hidden(),
                NodeHealthColumn::make('health'),
                NodeClientHealthColumn::make('reachable'),
                TextColumn::make('name')
                    ->label(trans('admin/node.table.name'))
                    ->sortable()
                    ->searchable(),
                TextColumn::make('fqdn')
                    ->visibleFrom('md')
                    ->label(trans('admin/node.table.address'))
                    ->sortable()
                    ->searchable(),
                IconColumn::make('scheme')
                    ->visibleFrom('xl')
                    ->label(trans('admin/node.table.ssl'))
                    ->trueIcon(TablerIcon::Lock)
                    ->falseIcon(TablerIcon::LockOpenOff)
                    ->state(fn (Node $node) => $node->scheme === 'https'),
                IconColumn::make('public')
                    ->label(trans('admin/node.table.public'))
                    ->visibleFrom('lg')
                    ->trueIcon(TablerIcon::EyeCheck)
                    ->falseIcon(TablerIcon::EyeCancel),
                TextColumn::make('servers_count')
                    ->visibleFrom('sm')
                    ->counts('servers')
                    ->label(trans('admin/node.table.servers'))
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->tooltip(trans('filament-actions::view.single.label'))
                    ->hidden(fn (Node $record) => NodeResource::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->emptyStateIcon(TablerIcon::Server2)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/node.no_nodes'))
            ->filters([
                TagsFilter::make()
                    ->model(Node::class),
            ]);
    }
}
