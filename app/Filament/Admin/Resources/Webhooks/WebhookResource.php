<?php

namespace App\Filament\Admin\Resources\Webhooks;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Extensions\Webhooks\WebhookForm;
use App\Facades\WebhookTypes;
use App\Filament\Admin\Resources\Webhooks\Pages\CreateWebhookConfiguration;
use App\Filament\Admin\Resources\Webhooks\Pages\EditWebhookConfiguration;
use App\Filament\Admin\Resources\Webhooks\Pages\ListWebhookConfigurations;
use App\Filament\Admin\Resources\Webhooks\Pages\ViewWebhookConfiguration;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Livewire\Features\SupportEvents\HandlesEvents;

class WebhookResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;
    use HandlesEvents;

    protected static ?string $model = WebhookConfiguration::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Webhook;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationLabel(): string
    {
        return trans('admin/webhook.nav_title');
    }

    public static function getModelLabel(): string
    {
        return trans('admin/webhook.model_label');
    }

    public static function getPluralModelLabel(): string
    {
        return trans('admin/webhook.model_label_plural');
    }

    public static function getNavigationBadge(): ?string
    {
        return ($count = static::getModel()::count()) > 0 ? (string) $count : null;
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    public static function defaultTable(Table $table): Table
    {
        return $table
            ->groups([
                'server.name',
            ])
            ->columns([
                TextColumn::make('name')
                    ->label(trans('admin/webhook.name')),
                TextColumn::make('description')
                    ->label(trans('admin/webhook.table.description')),
                IconColumn::make('type')
                    ->label(trans('admin/webhook.type'))
                    ->icon(fn (?string $state) => WebhookTypes::get($state)?->getIcon() ?? TablerIcon::PuzzleOff)
                    ->color(fn (?string $state) => WebhookTypes::get($state)?->getColor() ?? 'gray')
                    ->tooltip(fn (?string $state) => WebhookTypes::get($state)?->getLabel() ?? trans('admin/webhook.unavailable_type')),
                TextColumn::make('server.name')
                    ->label(trans('admin/webhook.server'))
                    ->placeholder('—')
                    ->icon('tabler-server')
                    ->iconColor('info'),
                TextColumn::make('endpoint')
                    ->label(trans('admin/webhook.endpoint'))
                    ->formatStateUsing(fn (string $state) => str($state)->after('://'))
                    ->limit(60)
                    ->wrap(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (WebhookConfiguration $record) => static::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
                ReplicateAction::make()
                    ->iconButton()
                    ->tooltip(trans('filament-actions::replicate.single.label'))
                    ->modal(false)
                    ->excludeAttributes(['created_at', 'updated_at'])
                    ->beforeReplicaSaved(fn (WebhookConfiguration $replica) => $replica->name .= ' Copy ' . now()->format('Y-m-d H:i:s'))
                    ->successRedirectUrl(fn (WebhookConfiguration $replica) => EditWebhookConfiguration::getUrl(['record' => $replica])),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make('exclude_bulk_delete'),
                ]),
            ])
            ->emptyStateIcon(TablerIcon::Webhook)
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/webhook.no_webhooks'))
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('type')
                    ->options(fn () => WebhookTypes::getOptions())
                    ->attribute('type'),
                SelectFilter::make('server_id')
                    ->label(trans('admin/webhook.server'))
                    ->options(Server::query()->pluck('name', 'id')->toArray()),
            ]);
    }

    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('webhook_tabs')
                    ->persistTab()
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make(trans('admin/webhook.information'))
                            ->icon(TablerIcon::InfoCircle)
                            ->schema([
                                Grid::make()
                                    ->schema([
                                        TextInput::make('name')
                                            ->label(trans('admin/webhook.name'))
                                            ->required(),
                                        Select::make('server_id')
                                            ->label(trans('admin/webhook.server'))
                                            ->relationship('server', 'id')
                                            ->preload()
                                            ->disabled(),
                                    ]),
                                TextInput::make('description')
                                    ->label(trans('admin/webhook.description'))
                                    ->required(),
                                Grid::make()
                                    ->schema([
                                        WebhookForm::typeSelector(),
                                        Hidden::make('scope')
                                            ->formatStateUsing(fn (Get $get) => $get('server_id') ? WebhookScope::Server : WebhookScope::Global),
                                        TextInput::make('endpoint')
                                            ->label(trans('admin/webhook.endpoint'))
                                            ->required()
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (?string $state, ?string $old, Get $get, Set $set) => $set('type', WebhookTypes::detectFor($state, $old, $get('type')))),
                                    ]),
                            ]),
                        Tab::make(trans('admin/webhook.payload'))
                            ->icon(TablerIcon::FileCode)
                            ->schema(fn (Get $get) => [
                                WebhookForm::payloadSection($get('type'), self::resolveScope($get('scope'))),
                            ]),
                        Tab::make(trans('admin/webhook.events'))
                            ->icon(TablerIcon::Star)
                            ->schema([
                                Section::make()
                                    ->schema([
                                        CheckboxList::make('events')
                                            ->live()
                                            ->options(fn (Get $get) => WebhookConfiguration::filamentCheckboxList(self::resolveScope($get('scope'))))
                                            ->searchable()
                                            ->bulkToggleable()
                                            ->columns(3)
                                            ->columnSpanFull()
                                            ->required(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    private static function resolveScope(mixed $scope): WebhookScope
    {
        if ($scope instanceof WebhookScope) {
            return $scope;
        }

        return WebhookScope::tryFrom($scope ?? '') ?? WebhookScope::Global;
    }

    public static function sendHelpBanner(): void
    {
        AlertBanner::make('webhook_help')
            ->title(trans('admin/webhook.help'))
            ->body(trans('admin/webhook.help_text'))
            ->icon(TablerIcon::QuestionMark)
            ->info()
            ->send();
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListWebhookConfigurations::route('/'),
            'create' => CreateWebhookConfiguration::route('/create'),
            'view' => ViewWebhookConfiguration::route('/{record}'),
            'edit' => EditWebhookConfiguration::route('/{record}/edit'),
        ];
    }
}
