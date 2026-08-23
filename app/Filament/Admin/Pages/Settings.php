<?php

namespace App\Filament\Admin\Pages;

use App\Enums\TablerIcon;
use App\Extensions\Avatar\AvatarService;
use App\Extensions\Captcha\CaptchaService;
use App\Extensions\OAuth\OAuthService;
use App\Notifications\MailTested;
use App\Traits\EnvironmentWriterTrait;
use App\Traits\Filament\CanCustomizeHeaderActions;
use App\Traits\Filament\CanCustomizeHeaderWidgets;
use App\Traits\Filament\CanCustomizeTabs;
use BackedEnum;
use BladeUI\Icons\Exceptions\SvgNotFound;
use BladeUI\Icons\Factory as IconFactory;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\InteractsWithHeaderActions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\StateCasts\BooleanStateCast;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification as MailNotification;
use Illuminate\Support\Str;

/**
 * @property Schema $form
 */
class Settings extends Page implements HasSchemas
{
    use CanCustomizeHeaderActions, InteractsWithHeaderActions {
        CanCustomizeHeaderActions::getHeaderActions insteadof InteractsWithHeaderActions;
    }
    use CanCustomizeHeaderWidgets;
    use CanCustomizeTabs;
    use EnvironmentWriterTrait;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Settings;

    protected string $view = 'filament.pages.settings';

    protected OAuthService $oauthService;

    protected AvatarService $avatarService;

    protected CaptchaService $captchaService;

    protected IconFactory $iconFactory;

    /** @var array<mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function boot(OAuthService $oauthService, AvatarService $avatarService, CaptchaService $captchaService, IconFactory $iconFactory): void
    {
        $this->oauthService = $oauthService;
        $this->avatarService = $avatarService;
        $this->captchaService = $captchaService;
        $this->iconFactory = $iconFactory;
    }

    public static function canAccess(): bool
    {
        return user()?->can('view settings') ?? false;
    }

    public function getTitle(): string
    {
        return trans('admin/setting.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/setting.title');
    }

    /** @return array<Component> */
    protected function getFormSchema(): array
    {
        return [
            Tabs::make('Tabs')
                ->columns()
                ->persistTabInQueryString()
                ->disabled(fn () => !user()?->can('update settings'))
                ->tabs($this->getTabs()),
        ];
    }

    /**
     * @return Tab[]
     *
     * @throws Exception
     */
    protected function getDefaultTabs(): array
    {
        return [
            Tab::make('general')
                ->label(trans('admin/setting.navigation.general'))
                ->icon(TablerIcon::Home)
                ->schema($this->generalSettings()),
            Tab::make('captcha')
                ->label(trans('admin/setting.navigation.captcha'))
                ->icon(TablerIcon::Shield)
                ->schema($this->captchaSettings())
                ->columns(1),
            Tab::make('mail')
                ->label(trans('admin/setting.navigation.mail'))
                ->icon(TablerIcon::Mail)
                ->schema($this->mailSettings()),
            Tab::make('backup')
                ->label(trans('admin/setting.navigation.backup'))
                ->icon(TablerIcon::Box)
                ->schema($this->backupSettings()),
            Tab::make('oauth')
                ->label(trans('admin/setting.navigation.oauth'))
                ->icon(TablerIcon::BrandOauth)
                ->schema($this->oauthSettings())
                ->columns(1),
            Tab::make('misc')
                ->label(trans('admin/setting.navigation.misc'))
                ->icon(TablerIcon::Tool)
                ->schema($this->miscSettings()),
        ];
    }

