<?php

namespace App\Filament\Server\Resources\Webhooks;

use App\Enums\TablerIcon;
use App\Enums\WebhookScope;
use App\Extensions\Webhooks\WebhookForm;
use App\Facades\WebhookTypes;
use App\Filament\Server\Resources\Webhooks\Pages\CreateWebhook;
use App\Filament\Server\Resources\Webhooks\Pages\EditWebhook;
use App\Filament\Server\Resources\Webhooks\Pages\ListWebhooks;
use App\Filament\Server\Resources\Webhooks\Pages\ViewWebhook;
use App\Livewire\AlertBanner;
use App\Models\Server;
use App\Models\WebhookConfiguration;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ReplicateAction;
use Filament\Actions\ViewAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
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

    protected static string|\BackedEnum|null $navigationIcon = 'tabler-webhook';

    protected static ?int $navigationSort = 11;

    protected static ?string $slug = 'webhook';

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
        /** @var Server|null $server */
        $server = Filament::getTenant();
        if (!$server instanceof Server) {
            return null;
        }
        $count = static::getModel()::where('server_id', $server->id)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('type')
                    ->label(trans('admin/webhook.type'))
                    ->icon(fn (?string $state) => WebhookTypes::get($state)?->getIcon() ?? TablerIcon::PuzzleOff)
                    ->color(fn (?string $state) => WebhookTypes::get($state)?->getColor() ?? 'gray')
                    ->tooltip(fn (?string $state) => WebhookTypes::get($state)?->getLabel() ?? trans('admin/webhook.unavailable_type')),
                TextColumn::make('name')
                    ->label(trans('admin/webhook.name')),
                TextColumn::make('endpoint')
                    ->label(trans('admin/webhook.endpoint'))
                    ->formatStateUsing(fn (string $state) => str($state)->after('://'))
                    ->limit(60)
                    ->wrap(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn (WebhookConfiguration $record) => static::canEdit($record)),
                EditAction::make(),
                ReplicateAction::make()
                    ->iconButton()
                    ->tooltip(trans('filament-actions::replicate.single.label'))
                    ->modal(false)
                    ->excludeAttributes(['created_at', 'updated_at']),
            ])
            ->groupedBulkActions([
                DeleteBulkAction::make(),
            ])
            ->emptyStateIcon('tabler-webhook')
            ->emptyStateDescription('')
            ->emptyStateHeading(trans('admin/webhook.no_webhooks'))
            ->emptyStateActions([
                CreateAction::make(),
            ])
            ->persistFiltersInSession()
            ->filters([
                SelectFilter::make('type')
                    ->options(fn () => WebhookTypes::getOptions())
                    ->attribute('type'),
            ]);
    }

    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                WebhookForm::typeSelector(),
                TextInput::make('endpoint')
                    ->label(trans('admin/webhook.endpoint'))
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (?string $state, ?string $old, Get $get, Set $set) => $set('type', WebhookTypes::detectFor($state, $old, $get('type')))),
                TextInput::make('name')
                    ->label(trans('admin/webhook.name'))
                    ->columnSpanFull()
                    ->required(),
                Group::make()
                    ->columnSpanFull()
                    ->schema(fn (Get $get) => [
                        WebhookForm::payloadSection($get('type'), WebhookScope::Server),
                    ]),
                Section::make(trans('admin/webhook.events'))
                    ->schema([
                        CheckboxList::make('events')
                            ->live()
                            ->options(fn () => WebhookConfiguration::filamentCheckboxList(WebhookScope::Server))
                            ->searchable()
                            ->bulkToggleable()
                            ->columns(3)
                            ->columnSpanFull()
                            ->required(),
                    ]),
            ]);
    }

    public static function sendHelpBanner(): void
    {
        AlertBanner::make('webhook_help')
            ->title(trans('admin/webhook.help'))
            ->body(trans('admin/webhook.help_text'))
            ->icon('tabler-question-mark')
            ->info()
            ->send();
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListWebhooks::route('/'),
            'create' => CreateWebhook::route('/create'),
            'view' => ViewWebhook::route('/{record}'),
            'edit' => EditWebhook::route('/{record}/edit'),
        ];
    }
}
