<?php

namespace App\Filament\Admin\Resources\Nodes\RelationManagers;

use App\Enums\ServerTransferBatchItemStatus;
use App\Enums\ServerTransferBatchStatus;
use App\Enums\TablerIcon;
use App\Models\ServerTransferBatch;
use App\Models\ServerTransferBatchItem;
use App\Services\Servers\ServerTransferBatchService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Text;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ServerTransferBatchesRelationManager extends RelationManager
{
    protected static string $relationship = 'transferBatches';

    protected static string|BackedEnum|null $icon = TablerIcon::ArrowsExchange;

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return trans('admin/node.transfer_batch.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return user()?->can('update server') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading(null)
            ->poll('15s')
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['newNode', 'user', 'activeItem.server'])->withCount([
                'items',
                'items as finished_items_count' => fn (Builder $query) => $query->whereIn('status', [ServerTransferBatchItemStatus::Successful, ServerTransferBatchItemStatus::Failed]),
                'failedItems',
            ]))
            ->columns([
                TextColumn::make('newNode.name')
                    ->label(trans('admin/node.transfer_batch.target')),
                TextColumn::make('status')
                    ->label(trans('admin/node.transfer_batch.batch_status'))
                    ->badge()
                    ->wrap()
                    ->description(fn (ServerTransferBatch $batch) => $batch->status === ServerTransferBatchStatus::Running && !$batch->activeItem
                        ? $batch->items()->where('status', ServerTransferBatchItemStatus::Queued)->whereNotNull('error')->value('error')
                        : null),
                TextColumn::make('progress')
                    ->label(trans('admin/node.transfer_batch.progress'))
                    ->state(fn (ServerTransferBatch $batch) => "$batch->finished_items_count / $batch->items_count"),
                TextColumn::make('failed_items_count')
                    ->label(trans('admin/node.transfer_batch.failed'))
                    ->color(fn (int $state) => $state > 0 ? 'danger' : null),
                TextColumn::make('activeItem.server.name')
                    ->label(trans('admin/node.transfer_batch.current_server'))
                    ->placeholder('-'),
                TextColumn::make('user.username')
                    ->label(trans('admin/node.transfer_batch.started_by'))
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label(trans('admin/node.transfer_batch.created_at'))
                    ->since(),
            ])
            ->emptyStateHeading(trans('admin/node.transfer_batch.empty'))
            ->emptyStateDescription(trans('admin/node.transfer_batch.empty_help'))
            ->recordActions([
                Action::make('failures')
                    ->label(trans('admin/node.transfer_batch.view_failures'))
                    ->icon(TablerIcon::AlertTriangle)
                    ->color('danger')
                    ->visible(fn (ServerTransferBatch $batch) => $batch->failed_items_count > 0)
                    ->modalSubmitAction(false)
                    ->schema(fn (ServerTransferBatch $batch) => $batch->failedItems()->with('server')->get()
                        ->map(fn (ServerTransferBatchItem $item) => Text::make("{$item->server->name}: $item->error"))
                        ->all()),
                Action::make('cancel')
                    ->label(trans('admin/node.transfer_batch.cancel'))
                    ->icon(TablerIcon::Ban)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(trans('admin/node.transfer_batch.cancel_help'))
                    ->authorize(fn () => user()?->can('update server'))
                    ->visible(fn (ServerTransferBatch $batch) => !$this->isReadOnly() && $batch->status === ServerTransferBatchStatus::Running)
                    ->action(function (ServerTransferBatch $batch, ServerTransferBatchService $service) {
                        try {
                            $isDone = $service->cancel($batch);
                        } catch (LockTimeoutException) {
                            Notification::make()
                                ->title(trans('admin/node.transfer_batch.notifications.cancel_busy'))
                                ->warning()
                                ->send();

                            return;
                        }

                        $notification = Notification::make();

                        ($isDone
                            ? $notification->title(trans('admin/node.transfer_batch.notifications.cancelled'))->success()
                            : $notification->title(trans('admin/node.transfer_batch.notifications.cancel_unconfirmed'))->warning()
                        )->send();
                    }),
            ]);
    }
}
