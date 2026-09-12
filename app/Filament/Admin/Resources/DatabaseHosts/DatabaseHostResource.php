<?php

namespace App\Filament\Admin\Resources\DatabaseHosts;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\CreateDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\EditDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\ListDatabaseHosts;
use App\Filament\Admin\Resources\DatabaseHosts\Pages\ViewDatabaseHost;
use App\Filament\Admin\Resources\DatabaseHosts\RelationManagers\DatabasesRelationManager;
use App\Filament\Components\Actions\LoggedDeleteBulkAction;
use App\Models\DatabaseHost;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DatabaseHostResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;

    protected static ?string $model = DatabaseHost::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Database;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count() ?: null;
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/databasehost.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/databasehost.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/databasehost.model_label_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    /**
     * @throws Exception
     */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/databasehost.table.name')),
                TextColumn::make('host')
                    ->label(trans('admin/databasehost.table.host')),
                TextColumn::make('port')
                    ->label(trans('admin/databasehost.table.port')),
                TextColumn::make('username')
                    ->label(trans('admin/databasehost.table.username')),
                TextColumn::make('databases_count')
                    ->counts('databases')
                    ->label(trans('admin/databasehost.databases')),
                TextColumn::make('nodes.name')
                    ->label(trans('admin/databasehost.nodes'))
                    ->badge()
                    ->placeholder(trans('admin/databasehost.no_nodes')),
            ])
            ->checkIfRecordIsSelectableUsing(fn (DatabaseHost $databaseHost) => !$databaseHost->databases_count)
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn ($record) => static::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    LoggedDeleteBulkAction::make('exclude_bulk_delete'),
                ]),
            ])
            ->emptyStateIcon(TablerIcon::Database)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/databasehost.no_database_hosts'));
    }

    /**
     * @throws Exception
     */
    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columnSpanFull()
                    ->columns([
                        'default' => 2,
                        'sm' => 3,
                        'md' => 3,
                        'lg' => 4,
                    ])
                    ->schema([
                        TextInput::make('host')
                            ->columnSpan(2)
                            ->label(trans('admin/databasehost.host'))
                            ->helperText(trans('admin/databasehost.host_help'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, Set $set) => $set('name', $state))
                            ->maxLength(255),
                        TextInput::make('port')
                            ->columnSpan(1)
                            ->label(trans('admin/databasehost.port'))
                            ->helperText(trans('admin/databasehost.port_help'))
                            ->required()
                            ->numeric()
                            ->default(3306)
                            ->minValue(0)
                            ->maxValue(65535),
                        TextInput::make('max_databases')
                            ->label(trans('admin/databasehost.max_database'))
                            ->helperText(trans('admin/databasehost.max_databases_help'))
                            ->numeric(),
                        TextInput::make('name')
                            ->label(trans('admin/databasehost.display_name'))
                            ->helperText(trans('admin/databasehost.display_name_help'))
                            ->required()
                            ->maxLength(60),
                        TextInput::make('username')
                            ->label(trans('admin/databasehost.username'))
                            ->helperText(trans('admin/databasehost.username_help'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('password')
                            ->label(trans('admin/databasehost.password'))
                            ->helperText(trans('admin/databasehost.password_help'))
                            ->password()
                            ->revealable()
                            ->maxLength(255)
                            ->required(fn ($operation) => $operation === 'create'),
                        Select::make('node_ids')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText(trans('admin/databasehost.linked_nodes_help'))
                            ->label(trans('admin/databasehost.linked_nodes'))
                            ->relationship('nodes', 'name', fn (Builder $query) => $query->whereIn('nodes.id', user()?->accessibleNodes()->pluck('id'))),
                    ]),
            ]);
    }

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            DatabasesRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListDatabaseHosts::route('/'),
            'create' => CreateDatabaseHost::route('/create'),
            'view' => ViewDatabaseHost::route('/{record}'),
            'edit' => EditDatabaseHost::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        return $query->where(function (Builder $query) {
            return $query->whereHas('nodes', function (Builder $query) {
                $query->whereIn('nodes.id', user()?->accessibleNodes()->pluck('id'));
            })->orDoesntHave('nodes');
        });
    }
}
