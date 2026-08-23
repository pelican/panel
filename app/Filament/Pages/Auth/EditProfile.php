<?php

namespace App\Filament\Pages\Auth;

use App\Enums\CustomizationKey;
use App\Enums\TablerIcon;
use App\Extensions\OAuth\OAuthService;
use App\Facades\Activity;
use App\Models\ActivityLog;
use App\Models\ApiKey;
use App\Models\User;
use App\Models\UserSSHKey;
use App\Services\Helpers\LanguageService;
use App\Services\Ssh\KeyCreationService;
use App\Services\Users\UserUpdateService;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use DateTimeZone;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rules\Password;
use Laravel\Socialite\Facades\Socialite;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * @method User getUser()
 */
class EditProfile extends BaseEditProfile
{
    use CanCustomizeHeaderActions;
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;

    protected OAuthService $oauthService;

    protected Request $request;

    public function boot(OAuthService $oauthService, Request $request): void
    {
        $this->oauthService = $oauthService;
        $this->request = $request;
    }

    public function getMaxWidth(): Width|string
    {
        return config('panel.filament.display-width', 'screen-2xl');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getFormContentComponent(),
            ]);
    }

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make()
                    ->persistTabInQueryString()
                    ->tabs($this->getTabs()),
            ])
            ->operation('edit')
            ->model($this->getUser())
            ->statePath('data')
            ->inlineLabel(!static::isSimple());
    }

    /** @return Tab[] */
    protected function getDefaultTabs(): array
    {
        $oauthSchemas = $this->oauthService->getEnabled();

        return [
            Tab::make('account')
                ->label(trans('profile.tabs.account'))
                ->icon(TablerIcon::UserCog)
                ->schema([
                    TextInput::make('username')
                        ->disabled(fn (User $user) => $user->is_managed_externally)
                        ->prefixIcon(TablerIcon::User)
                        ->label(trans('profile.username'))
                        ->required()
                        ->maxLength(255)
                        ->unique(),
                    TextInput::make('email')
                        ->disabled(fn (User $user) => $user->is_managed_externally)
                        ->prefixIcon(TablerIcon::Mail)
                        ->label(trans('profile.email'))
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(),
                    TextInput::make('password')
                        ->hidden(fn (User $user) => $user->is_managed_externally)
                        ->label(trans('profile.password'))
                        ->password()
                        ->prefixIcon(TablerIcon::Password)
                        ->revealable(filament()->arePasswordsRevealable())
                        ->rule(Password::default())
                        ->autocomplete('new-password')
                        ->dehydrated(fn ($state) => filled($state))
                        ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                        ->live(debounce: 500)
                        ->same('passwordConfirmation'),
                    TextInput::make('passwordConfirmation')
                        ->label(trans('profile.password_confirmation'))
                        ->password()
                        ->prefixIcon(TablerIcon::PasswordFingerprint)
                        ->revealable(filament()->arePasswordsRevealable())
                        ->required()
                        ->visible(fn (Get $get) => filled($get('password')))
                        ->dehydrated(false),
                    Select::make('timezone')
                        ->label(trans('profile.timezone'))
                        ->required()
                        ->prefixIcon(TablerIcon::ClockPin)
                        ->default(config('app.timezone', 'UTC'))
                        ->selectablePlaceholder(false)
                        ->options(fn () => collect(DateTimeZone::listIdentifiers())->mapWithKeys(fn ($tz) => [$tz => $tz]))
                        ->searchable(),
                    Select::make('language')
                        ->label(trans('profile.language'))
                        ->required()
                        ->prefixIcon(TablerIcon::Flag)
                        ->live()
                        ->default('en')
                        ->selectablePlaceholder(false)
                        ->helperText(fn ($state, LanguageService $languageService) => new HtmlString($languageService->isLanguageTranslated($state) ? ''
                            : trans('profile.language_help', ['state' => $state]) . ' <u><a href="https://crowdin.com/project/pelican-dev/">Update On Crowdin</a></u>'))
                        ->options(fn (LanguageService $languageService) => $languageService->getAvailableLanguages()),
                    FileUpload::make('avatar')
                        ->visible(fn () => config('panel.filament.uploadable-avatars'))
                        ->avatar()
                        ->imageEditor()
                        ->acceptedFileTypes(['image/png'])
                        ->directory('avatars')
                        ->disk('public')
                        ->getUploadedFileNameForStorageUsing(fn () => $this->getUser()->id . '.png')
                        ->formatStateUsing(function (FileUpload $fileUpload) {
                            $path = $fileUpload->getDirectory() . '/' . $this->getUser()->id . '.png';
                            if ($fileUpload->getDisk()->exists($path)) {
                                return $path;
                            }
                        })
                        ->deleteUploadedFileUsing(function (FileUpload $fileUpload, $file) {
                            if ($file instanceof TemporaryUploadedFile) {
                                return $file->delete();
                            }

                            if ($fileUpload->getDisk()->exists($file)) {
                                return $fileUpload->getDisk()->delete($file);
                            }
                        }),
                ]),
            Tab::make('oauth')
                ->label(trans('profile.tabs.oauth'))
                ->icon(TablerIcon::BrandOauth)
                ->visible(count($oauthSchemas) > 0)
                ->schema(function () use ($oauthSchemas) {
                    $actions = [];

                    foreach ($oauthSchemas as $schema) {

                        $id = $schema->getId();
                        $name = $schema->getName();

                        $color = $schema->getHexColor();
                        $color = is_string($color) ? Color::hex($color) : null;

                        $unlink = array_key_exists($id, $this->getUser()->oauth ?? []);

                        $actions[] = Action::make("oauth_$id")
                            ->label(trans('profile.' . ($unlink ? 'unlink' : 'link'), ['name' => $name]))
                            ->icon($unlink ? TablerIcon::Unlink : TablerIcon::Link)
                            ->color($color)
                            ->action(function (UserUpdateService $updateService) use ($id, $name, $unlink) {
                                if ($unlink) {
                                    $oauth = user()?->oauth;
                                    unset($oauth[$id]);

                                    $updateService->handle(user(), ['oauth' => $oauth]);

                                    $this->fillForm();

                                    Notification::make()
                                        ->title(trans('profile.unlinked', ['name' => $name]))
                                        ->success()
                                        ->send();
                                } else {
                                    redirect(Socialite::with($id)->redirect()->getTargetUrl());
                                }
                            });
                    }

                    return [Actions::make($actions)];
                }),
            Tab::make('2fa')
                ->label(trans('profile.tabs.2fa'))
                ->icon(TablerIcon::ShieldLock)
                ->visible(fn () => Filament::hasMultiFactorAuthentication())
                ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                    ->sort(fn (MultiFactorAuthenticationProvider $multiFactorAuthenticationProvider) => $multiFactorAuthenticationProvider->isEnabled(Filament::auth()->user()) ? 0 : 1)
                    ->map(fn (MultiFactorAuthenticationProvider $multiFactorAuthenticationProvider) => Group::make($multiFactorAuthenticationProvider->getManagementSchemaComponents())
                        ->statePath($multiFactorAuthenticationProvider->getId()))
                    ->all()),
            Tab::make('passkeys')
                ->label(trans('profile.tabs.passkeys'))
                ->icon(TablerIcon::Fingerprint)
                ->hidden(fn () => !$this->request->isSecure())
                ->schema([
                    Section::make(trans('profile.tabs.passkeys'))
                        ->description(trans('passkeys.description'))
                        ->schema([
                            View::make('passkeys.livewire.passkeys-tab'),
                        ]),
                ]),
            Tab::make('api_keys')
                ->label(trans('profile.tabs.api_keys'))
                ->icon(TablerIcon::Key)
                ->schema([
                    Grid::make(5)
                        ->schema([
                            Section::make(trans('profile.create_api_key'))->columnSpan(3)
                                ->schema([
                                    TextInput::make('description')
                                        ->label(trans('profile.description'))
                                        ->live(),
                                    TagsInput::make('allowed_ips')
                                        ->label(trans('profile.allowed_ips'))
                                        ->live()
                                        ->splitKeys([',', ' ', 'Tab'])
                                        ->placeholder('127.0.0.1 or 192.168.1.1')
                                        ->helperText(trans('profile.allowed_ips_help'))
                                        ->columnSpanFull(),
                                ])
                                ->headerActions([
                                    Action::make('exclude_create_api_key')
                                        ->label(trans('filament-actions::create.single.modal.actions.create.label'))
                                        ->disabled(fn (Get $get) => empty($get('description')))
                                        ->successRedirectUrl(self::getUrl(['tab' => 'api-keys::data::tab'], panel: 'app'))
                                        ->action(function (Get $get, Action $action, User $user) {
                                            $token = $user->createToken(
                                                $get('description'),
                                                $get('allowed_ips'),
                                            );

                                            Activity::event('user:api-key.create')
                                                ->actor($user)
                                                ->subject($user)
                                                ->subject($token->accessToken)
                                                ->property('identifier', $token->accessToken->identifier)
                                                ->log();

                                            Notification::make()
                                                ->title(trans('profile.api_key_created'))
                                                ->body($token->accessToken->identifier . $token->plainTextToken)
                                                ->persistent()
                                                ->success()
                                                ->send();

                                            $action->success();
                                        }),
                                ]),
                            Section::make(trans('profile.api_keys'))->columnSpan(2)
                                ->schema([
                                    Repeater::make('api_keys')
                                        ->hiddenLabel()
                                        ->inlineLabel(false)
                                        ->relationship('apiKeys')
                                        ->addable(false)
                                        ->itemLabel(fn ($state) => $state['identifier'])
                                        ->deleteAction(function (Action $action) {
                                            $action->requiresConfirmation()->action(function (array $arguments, Repeater $component, User $user) {
                                                $items = $component->getState();
                                                $key = $items[$arguments['item']];

                                                $apiKey = ApiKey::find($key['id'] ?? null);
                                                if ($apiKey->exists()) {
                                                    $apiKey->delete();

                                                    Activity::event('user:api-key.delete')
                                                        ->actor($user)
                                                        ->subject($user)
                                                        ->subject($apiKey)
                                                        ->property('identifier', $apiKey->identifier)
                                                        ->log();
                                                }

                                                unset($items[$arguments['item']]);

                                                $component->state($items);

                                                $component->callAfterStateUpdated();
                                            });
                                        })
                                        ->schema(fn () => [
                                            TextEntry::make('memo')
                                                ->hiddenLabel()
                                                ->state(fn (ApiKey $key) => $key->memo),
                                        ])
                                        ->visible(fn (User $user) => $user->apiKeys()->exists()),

                                    TextEntry::make('no_api_keys')
                                        ->state(trans('profile.no_api_keys'))
                                        ->hiddenLabel()
                                        ->visible(fn (User $user) => !$user->apiKeys()->exists()),
                                ]),
                        ]),
                ]),
            Tab::make('ssh_keys')
                ->label(trans('profile.tabs.ssh_keys'))
                ->icon(TablerIcon::LockCode)
                ->schema([
                    Grid::make(5)->schema([
                        Section::make(trans('profile.create_ssh_key'))->columnSpan(3)
                            ->schema([
                                TextInput::make('name')
                                    ->label(trans('profile.name'))
                                    ->live(),
                                Textarea::make('public_key')
                                    ->label(trans('profile.public_key'))
                                    ->autosize()
                                    ->live(),
                            ])
                            ->headerActions([
                                Action::make('exclude_create_ssh_key')
                                    ->label(trans('filament-actions::create.single.modal.actions.create.label'))
                                    ->disabled(fn (Get $get) => empty($get('name')) || empty($get('public_key')))
                                    ->successRedirectUrl(self::getUrl(['tab' => 'ssh-keys::data::tab'], panel: 'app'))
                                    ->action(function (Get $get, Action $action, User $user, KeyCreationService $service) {
                                        try {
                                            $sshKey = $service->handle($user, $get('name'), $get('public_key'));

                                            Activity::event('user:ssh-key.create')
                                                ->actor($user)
                                                ->subject($user)
                                                ->subject($sshKey)
                                                ->property('fingerprint', $sshKey->fingerprint)
                                                ->log();

                                            Notification::make()
                                                ->title(trans('profile.ssh_key_created'))
                                                ->body("SHA256:{$sshKey->fingerprint}")
                                                ->success()
                                                ->send();

                                            $action->success();
                                        } catch (Exception $exception) {
                                            Notification::make()
                                                ->title(trans('profile.could_not_create_ssh_key'))
                                                ->body($exception->getMessage())
                                                ->danger()
                                                ->send();

                                            $action->failure();
                                        }
                                    }),
                            ]),
                        Section::make(trans('profile.ssh_keys'))->columnSpan(2)
                            ->schema([
                                Repeater::make('ssh_keys')
                                    ->hiddenLabel()
                                    ->inlineLabel(false)
                                    ->relationship('sshKeys')
                                    ->addable(false)
                                    ->itemLabel(fn ($state) => $state['name'])
                                    ->deleteAction(function (Action $action) {
                                        $action->requiresConfirmation()->action(function (array $arguments, Repeater $component, User $user) {
                                            $items = $component->getState();
                                            $key = $items[$arguments['item']];

                                            $sshKey = UserSSHKey::find($key['id'] ?? null);
                                            if ($sshKey->exists()) {
                                                $sshKey->delete();

                                                Activity::event('user:ssh-key.delete')
                                                    ->actor($user)
                                                    ->subject($user)
                                                    ->subject($sshKey)
                                                    ->property('fingerprint', $sshKey->fingerprint)
                                                    ->log();
                                            }

                                            unset($items[$arguments['item']]);

                                            $component->state($items);

                                            $component->callAfterStateUpdated();
                                        });
                                    })
                                    ->schema(fn () => [
                                        TextEntry::make('fingerprint')
                                            ->hiddenLabel()
                                            ->state(fn (UserSSHKey $key) => "SHA256:{$key->fingerprint}"),
                                    ])
                                    ->visible(fn (User $user) => $user->sshKeys()->exists()),

                                TextEntry::make('no_ssh_keys')
                                    ->state(trans('profile.no_ssh_keys'))
                                    ->hiddenLabel()
                                    ->visible(fn (User $user) => !$user->sshKeys()->exists()),
                            ]),
                    ]),
                ]),
            Tab::make('activity')
                ->label(trans('profile.tabs.activity'))
                ->icon(TablerIcon::History)
                ->schema([
                    Repeater::make('activity') // TODO: move to a table
                        ->label(trans('profile.activity_info'))
                        ->inlineLabel(false)
                        ->deletable(false)
                        ->addable(false)
                        ->relationship(null, function (Builder $query) {
                            $query->orderByDesc('timestamp')->limit(50);
                        })
                        ->schema([
                            TextEntry::make('log')
                                ->hiddenLabel()
                                ->state(fn (ActivityLog $record) => new HtmlString($record->htmlable())),
                        ]),
                ]),
            Tab::make('customization')
                ->label(trans('profile.tabs.customization'))
                ->icon(TablerIcon::Adjustments)
                ->schema([
                    Section::make(trans('profile.dashboard'))
                        ->collapsible()
                        ->icon(TablerIcon::Dashboard)
                        ->schema([
                            ToggleButtons::make('dashboard_layout')
                                ->label(trans('profile.dashboard_layout'))
                                ->inline()
                                ->required()
                                ->options([
                                    'grid' => trans('profile.grid'),
                                    'table' => trans('profile.table'),
                                ]),
                            ToggleButtons::make('top_navigation')
                                ->label(trans('profile.navigation'))
                                ->inline()
                                ->options([
                                    'sidebar' => trans('profile.sidebar'),
                                    'topbar' => trans('profile.topbar'),
                                    'mixed' => trans('profile.mixed'),
                                ]),
                            ToggleButtons::make('button_style')
                                ->label(trans('profile.button_style'))
                                ->inline()
                                ->default('icon')
                                ->options([
                                    true => trans('profile.icon'),
                                    false => trans('profile.icon_button'),
                                ]),
                        ]),
                    Section::make(trans('profile.admin'))
                        ->collapsible()
                        ->icon(TablerIcon::Shield)
                        ->visible(fn (User $user) => $user->isAdmin())
                        ->schema([
                            ToggleButtons::make('redirect_to_admin')
                                ->label(trans('profile.redirect_to_admin'))
                                ->helperText(trans('profile.redirect_to_admin_help'))
                                ->inline()
                                ->boolean(),
                        ]),
                    Section::make(trans('profile.console'))
                        ->collapsible()
                        ->icon(TablerIcon::Terminal2)
                        ->columns(4)
                        ->schema([
                            TextInput::make('console_font_size')
                                ->label(trans('profile.font_size'))
                                ->columnSpan(1)
                                ->minValue(1)
                                ->numeric()
                                ->required()
                                ->live()
                                ->default(14),
                            Select::make('console_font')
                                ->label(trans('profile.font'))
                                ->required()
                                ->options(function () {
                                    $fonts = [
                                        'monospace' => 'monospace', //default
                                    ];

                                    if (!Storage::disk('public')->exists('fonts')) {
                                        Storage::disk('public')->makeDirectory('fonts');
                                        $this->fillForm();
                                    }

                                    foreach (Storage::disk('public')->allFiles('fonts') as $file) {
                                        $fileInfo = pathinfo($file);

                                        if ($fileInfo['extension'] === 'ttf') {
                                            $fonts[$fileInfo['filename']] = $fileInfo['filename'];
                                        }
                                    }

                                    return $fonts;
                                })
                                ->live()
                                ->default('monospace'),
                            TextEntry::make('font_preview')
                                ->label(trans('profile.font_preview'))
                                ->columnSpan(2)
                                ->state(function (Get $get) {
                                    $fontName = $get('console_font') ?? 'monospace';
                                    $fontSize = $get('console_font_size') . 'px';
                                    $style = <<<CSS
                                                            .preview-text {
                                                                font-family: $fontName;
                                                                font-size: $fontSize;
                                                                margin-top: 10px;
                                                                display: block;
                                                            }
                                                        CSS;
                                    if ($fontName !== 'monospace') {
                                        $fontUrl = asset("storage/fonts/$fontName.ttf");
                                        $style = <<<CSS
                                                                @font-face {
                                                                    font-family: $fontName;
                                                                    src: url("$fontUrl");
                                                                }
                                                                $style
                                                            CSS;
                                    }

                                    return new HtmlString(<<<HTML
                                                            <style>
                                                            {$style}
                                                            </style>
                                                            <span class="preview-text">The quick blue pelican jumps over the lazy pterodactyl. :)</span>
                                                        HTML);
                                }),
                            TextInput::make('console_graph_period')
                                ->label(trans('profile.graph_period'))
                                ->suffix(trans('profile.seconds'))
                                ->hintIcon(TablerIcon::QuestionMark, trans('profile.graph_period_helper'))
                                ->columnSpan(2)
                                ->numeric()
                                ->default(30)
                                ->minValue(10)
                                ->maxValue(120)
                                ->required(),
                            TextInput::make('console_rows')
                                ->label(trans('profile.rows'))
                                ->minValue(1)
                                ->numeric()
                                ->required()
                                ->columnSpan(2)
                                ->default(30),
                        ]),
                ]),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            $this->getCancelFormAction()->formId('form')
                ->tooltip(trans('filament-panels::auth/pages/edit-profile.actions.cancel.label'))
                ->hiddenLabel()
                ->icon(TablerIcon::ArrowLeft),
            Action::make('save')
                ->hiddenLabel()
                ->action('save')
                ->keyBindings(['mod+s'])
                ->tooltip(trans('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->icon(TablerIcon::DeviceFloppy),
        ];

    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $customization = [
            'console_font' => $data['console_font'],
            'console_font_size' => $data['console_font_size'],
            'console_rows' => $data['console_rows'],
            'console_graph_period' => $data['console_graph_period'],
            'dashboard_layout' => $data['dashboard_layout'],
            'top_navigation' => $data['top_navigation'],
            'button_style' => $data['button_style'],
            'redirect_to_admin' => $data['redirect_to_admin'] ?? $this->getUser()->getCustomization(CustomizationKey::RedirectToAdmin),
        ];

        unset(
            $data['console_font'],
            $data['console_font_size'],
            $data['console_rows'],
            $data['dashboard_layout'],
            $data['top_navigation'],
            $data['button_style'],
            $data['redirect_to_admin'],
        );

        $data['customization'] = json_encode($customization);

        return $data;
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['console_font'] = $this->getUser()->getCustomization(CustomizationKey::ConsoleFont);
        $data['console_font_size'] = (int) $this->getUser()->getCustomization(CustomizationKey::ConsoleFontSize);
        $data['console_rows'] = (int) $this->getUser()->getCustomization(CustomizationKey::ConsoleRows);
        $data['console_graph_period'] = (int) $this->getUser()->getCustomization(CustomizationKey::ConsoleGraphPeriod);
        $data['dashboard_layout'] = $this->getUser()->getCustomization(CustomizationKey::DashboardLayout);
        $data['button_style'] = $this->getUser()->getCustomization(CustomizationKey::ButtonStyle);
        $data['redirect_to_admin'] = $this->getUser()->getCustomization(CustomizationKey::RedirectToAdmin);

        // Handle migration from boolean to string navigation types
        $topNavigation = $this->getUser()->getCustomization(CustomizationKey::TopNavigation);
        if (is_bool($topNavigation)) {
            $data['top_navigation'] = $topNavigation ? 'topbar' : 'sidebar';
        } else {
            $data['top_navigation'] = $topNavigation;
        }

        return $data;
    }
}
