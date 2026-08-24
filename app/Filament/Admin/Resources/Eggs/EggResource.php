<?php

namespace App\Filament\Admin\Resources\Eggs;

use App\Enums\CustomizationKey;
use App\Enums\EditorLanguages;
use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Eggs\Pages\CreateEgg;
use App\Filament\Admin\Resources\Eggs\Pages\EditEgg;
use App\Filament\Admin\Resources\Eggs\Pages\ListEggs;
use App\Filament\Admin\Resources\Eggs\Pages\ViewEgg;
use App\Filament\Admin\Resources\Eggs\RelationManagers\ServersRelationManager;
use App\Filament\Components\Actions\DeleteIcon;
use App\Filament\Components\Actions\UploadIcon;
use App\Filament\Components\Forms\Fields\CopyFrom;
use App\Filament\Components\Forms\Fields\MonacoEditor;
use App\Models\Egg;
use App\Models\EggVariable;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use BackedEnum;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Validation\Rules\Unique;

class EggResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;

    protected static ?string $model = Egg::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Eggs;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        return ($count = static::getModel()::count()) > 0 ? (string) $count : null;
    }

    public static function getNavigationGroup(): ?string
    {
        if (user()?->getCustomization(CustomizationKey::TopNavigation) === 'topbar') {
            return null;
        }

        return trans('admin/dashboard.server');
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/egg.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/egg.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/egg.model_label_plural');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'tags', 'uuid', 'id'];
    }

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            ServersRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListEggs::route('/'),
            'create' => CreateEgg::route('/create'),
            'view' => ViewEgg::route('/{record}'),
            'edit' => EditEgg::route('/{record}/edit'),
        ];
    }

    /** @return Tab[] */
    public static function detailTabs(): array
    {
        return [
            Tab::make('configuration')
                ->label(trans('admin/egg.tabs.configuration'))
                ->columns(['default' => 2, 'sm' => 2, 'md' => 4, 'lg' => 6])
                ->icon(TablerIcon::Egg)
                ->schema([
                    Grid::make(2)
                        ->columnStart(1)
                        ->schema([
                            Image::make('', 'icon')
                                ->hidden(fn ($record) => !$record->icon)
                                ->url(fn ($record) => $record->icon)
                                ->imageSize(150)
                                ->columnSpanFull()
                                ->alignJustify(),
                            UploadIcon::make()
                                ->hidden(fn (string $operation) => $operation === 'view'),
                            DeleteIcon::make()
                                ->iconStoragePath(Egg::getIconStoragePath())
                                ->hidden(fn (string $operation) => $operation === 'view'),
                        ]),
                    TextInput::make('name')
                        ->label(trans('admin/egg.name'))
                        ->required()
                        ->maxLength(255)
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 3, 'lg' => 2])
                        ->helperText(trans('admin/egg.name_help')),
                    Textarea::make('description')
                        ->label(trans('admin/egg.description'))
                        ->rows(3)
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 4, 'lg' => 3])
                        ->helperText(trans('admin/egg.description_help')),
                    TextInput::make('id')
                        ->label(trans('admin/egg.egg_id'))
                        ->columnSpan(1)
                        ->disabled(),
                    TextInput::make('uuid')
                        ->label(trans('admin/egg.egg_uuid'))
                        ->disabled()
                        ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 2])
                        ->helperText(trans('admin/egg.uuid_help')),
                    TextInput::make('author')
                        ->label(trans('admin/egg.author'))
                        ->required()
                        ->maxLength(255)
                        ->email()
                        ->disabled()
                        ->columnSpan(['default' => 1, 'sm' => 1, 'md' => 1, 'lg' => 2])
                        ->helperText(trans('admin/egg.author_help_edit')),
                    Toggle::make('force_outgoing_ip')
                        ->inline(false)
                        ->label(trans('admin/egg.force_ip'))
                        ->columnSpan(1)
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/egg.force_ip_help')),
                    KeyValue::make('startup_commands')
                        ->label(trans('admin/egg.startup_commands'))
                        ->live()
                        ->columnSpanFull()
                        ->required()
                        ->reorderable()
                        ->addActionLabel(trans('admin/egg.add_startup'))
                        ->keyLabel(trans('admin/egg.startup_name'))
                        ->valueLabel(trans('admin/egg.startup_command'))
                        ->helperText(trans('admin/egg.startup_help')),
                    TagsInput::make('file_denylist')
                        ->label(trans('admin/egg.file_denylist'))
                        ->placeholder('denied-file.txt')
                        ->helperText(trans('admin/egg.file_denylist_help'))
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 2, 'lg' => 3]),
                    TextInput::make('update_url')
                        ->label(trans('admin/egg.update_url'))
                        ->url()
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/egg.update_url_help'))
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 2, 'lg' => 3]),
                    TagsInput::make('features')
                        ->label(trans('admin/egg.features'))
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 2, 'lg' => 3]),
                    Hidden::make('script_is_privileged')
                        ->helperText(trans('admin/egg.docker_help')),
                    TagsInput::make('tags')
                        ->label(trans('admin/egg.tags'))
                        ->columnSpan(['default' => 2, 'sm' => 2, 'md' => 2, 'lg' => 3]),
                    KeyValue::make('docker_images')
                        ->label(trans('admin/egg.docker_images'))
                        ->live()
                        ->columnSpanFull()
                        ->required()
                        ->reorderable()
                        ->addActionLabel(trans('admin/egg.add_image'))
                        ->keyLabel(trans('admin/egg.docker_name'))
                        ->valueLabel(trans('admin/egg.docker_uri'))
                        ->helperText(trans('admin/egg.docker_help')),
                ]),
            Tab::make('process_management')
                ->label(trans('admin/egg.tabs.process_management'))
                ->columns()
                ->icon(TablerIcon::ServerCog)
                ->schema([
                    CopyFrom::make('copy_process_from')
                        ->process()
                        ->hidden(fn (string $operation) => $operation === 'view'),
                    TextInput::make('config_stop')
                        ->label(trans('admin/egg.stop_command'))
                        ->maxLength(255)
                        ->helperText(trans('admin/egg.stop_command_help')),
                    Textarea::make('config_startup')->rows(10)->json()
                        ->label(trans('admin/egg.start_config'))
                        ->helperText(trans('admin/egg.start_config_help')),
                    Textarea::make('config_files')->rows(10)->json()
                        ->label(trans('admin/egg.config_files'))
                        ->dehydrateStateUsing(fn ($state) => blank($state) ? '{}' : $state)
                        ->helperText(trans('admin/egg.config_files_help')),
                    Textarea::make('config_logs')->rows(10)->json()
                        ->label(trans('admin/egg.log_config'))
                        ->helperText(trans('admin/egg.log_config_help')),
                ]),
            Tab::make('egg_variables')
                ->label(trans('admin/egg.tabs.egg_variables'))
                ->columnSpanFull()
                ->icon(TablerIcon::Variable)
                ->schema([
                    Repeater::make('variables')
                        ->hiddenLabel()
                        ->grid()
                        ->relationship('variables')
                        ->orderColumn()
                        ->collapsible()->collapsed()
                        ->addActionLabel(trans('admin/egg.add_new_variable'))
                        ->itemLabel(fn (array $state) => $state['name'])
                        ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                            $data['default_value'] ??= '';
                            $data['description'] ??= '';
                            $data['rules'] ??= [];
                            $data['user_viewable'] ??= '';
                            $data['user_editable'] ??= '';

                            return $data;
                        })
                        ->mutateRelationshipDataBeforeSaveUsing(function (array $data): array {
                            $data['default_value'] ??= '';
                            $data['description'] ??= '';
                            $data['rules'] ??= [];
                            $data['user_viewable'] ??= '';
                            $data['user_editable'] ??= '';

                            return $data;
                        })
                        ->schema([
                            TextInput::make('name')
                                ->label(trans('admin/egg.name'))
                                ->live()
                                ->debounce(750)
                                ->maxLength(255)
                                ->columnSpanFull()
                                ->afterStateUpdated(fn (Set $set, $state) => $set('env_variable', str($state)->trim()->snake()->upper()->toString()))
                                ->unique(modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('egg_id', $get('../../id')))
                                ->validationMessages([
                                    'unique' => trans('admin/egg.error_unique'),
                                ])
                                ->required(),
                            Textarea::make('description')->label(trans('admin/egg.description'))->columnSpanFull(),
                            TextInput::make('env_variable')
                                ->label(trans('admin/egg.environment_variable'))
                                ->maxLength(255)
                                ->prefix('{{')
                                ->suffix('}}')
                                ->hintIcon(TablerIcon::Code, fn ($state) => "{{{$state}}}")
                                ->unique(modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('egg_id', $get('../../id')))
                                ->rules(EggVariable::getRulesForField('env_variable'))
                                ->validationMessages([
                                    'unique' => trans('admin/egg.error_unique'),
                                    'required' => trans('admin/egg.error_required'),
                                    '*' => trans('admin/egg.error_reserved'),
                                ])
                                ->required(),
                            TextInput::make('default_value')->label(trans('admin/egg.default_value')),
                            Fieldset::make(trans('admin/egg.user_permissions'))
                                ->schema([
                                    Checkbox::make('user_viewable')->label(trans('admin/egg.viewable')),
                                    Checkbox::make('user_editable')->label(trans('admin/egg.editable')),
                                ]),
                            TagsInput::make('rules')
                                ->label(trans('admin/egg.rules'))
                                ->columnSpanFull()
                                ->reorderable()
                                ->suggestions([
                                    'required',
                                    'nullable',
                                    'string',
                                    'integer',
                                    'numeric',
                                    'boolean',
                                    'alpha',
                                    'alpha_dash',
                                    'alpha_num',
                                    'url',
                                    'email',
                                    'regex:',
                                    'min:',
                                    'max:',
                                    'between:',
                                    'between:1024,65535',
                                    'in:',
                                    'in:true,false',
                                ]),
                        ]),
                ]),
            Tab::make('install_script')
                ->label(trans('admin/egg.tabs.install_script'))
                ->columns(3)
                ->icon(TablerIcon::FileDownload)
                ->schema([
                    CopyFrom::make('copy_script_from')
                        ->script()
                        ->hidden(fn (string $operation) => $operation === 'view'),
                    TextInput::make('script_container')
                        ->label(trans('admin/egg.script_container'))
                        ->required()
                        ->maxLength(255)
                        ->placeholder('ghcr.io/pelican-eggs/installers:debian'),
                    Select::make('script_entry')
                        ->label(trans('admin/egg.script_entry'))
                        ->selectablePlaceholder(false)
                        ->options([
                            'bash' => 'bash',
                            'ash' => 'ash',
                            '/bin/bash' => '/bin/bash',
                        ])
                        ->required(),
                    MonacoEditor::make('script_install')
                        ->hiddenLabel()
                        ->language(EditorLanguages::shell)
                        ->columnSpanFull(),
                ]),
        ];
    }
}