    /** @return Component[]
     * @throws Exception
     */
    private function generalSettings(): array
    {
        return [
            TextInput::make('APP_NAME')
                ->label(trans('admin/setting.general.app_name'))
                ->required()
                ->default(env('APP_NAME', 'Pelican')),
            Group::make()
                ->columns(2)
                ->schema([
                    TextInput::make('APP_LOGO')
                        ->label(trans('admin/setting.general.app_logo'))
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/setting.general.app_logo_help'))
                        ->default(env('APP_LOGO'))
                        ->placeholder('/pelican.svg'),
                    TextInput::make('APP_FAVICON')
                        ->label(trans('admin/setting.general.app_favicon'))
                        ->hintIcon(TablerIcon::QuestionMark, trans('admin/setting.general.app_favicon_help'))
                        ->required()
                        ->default(env('APP_FAVICON', '/pelican.ico'))
                        ->placeholder('/pelican.ico'),
                ]),
            Group::make()
                ->columns(2)
                ->schema([
                    Toggle::make('APP_DEBUG')
                        ->label(trans('admin/setting.general.debug_mode'))
                        ->inline(false)
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->stateCast(new BooleanStateCast(false))
                        ->default(env('APP_DEBUG', config('app.debug'))),
                ]),
            Group::make()
                ->columns(2)
                ->schema([
                    Select::make('FILAMENT_AVATAR_PROVIDER')
                        ->label(trans('admin/setting.general.avatar_provider'))
                        ->options($this->avatarService->getMappings())
                        ->selectablePlaceholder(false)
                        ->default(env('FILAMENT_AVATAR_PROVIDER', config('panel.filament.avatar-provider'))),
                    Toggle::make('FILAMENT_UPLOADABLE_AVATARS')
                        ->label(trans('admin/setting.general.uploadable_avatars'))
                        ->inline(false)
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->stateCast(new BooleanStateCast(false))
                        ->default(env('FILAMENT_UPLOADABLE_AVATARS', config('panel.filament.uploadable-avatars'))),
                ]),
            ToggleButtons::make('PANEL_USE_BINARY_PREFIX')
                ->label(trans('admin/setting.general.unit_prefix'))
                ->inline()
                ->options([
                    0 => trans('admin/setting.general.decimal_prefix'),
                    1 => trans('admin/setting.general.binary_prefix'),
                ])
                ->stateCast(new BooleanStateCast(false, true))
                ->default(env('PANEL_USE_BINARY_PREFIX', config('panel.use_binary_prefix'))),
            ToggleButtons::make('FILAMENT_DEFAULT_NAVIGATION')
                ->label(trans('admin/setting.general.default_navigation'))
                ->inline()
                ->options([
                    'sidebar' => trans('admin/setting.general.sidebar'),
                    'topbar' => trans('admin/setting.general.topbar'),
                    'mixed' => trans('admin/setting.general.mixed'),
                ])
                ->default(env('FILAMENT_DEFAULT_NAVIGATION', config('panel.filament.default-navigation'))),
            ToggleButtons::make('APP_2FA_REQUIRED')
                ->label(trans('admin/setting.general.2fa_requirement'))
                ->inline()
                ->options([
                    0 => trans('admin/setting.general.not_required'),
                    1 => trans('admin/setting.general.admins_only'),
                    2 => trans('admin/setting.general.all_users'),
                ])
                ->formatStateUsing(fn ($state): int => (int) $state)
                ->afterStateUpdated(fn ($state, Set $set) => $set('APP_2FA_REQUIRED', (int) $state))
                ->default(env('APP_2FA_REQUIRED', config('panel.auth.2fa_required'))),
            Select::make('FILAMENT_WIDTH')
                ->label(trans('admin/setting.general.display_width'))
                ->options(Width::class)
                ->selectablePlaceholder(false)
                ->default(env('FILAMENT_WIDTH', config('panel.filament.display-width'))),
            TagsInput::make('TRUSTED_PROXIES')
                ->label(trans('admin/setting.general.trusted_proxies'))
                ->separator()
                ->splitKeys(['Tab', ' '])
                ->placeholder(trans('admin/setting.general.trusted_proxies_help'))
                ->default(env('TRUSTED_PROXIES', implode(',', Arr::wrap(config('trustedproxy.proxies')))))
                ->hintActions([
                    Action::make('hint_clear')
                        ->label(trans('admin/setting.general.clear'))
                        ->color('danger')
                        ->icon(TablerIcon::Trash)
                        ->requiresConfirmation()
                        ->authorize(fn () => user()?->can('update settings'))
                        ->action(fn (Set $set) => $set('TRUSTED_PROXIES', [])),
                    Action::make('hint_cloudflare')
                        ->label(trans('admin/setting.general.set_to_cf'))
                        ->icon(TablerIcon::BrandCloudflare)
                        ->authorize(fn () => user()?->can('update settings'))
                        ->action(function (Factory $client, Set $set) {
                            $ips = collect();

                            try {
                                $response = $client
                                    ->timeout(3)
                                    ->connectTimeout(3)
                                    ->get('https://api.cloudflare.com/client/v4/ips');

                                if ($response->status() === 200) {
                                    $result = $response->json('result');
                                    foreach (['ipv4_cidrs', 'ipv6_cidrs'] as $value) {
                                        $ips->push(...data_get($result, $value));
                                    }
                                    $ips->unique();
                                }
                            } catch (Exception) {
                            }

                            $set('TRUSTED_PROXIES', $ips->values()->all());
                        }),
                ]),
        ];
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private function captchaSettings(): array
    {
        $formFields = [];

        $captchaSchemas = $this->captchaService->getAll();
        foreach ($captchaSchemas as $schema) {
            $id = Str::upper($schema->getId());

            $formFields[] = Section::make($schema->getName())
                ->columns(5)
                ->icon($schema->getIcon() ?? TablerIcon::Shield)
                ->collapsed(fn () => !$schema->isEnabled())
                ->collapsible()
                ->schema([
                    Hidden::make("CAPTCHA_{$id}_ENABLED")
                        ->live()
                        ->default(env("CAPTCHA_{$id}_ENABLED")),
                    Actions::make([
                        Action::make("disable_captcha_$id")
                            ->visible(fn (Get $get) => $get("CAPTCHA_{$id}_ENABLED"))
                            ->disabled(fn () => !user()?->can('update settings'))
                            ->label(trans('admin/setting.captcha.disable'))
                            ->color('danger')
                            ->action(fn (Set $set) => $set("CAPTCHA_{$id}_ENABLED", false)),
                        Action::make("enable_captcha_$id")
                            ->visible(fn (Get $get) => !$get("CAPTCHA_{$id}_ENABLED"))
                            ->disabled(fn () => !user()?->can('update settings'))
                            ->label(trans('admin/setting.captcha.enable'))
                            ->color('success')
                            ->action(fn (Set $set) => $set("CAPTCHA_{$id}_ENABLED", true)),
                    ])->columnSpan(1),
                    Group::make($schema->getSettingsForm())
                        ->visible(fn (Get $get) => $get("CAPTCHA_{$id}_ENABLED"))
                        ->columns(4)
                        ->columnSpan(4),
                ]);
        }

        return $formFields;
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private function mailSettings(): array
    {
        return [
            ToggleButtons::make('MAIL_MAILER')
                ->label(trans('admin/setting.mail.mail_driver'))
                ->columnSpanFull()
                ->inline()
                ->options([
                    'log' => '/storage/logs Directory',
                    'smtp' => 'SMTP Server',
                    'mailgun' => 'Mailgun',
                    'mandrill' => 'Mandrill',
                    'postmark' => 'Postmark',
                    'sendmail' => 'sendmail (PHP)',
                ])
                ->live()
                ->default(env('MAIL_MAILER', config('mail.default')))
                ->hintAction(
                    Action::make('hint_test')
                        ->label(trans('admin/setting.mail.test_mail'))
                        ->icon(TablerIcon::Send)
                        ->hidden(fn (Get $get) => $get('MAIL_MAILER') === 'log')
                        ->authorize(fn () => user()?->can('update settings'))
                        ->action(function (Get $get) {
                            // Store original mail configuration
                            $originalConfig = [
                                'mail.default' => config('mail.default'),
                                'mail.mailers.smtp.host' => config('mail.mailers.smtp.host'),
                                'mail.mailers.smtp.port' => config('mail.mailers.smtp.port'),
                                'mail.mailers.smtp.username' => config('mail.mailers.smtp.username'),
                                'mail.mailers.smtp.password' => config('mail.mailers.smtp.password'),
                                'mail.mailers.smtp.scheme' => config('mail.mailers.smtp.scheme'),
                                'mail.from.address' => config('mail.from.address'),
                                'mail.from.name' => config('mail.from.name'),
                                'services.mailgun.domain' => config('services.mailgun.domain'),
                                'services.mailgun.secret' => config('services.mailgun.secret'),
                                'services.mailgun.endpoint' => config('services.mailgun.endpoint'),
                            ];

                            try {
                                // Update mail configuration dynamically
                                config([
                                    'mail.default' => $get('MAIL_MAILER'),
                                    'mail.mailers.smtp.host' => $get('MAIL_HOST'),
                                    'mail.mailers.smtp.port' => $get('MAIL_PORT'),
                                    'mail.mailers.smtp.username' => $get('MAIL_USERNAME'),
                                    'mail.mailers.smtp.password' => $get('MAIL_PASSWORD'),
                                    'mail.mailers.smtp.scheme' => $get('MAIL_SCHEME'),
                                    'mail.from.address' => $get('MAIL_FROM_ADDRESS'),
                                    'mail.from.name' => $get('MAIL_FROM_NAME'),
                                    'services.mailgun.domain' => $get('MAILGUN_DOMAIN'),
                                    'services.mailgun.secret' => $get('MAILGUN_SECRET'),
                                    'services.mailgun.endpoint' => $get('MAILGUN_ENDPOINT'),
                                ]);

                                MailNotification::route('mail', user()?->email)
                                    ->notify(new MailTested(user()));

                                Notification::make()
                                    ->title(trans('admin/setting.mail.test_mail_sent'))
                                    ->success()
                                    ->send();
                            } catch (Exception $exception) {
                                Notification::make()
                                    ->title(trans('admin/setting.mail.test_mail_failed'))
                                    ->body($exception->getMessage())
                                    ->danger()
                                    ->send();
                            } finally {
                                config($originalConfig);
                            }
                        })
                ),
            Section::make(trans('admin/setting.mail.from_settings'))
                ->description(trans('admin/setting.mail.from_settings_help'))
                ->columns()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('MAIL_FROM_ADDRESS')
                        ->label(trans('admin/setting.mail.from_address'))
                        ->required()
                        ->email()
                        ->default(env('MAIL_FROM_ADDRESS', config('mail.from.address'))),
                    TextInput::make('MAIL_FROM_NAME')
                        ->label(trans('admin/setting.mail.from_name'))
                        ->required()
                        ->default(env('MAIL_FROM_NAME', config('mail.from.name'))),
                ]),
            Section::make(trans('admin/setting.mail.smtp.smtp_title'))
                ->columns()
                ->columnSpanFull()
                ->visible(fn (Get $get) => $get('MAIL_MAILER') === 'smtp')
                ->schema([
                    TextInput::make('MAIL_HOST')
                        ->label(trans('admin/setting.mail.smtp.host'))
                        ->required()
                        ->default(env('MAIL_HOST', config('mail.mailers.smtp.host'))),
                    TextInput::make('MAIL_PORT')
                        ->label(trans('admin/setting.mail.smtp.port'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->default(env('MAIL_PORT', config('mail.mailers.smtp.port'))),
                    TextInput::make('MAIL_USERNAME')
                        ->label(trans('admin/setting.mail.smtp.username'))
                        ->default(env('MAIL_USERNAME', config('mail.mailers.smtp.username'))),
                    TextInput::make('MAIL_PASSWORD')
                        ->label(trans('admin/setting.mail.smtp.password'))
                        ->password()
                        ->revealable()
                        ->default(env('MAIL_PASSWORD')),
                    ToggleButtons::make('MAIL_SCHEME')
                        ->label(trans('admin/setting.mail.smtp.scheme'))
                        ->inline()
                        ->options([
                            'smtp' => 'SMTP',
                            'smtps' => 'SMTPS',
                        ])
                        ->default(env('MAIL_SCHEME', config('mail.mailers.smtp.scheme')))
                        ->live()
                        ->afterStateUpdated(function ($state, Set $set) {
                            $set('MAIL_PORT', $state === 'smtps' ? 587 : 2525);
                        }),
                ]),
            Section::make(trans('admin/setting.mail.mailgun.mailgun_title'))
                ->columns()
                ->columnSpanFull()
                ->visible(fn (Get $get) => $get('MAIL_MAILER') === 'mailgun')
                ->schema([
                    TextInput::make('MAILGUN_DOMAIN')
                        ->label(trans('admin/setting.mail.mailgun.domain'))
                        ->required()
                        ->default(env('MAILGUN_DOMAIN', config('services.mailgun.domain'))),
                    TextInput::make('MAILGUN_SECRET')
                        ->label(trans('admin/setting.mail.mailgun.secret'))
                        ->required()
                        ->default(env('MAILGUN_SECRET', config('services.mailgun.secret'))),
                    TextInput::make('MAILGUN_ENDPOINT')
                        ->label(trans('admin/setting.mail.mailgun.endpoint'))
                        ->required()
                        ->default(env('MAILGUN_ENDPOINT', config('services.mailgun.endpoint'))),
                ]),
        ];
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private function backupSettings(): array
    {
        return [
            Section::make(trans('admin/setting.backup.throttle'))
                ->description(trans('admin/setting.backup.throttle_help'))
                ->columns()
                ->columnSpanFull()
                ->schema([
                    TextInput::make('BACKUP_THROTTLE_LIMIT')
                        ->label(trans('admin/setting.backup.limit'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->default(config('backups.throttles.limit')),
                    TextInput::make('BACKUP_THROTTLE_PERIOD')
                        ->label(trans('admin/setting.backup.period'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix('Seconds')
                        ->default(config('backups.throttles.period')),
                ]),
        ];
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private function oauthSettings(): array
    {
        $formFields = [];

        $oauthSchemas = $this->oauthService->getAll();
        foreach ($oauthSchemas as $schema) {
            $key = $schema->getConfigKey();

            $icon = $schema->getIcon();
            if (is_string($icon)) {
                try {
                    $this->iconFactory->svg($icon);
                } catch (SvgNotFound) {
                    $icon = null;
                }
            }

            $formFields[] = Section::make($schema->getName())
                ->columns(5)
                ->icon($icon ?? TablerIcon::BrandOauth)
                ->collapsed(fn () => !$schema->isEnabled())
                ->collapsible()
                ->schema([
                    Hidden::make($key)
                        ->live()
                        ->default($schema->isEnabled()),
                    Actions::make([
                        Action::make('disable_oauth_' . $schema->getId())
                            ->visible(fn (Get $get) => $get($key))
                            ->disabled(fn () => !user()?->can('update settings'))
                            ->label(trans('admin/setting.oauth.disable'))
                            ->color('danger')
                            ->action(fn (Set $set) => $set($key, false)),
                        Action::make('enable_oauth_'  . $schema->getId())
                            ->visible(fn (Get $get) => !$get($key))
                            ->disabled(fn () => !user()?->can('update settings'))
                            ->label(trans('admin/setting.oauth.enable'))
                            ->color('success')
                            ->steps($schema->getSetupSteps())
                            ->modalHeading(trans('admin/setting.oauth.enable_schema', ['schema' => $schema->getName()]))
                            ->modalSubmitActionLabel(trans('admin/setting.oauth.enable'))
                            ->modalCancelAction(false)
                            ->action(function ($data, Set $set) use ($key) {
                                $data = array_merge([
                                    $key => 'true',
                                ], $data);

                                foreach ($data as $key => $value) {
                                    $set($key, $value);
                                }
                            }),
                    ])->columnSpan(1),
                    Group::make($schema->getSettingsForm())
                        ->visible(fn (Get $get) => $get($key))
                        ->columns(4)
                        ->columnSpan(4),
                ]);
        }

        return $formFields;
    }

    /**
     * @return Component[]
     *
     * @throws Exception
     */
    private function miscSettings(): array
    {
        return [
            Section::make(trans('admin/setting.misc.auto_allocation.title'))
                ->description(trans('admin/setting.misc.auto_allocation.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('PANEL_CLIENT_ALLOCATIONS_ENABLED')
                        ->label(trans('admin/setting.misc.auto_allocation.question'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpanFull()
                        ->stateCast(new BooleanStateCast(false))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_ENABLED', config('panel.client_features.allocations.enabled'))),
                    Toggle::make('PANEL_CLIENT_ALLOCATIONS_CREATE_NEW')
                        ->label(trans('admin/setting.misc.auto_allocation.create_new'))
                        ->helperText(trans('admin/setting.misc.auto_allocation.create_new_help'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpanFull()
                        ->visible(fn (Get $get) => $get('PANEL_CLIENT_ALLOCATIONS_ENABLED'))
                        ->stateCast(new BooleanStateCast(false))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_CREATE_NEW', config('panel.client_features.allocations.create_new'))),
                    TextInput::make('PANEL_CLIENT_ALLOCATIONS_RANGE_START')
                        ->label(trans('admin/setting.misc.auto_allocation.start'))
                        ->required()
                        ->numeric()
                        ->minValue(1024)
                        ->maxValue(65535)
                        ->visible(fn (Get $get) => $get('PANEL_CLIENT_ALLOCATIONS_ENABLED'))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_RANGE_START')),
                    TextInput::make('PANEL_CLIENT_ALLOCATIONS_RANGE_END')
                        ->label(trans('admin/setting.misc.auto_allocation.end'))
                        ->required()
                        ->numeric()
                        ->minValue(1024)
                        ->maxValue(65535)
                        ->visible(fn (Get $get) => $get('PANEL_CLIENT_ALLOCATIONS_ENABLED'))
                        ->default(env('PANEL_CLIENT_ALLOCATIONS_RANGE_END')),
                ]),
            Section::make(trans('admin/setting.misc.mail_notifications.title'))
                ->description(trans('admin/setting.misc.mail_notifications.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('PANEL_SEND_ACCOUNT_CREATED_NOTIFICATION')
                        ->label(trans('admin/setting.misc.mail_notifications.account_created'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_ACCOUNT_CREATED_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_ACCOUNT_CREATED_NOTIFICATION', config('panel.email.send_account_created_notification'))),
                    Toggle::make('PANEL_SEND_ADDED_TO_SERVER_NOTIFICATION')
                        ->label(trans('admin/setting.misc.mail_notifications.added_to_server'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_ADDED_TO_SERVER_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_ADDED_TO_SERVER_NOTIFICATION', config('panel.email.send_added_to_server_notification'))),
                    Toggle::make('PANEL_SEND_REMOVED_FROM_SERVER_NOTIFICATION')
                        ->label(trans('admin/setting.misc.mail_notifications.removed_from_server'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_REMOVED_FROM_SERVER_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_REMOVED_FROM_SERVER_NOTIFICATION', config('panel.email.send_removed_from_server_notification'))),
                    Toggle::make('PANEL_SEND_INSTALL_NOTIFICATION')
                        ->label(trans('admin/setting.misc.mail_notifications.server_installed'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_INSTALL_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_INSTALL_NOTIFICATION', config('panel.email.send_install_notification'))),
                    Toggle::make('PANEL_SEND_REINSTALL_NOTIFICATION')
                        ->label(trans('admin/setting.misc.mail_notifications.server_reinstalled'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_SEND_REINSTALL_NOTIFICATION', (bool) $state))
                        ->default(env('PANEL_SEND_REINSTALL_NOTIFICATION', config('panel.email.send_reinstall_notification'))),
                ]),
            Section::make(trans('admin/setting.misc.connections.title'))
                ->description(trans('admin/setting.misc.connections.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('GUZZLE_TIMEOUT')
                        ->label(trans('admin/setting.misc.connections.request_timeout'))
                        ->required()
                        ->numeric()
                        ->minValue(15)
                        ->maxValue(60)
                        ->suffix(trans('admin/setting.misc.connections.seconds'))
                        ->default(env('GUZZLE_TIMEOUT', config('panel.guzzle.timeout'))),
                    TextInput::make('GUZZLE_CONNECT_TIMEOUT')
                        ->label(trans('admin/setting.misc.connections.connection_timeout'))
                        ->required()
                        ->numeric()
                        ->minValue(5)
                        ->maxValue(60)
                        ->suffix(trans('admin/setting.misc.connections.seconds'))
                        ->default(env('GUZZLE_CONNECT_TIMEOUT', config('panel.guzzle.connect_timeout'))),
                ]),
            Section::make(trans('admin/setting.misc.activity_log.title'))
                ->description(trans('admin/setting.misc.activity_log.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('APP_ACTIVITY_PRUNE_DAYS')
                        ->label(trans('admin/setting.misc.activity_log.prune_age'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->suffix(trans('admin/setting.misc.activity_log.days'))
                        ->default(env('APP_ACTIVITY_PRUNE_DAYS', config('activity.prune_days'))),
                    Toggle::make('APP_ACTIVITY_HIDE_ADMIN')
                        ->label(trans('admin/setting.misc.activity_log.log_admin'))
                        ->inline(false)
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->stateCast(new BooleanStateCast(false))
                        ->default(env('APP_ACTIVITY_HIDE_ADMIN', config('activity.hide_admin_activity'))),
                ]),
            Section::make(trans('admin/setting.misc.api.title'))
                ->description(trans('admin/setting.misc.api.helper'))
                ->columns(1)
                ->collapsible()
                ->collapsed()
                ->schema([
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.client_title'))
                        ->schema([
                            TextInput::make('APP_API_CLIENT_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_CLIENT_RATELIMIT', config('http.rate_limit.client'))),

                            TextInput::make('APP_API_CLIENT_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_CLIENT_RATELIMIT_PERIOD', config('http.rate_limit.client_period'))),
                            Text::make(trans('admin/setting.misc.api.client_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.application_title'))
                        ->schema([
                            TextInput::make('APP_API_APPLICATION_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_APPLICATION_RATELIMIT', config('http.rate_limit.application'))),

                            TextInput::make('APP_API_APPLICATION_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_APPLICATION_RATELIMIT_PERIOD', config('http.rate_limit.application_period'))),
                            Text::make(trans('admin/setting.misc.api.application_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.password_reset_title'))
                        ->schema([
                            TextInput::make('APP_API_PASSWORD_RESET_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_PASSWORD_RESET_RATELIMIT', config('http.rate_limit.password_reset'))),

                            TextInput::make('APP_API_PASSWORD_RESET_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_PASSWORD_RESET_RATELIMIT_PERIOD', config('http.rate_limit.password_reset_period'))),
                            Text::make(trans('admin/setting.misc.api.password_reset_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.websocket_title'))
                        ->schema([
                            TextInput::make('APP_API_WEBSOCKET_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_WEBSOCKET_RATELIMIT', config('http.rate_limit.websocket'))),

                            TextInput::make('APP_API_WEBSOCKET_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_WEBSOCKET_RATELIMIT_PERIOD', config('http.rate_limit.websocket_period'))),
                            Text::make(trans('admin/setting.misc.api.websocket_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.backup_restore_title'))
                        ->schema([
                            TextInput::make('APP_API_BACKUP_RESTORE_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_BACKUP_RESTORE_RATELIMIT', config('http.rate_limit.backup_restore'))),

                            TextInput::make('APP_API_BACKUP_RESTORE_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_BACKUP_RESTORE_RATELIMIT_PERIOD', config('http.rate_limit.backup_restore_period'))),
                            Text::make(trans('admin/setting.misc.api.backup_restore_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.database_create_title'))
                        ->schema([
                            TextInput::make('APP_API_DATABASE_CREATE_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_DATABASE_CREATE_RATELIMIT', config('http.rate_limit.database_create'))),

                            TextInput::make('APP_API_DATABASE_CREATE_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_DATABASE_CREATE_RATELIMIT_PERIOD', config('http.rate_limit.database_create_period'))),
                            Text::make(trans('admin/setting.misc.api.database_create_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.subuser_create_title'))
                        ->schema([
                            TextInput::make('APP_API_SUBUSER_CREATE_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_SUBUSER_CREATE_RATELIMIT', config('http.rate_limit.subuser_create'))),

                            TextInput::make('APP_API_SUBUSER_CREATE_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_SUBUSER_CREATE_RATELIMIT_PERIOD', config('http.rate_limit.subuser_create_period'))),
                            Text::make(trans('admin/setting.misc.api.subuser_create_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.file_pull_title'))
                        ->schema([
                            TextInput::make('APP_API_FILE_PULL_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_FILE_PULL_RATELIMIT', config('http.rate_limit.file_pull'))),

                            TextInput::make('APP_API_FILE_PULL_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_FILE_PULL_RATELIMIT_PERIOD', config('http.rate_limit.file_pull_period'))),
                            Text::make(trans('admin/setting.misc.api.file_pull_helper')),
                        ]),
                    Fieldset::make()
                        ->label(trans('admin/setting.misc.api.default_title'))
                        ->schema([
                            TextInput::make('APP_API_DEFAULT_RATELIMIT')
                                ->label(trans('admin/setting.misc.api.requests_per_period'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.requests'))->default(env('APP_API_DEFAULT_RATELIMIT', config('http.rate_limit.default'))),

                            TextInput::make('APP_API_DEFAULT_RATELIMIT_PERIOD')
                                ->label(trans('admin/setting.misc.api.period_length'))
                                ->required()
                                ->numeric()
                                ->minValue(1)
                                ->suffix(trans('admin/setting.misc.api.minutes'))->default(env('APP_API_DEFAULT_RATELIMIT_PERIOD', config('http.rate_limit.default_period'))),
                            Text::make(trans('admin/setting.misc.api.default_helper')),
                        ]),
                ]),
            Section::make(trans('admin/setting.misc.server.title'))
                ->description(trans('admin/setting.misc.server.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    Toggle::make('PANEL_EDITABLE_SERVER_DESCRIPTIONS')
                        ->label(trans('admin/setting.misc.server.edit_server_desc'))
                        ->onIcon(TablerIcon::Check)
                        ->offIcon(TablerIcon::X)
                        ->onColor('success')
                        ->offColor('danger')
                        ->live()
                        ->columnSpan(1)
                        ->formatStateUsing(fn ($state): bool => (bool) $state)
                        ->afterStateUpdated(fn ($state, Set $set) => $set('PANEL_EDITABLE_SERVER_DESCRIPTIONS', (bool) $state))
                        ->default(env('PANEL_EDITABLE_SERVER_DESCRIPTIONS', config('panel.editable_server_descriptions'))),
                    FileUpload::make('console_font')
                        ->hint(trans('admin/setting.misc.server.console_font_hint'))
                        ->label(trans('admin/setting.misc.server.console_font_upload'))
                        ->directory('fonts')
                        ->disk('public')
                        ->columnSpan(1)
                        ->maxFiles(1)
                        ->acceptedFileTypes(['font/*'])
                        ->preserveFilenames(),
                ]),
            Section::make(trans('admin/setting.misc.webhook.title'))
                ->description(trans('admin/setting.misc.webhook.helper'))
                ->columns()
                ->collapsible()
                ->collapsed()
                ->schema([
                    TextInput::make('APP_WEBHOOK_PRUNE_DAYS')
                        ->label(trans('admin/setting.misc.webhook.prune_age'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(365)
                        ->suffix(trans('admin/setting.misc.webhook.days'))
                        ->default(env('APP_WEBHOOK_PRUNE_DAYS', config('panel.webhook.prune_days'))),
                ]),
        ];
    }

    protected function getFormStatePath(): ?string
    {
        return 'data';
    }

    public function save(): void
    {
        abort_unless(user()?->can('update settings'), 403);

        try {
            $data = $this->form->getState();
            unset($data['console_font']);

            $data = array_map(function ($value) {
                // Convert bools to a string, so they are correctly written to the .env file
                if (is_bool($value)) {
                    return $value ? 'true' : 'false';
                }

                // Convert enum to its value
                if ($value instanceof BackedEnum) {
                    return $value->value;
                }

                return $value;
            }, $data);

            $this->writeToEnvironment($data);

            Artisan::call('queue:restart');

            $this->redirect($this->getUrl());

            Notification::make()
                ->title(trans('admin/setting.save_success'))
                ->success()
                ->send();
        } catch (Exception $exception) {
            Notification::make()
                ->title(trans('admin/setting.save_failed'))
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
    }

    /** @return array<Action|ActionGroup> */
    protected function getDefaultHeaderActions(): array
    {
        return [
            Action::make('save')
                ->hiddenLabel()
                ->icon(TablerIcon::DeviceFloppy)
                ->action('save')
                ->tooltip(trans('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->authorize(fn () => user()?->can('update settings'))
                ->keyBindings(['mod+s']),
        ];
    }
}
