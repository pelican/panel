<?php

namespace App\Filament\Server\Pages;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Components\Actions\PreviewStartupAction;
use App\Filament\Components\Forms\Fields\StartupVariable;
use App\Models\Server;
use App\Models\ServerVariable;
use BackedEnum;
use Exception;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

class Startup extends ServerFormPage
{
    protected static string|BackedEnum|null $navigationIcon = TablerIcon::PlayerPlay;

    protected static ?int $navigationSort = 10;

    /**
     * @throws Exception
     */
    public function form(Schema $schema): Schema
    {
        return parent::form($schema)
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->components([
                Hidden::make('previewing')
                    ->default(false),
                TextInput::make('custom_startup')
                    ->label(trans('server/startup.command'))
                    ->readOnly()
                    ->visible(fn (Server $server) => !in_array($server->startup, $server->egg->startup_commands))
                    ->formatStateUsing(fn () => 'Custom Startup')
                    ->hintAction(PreviewStartupAction::make()),
                Select::make('startup_select')
                    ->label(trans('server/startup.command'))
                    ->live()
                    ->visible(fn (Server $server) => in_array($server->startup, $server->egg->startup_commands))
                    ->disabled(fn (Server $server) => !user()?->can(SubuserPermission::StartupUpdate, $server))
                    ->formatStateUsing(fn (Server $server) => $server->startup)
                    ->afterStateUpdated(function ($state, Server $server, Set $set) {
                        abort_unless(user()?->can(SubuserPermission::StartupUpdate, $server), 403);
                        $original = $server->startup;
                        $server->forceFill(['startup' => $state])->saveOrFail();

                        $set('startup', $state);
                        $set('previewing', false);

                        if ($original !== $server->startup) {
                            $startups = array_flip($server->egg->startup_commands);
                            Activity::event('server:startup.command')
                                ->property(['old' => $startups[$original], 'new' => $startups[$state]])
                                ->log();
                        }

                        Notification::make()
                            ->title(trans('server/startup.notification_startup'))
                            ->body(trans('server/startup.notification_startup_body'))
                            ->success()
                            ->send();
                    })
                    ->options(fn (Server $server) => array_flip($server->egg->startup_commands))
                    ->selectablePlaceholder(false)
                    ->hintAction(PreviewStartupAction::make()),
                TextInput::make('custom_image')
                    ->label(trans('server/startup.docker_image'))
                    ->readOnly()
                    ->visible(fn (Server $server) => !in_array($server->image, $server->egg->docker_images))
                    ->formatStateUsing(fn (Server $server) => $server->image),
                Select::make('image')
                    ->label(trans('server/startup.docker_image'))
                    ->live()
                    ->visible(fn (Server $server) => in_array($server->image, $server->egg->docker_images))
                    ->disabled(fn (Server $server) => !user()?->can(SubuserPermission::StartupDockerImage, $server))
                    ->afterStateUpdated(function ($state, Server $server) {
                        abort_unless(user()?->can(SubuserPermission::StartupDockerImage, $server), 403);
                        $original = $server->image;
                        $server->forceFill(['image' => $state])->saveOrFail();

                        if ($original !== $server->image) {
                            Activity::event('server:startup.image')
                                ->property(['old' => $original, 'new' => $state])
                                ->log();
                        }

                        Notification::make()
                            ->title(trans('server/startup.notification_docker'))
                            ->body(trans('server/startup.notification_docker_body'))
                            ->success()
                            ->send();
                    })
                    ->options(function (Server $server) {
                        $images = $server->egg->docker_images;

                        return array_flip($images);
                    }),
                Textarea::make('startup')
                    ->hiddenLabel()
                    ->columnSpanFull()
                    ->autosize()
                    ->readOnly(),
                Section::make(trans('server/startup.variables'))
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('server_variables')
                            ->hiddenLabel()
                            ->relationship('serverVariables', function (Builder $query, Server $server) {
                                $server->ensureVariablesExist();

                                return $query->where('egg_variables.user_viewable', true)->orderByPowerJoins('variable.sort');
                            })
                            ->grid()
                            ->disabled(fn (Server $server) => !user()?->can(SubuserPermission::StartupUpdate, $server))
                            ->reorderable(false)->addable(false)->deletable(false)
                            ->schema([
                                StartupVariable::make('variable_value')
                                    ->fromRecord()
                                    ->afterStateUpdated(function ($state, ServerVariable $serverVariable) {
                                        $this->update($state, $serverVariable);
                                    }),
                            ])
                            ->columnSpan(6),
                    ]),
            ]);
    }

    protected function authorizeAccess(): void
    {
        abort_unless(user()?->can(SubuserPermission::StartupRead, Filament::getTenant()), 403);
    }

    public static function canAccess(): bool
    {
        return parent::canAccess() && user()?->can(SubuserPermission::StartupRead, Filament::getTenant());
    }

    public function update(null|string|bool $state, ServerVariable $serverVariable): void
    {
        abort_unless(user()?->can(SubuserPermission::StartupUpdate, $this->getRecord()), 403);
        abort_unless($serverVariable->server_id === $this->getRecord()->id, 403);
        abort_unless($serverVariable->variable->user_viewable, 403);

        if (!$serverVariable->variable->user_editable) {
            return;
        }

        if (is_bool($state)) {
            $state = $state ? '1' : '0';
        }

        $original = $serverVariable->variable_value;

        try {
            $validator = Validator::make(
                ['variable_value' => $state],
                ['variable_value' => $serverVariable->variable->rules]
            );

            if ($validator->fails()) {
                Notification::make()
                    ->title(trans('server/startup.validation_fail', ['variable' => $serverVariable->variable->name]))
                    ->body(implode(', ', $validator->errors()->all()))
                    ->danger()
                    ->send();

                return;
            }

            ServerVariable::query()->updateOrCreate([
                'server_id' => $this->getRecord()->id,
                'variable_id' => $serverVariable->variable->id,
            ], [
                'variable_value' => $state ?? '',
            ]);

            if ($original !== $state) {
                Activity::event('server:startup.edit')
                    ->property([
                        'variable' => $serverVariable->variable->env_variable,
                        'old' => $original,
                        'new' => $state,
                    ])
                    ->log();
            }

            Notification::make()
                ->title(trans('server/startup.update', ['variable' => $serverVariable->variable->name]))
                ->body(fn () => $original . ' -> ' . $state)
                ->success()
                ->send();
        } catch (Exception $e) {
            Notification::make()
                ->title(trans('server/startup.fail', ['variable' => $serverVariable->variable->name]))
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getTitle(): string
    {
        return trans('server/startup.title');
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/startup.title');
    }
}
