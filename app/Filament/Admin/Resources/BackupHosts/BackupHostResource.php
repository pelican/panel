<?php

namespace App\Filament\Admin\Resources\BackupHosts;

use App\Enums\TablerIcon;
use App\Extensions\BackupAdapter\BackupAdapterService;
use App\Filament\Admin\Resources\BackupHosts\Pages\CreateBackupHost;
use App\Filament\Admin\Resources\BackupHosts\Pages\EditBackupHost;
use App\Filament\Admin\Resources\BackupHosts\Pages\ListBackupHosts;
use App\Filament\Admin\Resources\BackupHosts\Pages\ViewBackupHost;
use App\Filament\Admin\Resources\BackupHosts\RelationManagers\BackupsRelationManager;
use App\Models\BackupHost;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BackupHostResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;

    protected static ?string $model = BackupHost::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::FileZip;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationLabel(): string
    {
        return static::getPluralModelLabel();
    }

    public static function getModelLabel(): string
    {
        return trans_choice('admin/backuphost.model_label', 1);
    }

    public static function getPluralModelLabel(): string
    {
        return trans_choice('admin/backuphost.model_label', 2);
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    /** @throws Exception */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/backuphost.name')),
                TextColumn::make('schema')
                    ->label(trans('admin/backuphost.schema'))
                    ->badge(),
                TextColumn::make('backups_count')
                    ->counts('backups')
                    ->label(trans('admin/backuphost.backups')),
                TextColumn::make('nodes.name')
                    ->badge()
                    ->placeholder(trans('admin/backuphost.all_nodes')),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn ($record) => static::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
            ])
            ->emptyStateIcon(TablerIcon::FileZip)
            ->emptyStateDescription(trans('admin/backuphost.local_backups_only'))
            ->emptyStateHeading(trans('admin/backuphost.no_backup_hosts'));
    }

    /** @throws Exception */
    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label(trans('admin/backuphost.name'))
                    ->required(),
                Select::make('schema')
                    ->label(trans('admin/backuphost.schema'))
                    ->required()
                    ->selectablePlaceholder(false)
                    ->searchable()
                    ->options(fn (BackupAdapterService $service) => $service->getMappings())
                    ->live(onBlur: true),
                Select::make('node_ids')
                    ->label(trans('admin/backuphost.linked_nodes'))
                    ->multiple()
                    ->searchable()
                    ->preload()
                    // Selecting only non-json fields to prevent Postgres from choking on DISTINCT JSON columns
                    ->relationship('nodes', 'name', fn (Builder $query) => $query->select(['nodes.id', 'nodes.name'])->whereIn('nodes.id', user()?->accessibleNodes()->pluck('id'))),
                Section::make(trans('admin/backuphost.configuration'))
                    ->columnSpanFull()
                    ->columns()
                    ->schema(function (?BackupHost $backupHost, Get $get, BackupAdapterService $service) {
                        $schema = $get('schema') ?? $backupHost?->schema;

                        if (!$schema) {
                            return [];
                        }

                        $schema = $service->get($schema);

                        if ($schema) {
                            return $schema->getConfigurationForm();
                        }

                        return [];
                    }),
            ]);
    }

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            BackupsRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListBackupHosts::route('/'),
            'create' => CreateBackupHost::route('/create'),
            'view' => ViewBackupHost::route('/{record}'),
            'edit' => EditBackupHost::route('/{record}/edit'),
        ];
    }
}
