<?php

namespace App\Filament\Admin\Resources\Roles;

use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Roles\Pages\CreateRole;
use App\Filament\Admin\Resources\Roles\Pages\EditRole;
use App\Filament\Admin\Resources\Roles\Pages\ListRoles;
use App\Filament\Admin\Resources\Roles\Pages\ViewRole;
use App\Models\Role;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Spatie\Permission\Contracts\Permission;

class RoleResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;

    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::UsersGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return trans('admin/role.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/role.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/role.model_label_plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return user()?->getCustomization(CustomizationKey::TopNavigation) === 'topbar' ? trans('admin/dashboard.advanced') : trans('admin/dashboard.user');
    }

    public static function getNavigationBadge(): ?string
    {
        return ($count = static::getModel()::count()) > 0 ? (string) $count : null;
    }

    /**
     * @throws Exception
     */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/role.name'))
                    ->sortable(),
                TextColumn::make('permissions_count')
                    ->label(trans('admin/role.permissions'))
                    ->badge()
                    ->counts('permissions')
                    ->formatStateUsing(fn (Role $role, $state) => $role->isRootAdmin() ? trans('admin/role.all') : $state),
                TextColumn::make('nodes.name')
                    ->label(trans('admin/role.nodes'))
                    ->badge()
                    ->placeholder(trans('admin/role.all')),
                TextColumn::make('users_count')
                    ->label(trans('admin/role.users'))
                    ->counts('users'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn ($record) => static::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make('exclude_bulk_delete'),
                ]),
            ])
            ->checkIfRecordIsSelectableUsing(fn (Role $role) => !$role->isRootAdmin() && $role->users_count <= 0);
    }

    /**
     * @throws Exception
     */
    public static function defaultForm(Schema $schema): Schema
    {
        $permissionSections = [];

        foreach (Role::getPermissionList() as $model => $permissions) {
            $options = [];

            foreach ($permissions as $permission) {
                $options[$permission . ' ' . $model] = trans()->has("admin/role.permissions_list.$permission")
                    ? trans("admin/role.permissions_list.$permission")
                    : Str::headline($permission);
            }

            $permissionSections[] = self::makeSection($model, $options);
        }

        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->label(trans('admin/role.name'))
                    ->required()
                    ->disabled(fn (Get $get) => $get('name') === Role::ROOT_ADMIN),
                TextInput::make('guard_name')
                    ->default(Role::DEFAULT_GUARD_NAME)
                    ->nullable()
                    ->hidden(),
                Fieldset::make(trans('admin/role.permissions'))
                    ->columns(3)
                    ->schema($permissionSections)
                    ->hidden(fn (Get $get) => $get('name') === Role::ROOT_ADMIN),
                TextEntry::make('permissions')
                    ->label(trans('admin/role.permissions'))
                    ->state(trans('admin/role.root_admin', ['role' => Role::ROOT_ADMIN]))
                    ->visible(fn (Get $get) => $get('name') === Role::ROOT_ADMIN),
                Select::make('nodes')
                    ->label(trans('admin/role.nodes'))
                    ->multiple()
                    ->relationship('nodes', 'name')
                    ->searchable(['name', 'fqdn'])
                    ->preload()
                    ->hint(trans('admin/role.nodes_hint'))
                    ->hidden(fn (Get $get) => $get('name') === Role::ROOT_ADMIN),
            ]);
    }

    /**
     * @param  string[]|int[]|Permission[]|BackedEnum[]  $options
     *
     * @throws Exception
     */
    private static function makeSection(string $model, array $options): Section
    {
        return Section::make(trans()->has("admin/role.models.$model")
            ? trans("admin/role.models.$model")
            : Str::headline($model))
            ->columnSpan(1)
            ->collapsible()
            ->collapsed()
            ->icon(Role::getModelIcon($model))
            ->headerActions([
                Action::make('count')
                    ->label(fn (Get $get) => count($get(strtolower($model) . '_list')))
                    ->badge(),
            ])
            ->schema([
                CheckboxList::make(strtolower($model) . '_list')
                    ->hiddenLabel()
                    ->options($options)
                    ->columns()
                    ->gridDirection('row')
                    ->bulkToggleable()
                    ->live()
                    ->afterStateHydrated(
                        function (Component $component, string $operation, ?Role $record) use ($options) {
                            if (in_array($operation, ['edit', 'view'])) {

                                if (blank($record)) {
                                    return;
                                }

                                if ($component->isVisible()) {
                                    $component->state(
                                        collect($options)
                                            ->filter(fn ($value, $key) => $record->checkPermissionTo($key))
                                            ->keys()
                                            ->toArray()
                                    );
                                }
                            }
                        }
                    )
                    ->dehydrated(fn ($state) => !blank($state)),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListRoles::route('/'),
            'create' => CreateRole::route('/create'),
            'view' => ViewRole::route('/{record}'),
            'edit' => EditRole::route('/{record}/edit'),
        ];
    }
}
