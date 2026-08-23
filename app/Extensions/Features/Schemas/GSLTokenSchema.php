<?php

namespace App\Extensions\Features\Schemas;

use App\Enums\SubuserPermission;
use App\Enums\TablerIcon;
use App\Extensions\Features\FeatureSchemaInterface;
use App\Facades\Activity;
use App\Models\Server;
use App\Models\ServerVariable;
use App\Models\User;
use App\Repositories\Daemon\DaemonServerRepository;
use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\HtmlString;

class GSLTokenSchema implements FeatureSchemaInterface
{
    /** @return array<string> */
    public function getListeners(): array
    {
        return [
            '(gsl token expired)',
            '(account not found)',
        ];
    }

    public function getId(): string
    {
        return 'gsl_token';
    }

    public function authorize(User $user, Server $server): bool
    {
        return $user->can(SubuserPermission::StartupUpdate, $server);
    }

    /**
     * @throws Exception
     */
    public function getAction(): Action
    {
        /** @var Server $server */
        $server = Filament::getTenant();

        /** @var ServerVariable $serverVariable */
        $serverVariable = $server->serverVariables()->whereHas('variable', function (Builder $query) {
            $query->where('env_variable', 'STEAM_ACC');
        })->first();

        return Action::make($this->getId())
            ->requiresConfirmation()
            ->modalHeading(trans('server/feature.gsl_token.heading'))
            ->modalDescription(trans('server/feature.gsl_token.description'))
            ->modalSubmitActionLabel(trans('server/feature.gsl_token.submit'))
            ->schema([
                TextEntry::make('info')
                    ->label(new HtmlString(Blade::render(trans('server/feature.gsl_token.info')))),
                TextInput::make('gsltoken')
                    ->label('GSL Token')
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) use ($serverVariable) {
                            $validator = Validator::make(['validatorkey' => $value], [
                                'validatorkey' => $serverVariable->variable->rules,
                            ]);

                            if ($validator->fails()) {
                                $message = str($validator->errors()->first())->replace('validatorkey', $serverVariable->variable->name);

                                $fail($message);
                            }
                        },
                    ])
                    ->hintIcon(TablerIcon::Code, fn () => implode('|', $serverVariable->variable->rules))
                    ->label(fn () => $serverVariable->variable->name)
                    ->prefix(fn () => '{{' . $serverVariable->variable->env_variable . '}}')
                    ->helperText(fn () => empty($serverVariable->variable->description) ? '—' : $serverVariable->variable->description),
            ])
            ->action(function (array $data, DaemonServerRepository $serverRepository) use ($server, $serverVariable) {
                /** @var Server $server */
                $server = Filament::getTenant();
                try {
                    $new = $data['gsltoken'] ?? '';
                    $original = $serverVariable->variable_value;

                    $serverVariable->update([
                        'variable_value' => $new,
                    ]);

                    if ($original !== $new) {

                        Activity::event('server:startup.edit')
                            ->property([
                                'variable' => $serverVariable->variable->env_variable,
                                'old' => $original,
                                'new' => $new,
                            ])
                            ->log();
                    }

                    $serverRepository->setServer($server)->power('restart');

                    Notification::make()
                        ->title(trans('server/feature.gsl_token.updated'))
                        ->body(trans('server/feature.restart_now'))
                        ->success()
                        ->send();
                } catch (Exception $exception) {
                    Notification::make()
                        ->title(trans('server/feature.gsl_token.failed'))
                        ->body($exception->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
