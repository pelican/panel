<?php

namespace App\Filament\Server\Resources\Schedules;

use App\Enums\ScheduleStatus;
use App\Enums\TablerIcon;
use App\Facades\Activity;
use App\Filament\Components\Actions\ImportScheduleAction;
use App\Filament\Components\Forms\Actions\CronPresetAction;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Filament\Server\Resources\Schedules\Pages\CreateSchedule;
use App\Filament\Server\Resources\Schedules\Pages\EditSchedule;
use App\Filament\Server\Resources\Schedules\Pages\ListSchedules;
use App\Filament\Server\Resources\Schedules\Pages\ViewSchedule;
use App\Filament\Server\Resources\Schedules\RelationManagers\TasksRelationManager;
use App\Helpers\Utilities;
use App\Models\Schedule;
use App\Traits\Filament\BlockAccessInConflict;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyForm;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;
use Throwable;

class ScheduleResource extends Resource
{
    use BlockAccessInConflict;
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyForm;
    use CanModifyTable;

    protected static ?string $model = Schedule::class;

    protected static ?int $navigationSort = 4;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Clock;

    /**
     * @throws Exception
     */
    public static function defaultForm(Schema $schema): Schema
    {
        return $schema
            ->columns([
                'default' => 1,
                'lg' => 2,
            ])
            ->components([
                TextInput::make('name')
                    ->label(trans('server/schedule.name'))
                    ->columnSpanFull()
                    ->autocomplete(false)
                    ->columnSpanFull()
                    ->required(),
                Toggle::make('only_when_online')
                    ->label(trans('server/schedule.only_online'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('server/schedule.only_online_hint'))
                    ->inline(false)
                    ->required()
                    ->default(1),
                Toggle::make('is_active')
                    ->label(trans('server/schedule.enabled'))
                    ->hintIcon(TablerIcon::QuestionMark, trans('server/schedule.enabled_hint'))
                    ->inline(false)
                    ->hiddenOn('view')
                    ->required()
                    ->default(1),
                ToggleButtons::make('status')
                    ->label(trans('server/schedule.status'))
                    ->enum(ScheduleStatus::class)
                    ->formatStateUsing(fn (?Schedule $schedule) => $schedule?->status->value ?? 'new')
                    ->options(fn (?Schedule $schedule) => [$schedule?->status->value ?? 'new' => $schedule?->status->getLabel() ?? trans('server/schedule.schedule_status.new')])
                    ->visibleOn('view'),
                Section::make(trans('server/schedule.cron'))
                    ->description(function (Get $get) {
                        try {
                            $nextRun = Utilities::getScheduleNextRunDate($get('cron_minute'), $get('cron_hour'), $get('cron_day_of_month'), $get('cron_month'), $get('cron_day_of_week'), user()->timezone ?? 'UTC')->timezone(user()->timezone ?? 'UTC');
                        } catch (Exception) {
                            $nextRun = trans('server/schedule.invalid');
                        }

                        return new HtmlString(trans('server/schedule.cron_body') . '<br>' . trans('server/schedule.cron_timezone', ['timezone' => user()->timezone ?? 'UTC', 'next_run' => $nextRun]));
                    })
                    ->schema([
                        Actions::make([
                            CronPresetAction::make('exclude_hourly')
                                ->label(trans('server/schedule.time.hourly'))
                                ->cron('0', '*', '*', '*', '*'),
                            CronPresetAction::make('exclude_daily')
                                ->label(trans('server/schedule.time.daily'))
                                ->cron('0', '0', '*', '*', '*'),
                            CronPresetAction::make('exclude_weekly_monday')
                                ->label(trans('server/schedule.time.weekly_mon'))
                                ->cron('0', '0', '*', '*', '1'),
                            CronPresetAction::make('exclude_weekly_sunday')
                                ->label(trans('server/schedule.time.weekly_sun'))
                                ->cron('0', '0', '*', '*', '0'),
                            CronPresetAction::make('exclude_monthly')
                                ->label(trans('server/schedule.time.monthly'))
                                ->cron('0', '0', '1', '*', '*'),
                            CronPresetAction::make('exclude_every_x_minutes')
                                ->label(trans('server/schedule.time.every_min'))
                                ->color(fn (Get $get) => str($get('cron_minute'))->startsWith('*/')
                                                    && $get('cron_hour') == '*'
                                                    && $get('cron_day_of_month') == '*'
                                                    && $get('cron_month') == '*'
                                                    && $get('cron_day_of_week') == '*' ? 'success' : 'primary')
                                ->schema([
                                    TextInput::make('x')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(60)
                                        ->prefix(trans('server/schedule.time.every'))
                                        ->suffix(trans('server/schedule.time.minutes')),
                                ])
                                ->action(function (Set $set, $data) {
                                    $set('cron_minute', '*/' . $data['x']);
                                    $set('cron_hour', '*');
                                    $set('cron_day_of_month', '*');
                                    $set('cron_month', '*');
                                    $set('cron_day_of_week', '*');
                                }),
                            CronPresetAction::make('exclude_every_x_hours')
                                ->color(fn (Get $get) => $get('cron_minute') == '0'
                                                    && str($get('cron_hour'))->startsWith('*/')
                                                    && $get('cron_day_of_month') == '*'
                                                    && $get('cron_month') == '*'
                                                    && $get('cron_day_of_week') == '*' ? 'success' : 'primary')
                                ->schema([
                                    TextInput::make('x')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(24)
                                        ->prefix(trans('server/schedule.time.every'))
                                        ->suffix(trans('server/schedule.time.hours')),
                                ])
                                ->action(function (Set $set, $data) {
                                    $set('cron_minute', '0');
                                    $set('cron_hour', '*/' . $data['x']);
                                    $set('cron_day_of_month', '*');
                                    $set('cron_month', '*');
                                    $set('cron_day_of_week', '*');
                                }),
                            CronPresetAction::make('exclude_every_x_days')
                                ->color(fn (Get $get) => $get('cron_minute') == '0'
                                                    && $get('cron_hour') == '0'
                                                    && str($get('cron_day_of_month'))->startsWith('*/')
                                                    && $get('cron_month') == '*'
                                                    && $get('cron_day_of_week') == '*' ? 'success' : 'primary')
                                ->schema([
                                    TextInput::make('x')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(24)
                                        ->prefix(trans('server/schedule.time.every'))
                                        ->suffix(trans('server/schedule.time.days')),
                                ])
                                ->action(function (Set $set, $data) {
                                    $set('cron_minute', '0');
                                    $set('cron_hour', '0');
                                    $set('cron_day_of_month', '*/' . $data['x']);
                                    $set('cron_month', '*');
                                    $set('cron_day_of_week', '*');
                                }),
                            CronPresetAction::make('exclude_every_x_months')
                                ->color(fn (Get $get) => $get('cron_minute') == '0'
                                                    && $get('cron_hour') == '0'
                                                    && $get('cron_day_of_month') == '1'
                                                    && str($get('cron_month'))->startsWith('*/')
                                                    && $get('cron_day_of_week') == '*' ? 'success' : 'primary')
                                ->schema([
                                    TextInput::make('x')
                                        ->hiddenLabel()
                                        ->numeric()
                                        ->minValue(1)
                                        ->maxValue(24)
                                        ->prefix(trans('server/schedule.time.every'))
                                        ->suffix(trans('server/schedule.time.months')),
                                ])
                                ->action(function (Set $set, $data) {
                                    $set('cron_minute', '0');
                                    $set('cron_hour', '0');
                                    $set('cron_day_of_month', '1');
                                    $set('cron_month', '*/' . $data['x']);
                                    $set('cron_day_of_week', '*');
                                }),
                            CronPresetAction::make('exclude_every_x_day_of_week')
                                ->color(fn (Get $get) => $get('cron_minute') == '0'
                                                    && $get('cron_hour') == '0'
                                                    && $get('cron_day_of_month') == '*'
                                                    && $get('cron_month') == '*'
                                                    && $get('cron_day_of_week') != '*' ? 'success' : 'primary')
                                ->schema([
                                    Select::make('x')
                                        ->hiddenLabel()
                                        ->prefix(trans('server/schedule.time.every'))
                                        ->options([
                                            '1' => trans('server/schedule.time.monday'),
                                            '2' => trans('server/schedule.time.tuesday'),
                                            '3' => trans('server/schedule.time.wednesday'),
                                            '4' => trans('server/schedule.time.thursday'),
                                            '5' => trans('server/schedule.time.friday'),
                                            '6' => trans('server/schedule.time.saturday'),
                                            '0' => trans('server/schedule.time.sunday'),
                                        ])
                                        ->selectablePlaceholder(false),
                                ])
                                ->action(function (Set $set, $data) {
                                    $set('cron_minute', '0');
                                    $set('cron_hour', '0');
                                    $set('cron_day_of_month', '*');
                                    $set('cron_month', '*');
                                    $set('cron_day_of_week', $data['x']);
                                }),
                        ])
                            ->hiddenOn('view'),
                        Group::make([
                            TextInput::make('cron_minute')
                                ->label(trans('server/schedule.time.minute'))
                                ->columnSpan([
                                    'default' => 2,
                                    'lg' => 1,
                                ])
                                ->default('*/5')
                                ->required()
                                ->live(),
                            TextInput::make('cron_hour')
                                ->label(trans('server/schedule.time.hour'))
                                ->columnSpan([
                                    'default' => 2,
                                    'lg' => 1,
                                ])
                                ->default('*')
                                ->required()
                                ->live(),
                            TextInput::make('cron_day_of_month')
                                ->label(trans('server/schedule.time.day_of_month'))
                                ->columnSpan([
                                    'default' => 2,
                                    'lg' => 1,
                                ])
                                ->default('*')
                                ->required()
                                ->live(),
                            TextInput::make('cron_month')
                                ->label(trans('server/schedule.time.month'))
                                ->columnSpan([
                                    'default' => 2,
                                    'lg' => 1,
                                ])
                                ->default('*')
                                ->required()
                                ->live(),
                            TextInput::make('cron_day_of_week')
                                ->label(trans('server/schedule.time.day_of_week'))
                                ->columnSpan([
                                    'default' => 2,
                                    'lg' => 1,
                                ])
                                ->default('*')
                                ->required()
                                ->live(),
                        ])
                            ->columns([
                                'default' => 4,
                                'lg' => 5,
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @throws Throwable
     */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(trans('server/schedule.name'))
                    ->searchable(),
                TextColumn::make('cron')
                    ->label(trans('server/schedule.cron'))
                    ->state(fn (Schedule $schedule) => $schedule->cron_minute . ' ' . $schedule->cron_hour . ' ' . $schedule->cron_day_of_month . ' ' . $schedule->cron_month . ' ' . $schedule->cron_day_of_week)
                    ->description(fn (Schedule $schedule) => $schedule->timezone),
                TextColumn::make('status')
                    ->label(trans('server/schedule.status'))
                    ->state(fn (Schedule $schedule) => $schedule->status->getLabel())
                    ->color(fn (Schedule $schedule) => $schedule->status->getColor())
                    ->badge(),
                IconColumn::make('only_when_online')
                    ->label(trans('server/schedule.online_only'))
                    ->boolean()
                    ->sortable(),
                DateTimeColumn::make('last_run_at')
                    ->label(trans('server/schedule.last_run'))
                    ->placeholder(trans('server/schedule.never'))
                    ->since()
                    ->sortable(),
                DateTimeColumn::make('next_run_at')
                    ->label(trans('server/schedule.next_run'))
                    ->placeholder(trans('server/schedule.never'))
                    ->since()
                    ->sortable()
                    ->state(fn (Schedule $schedule) => $schedule->status === ScheduleStatus::Active ? $schedule->next_run_at : null),
            ])
            ->recordActions([
                ViewAction::make()
                    ->hidden(fn ($record) => static::getEditAuthorizationResponse($record)->allowed()),
                EditAction::make(),
                DeleteAction::make()
                    ->after(function (Schedule $schedule) {
                        Activity::event('server:schedule.delete')
                            ->subject($schedule)
                            ->property('name', $schedule->name)
                            ->log();
                    }),
            ])
            ->toolbarActions([
                CreateAction::make()
                    ->hiddenLabel()
                    ->icon(TablerIcon::CalendarPlus)
                    ->color('primary')
                    ->tooltip(trans('server/schedule.new')),
                ImportScheduleAction::make()
                    ->hiddenLabel()
                    ->icon(TablerIcon::FileImport)
                    ->color('success')
                    ->tooltip(trans('server/schedule.import')),
            ]);
    }

    /** @return class-string<RelationManager>[] */
    public static function getDefaultRelations(): array
    {
        return [
            TasksRelationManager::class,
        ];
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListSchedules::route('/'),
            'create' => CreateSchedule::route('/create'),
            'view' => ViewSchedule::route('/{record}'),
            'edit' => EditSchedule::route('/{record}/edit'),
        ];
    }

    public static function getNextRun(string $minute, string $hour, string $dayOfMonth, string $month, string $dayOfWeek, string $timezone = 'UTC'): Carbon
    {
        try {
            return Utilities::getScheduleNextRunDate($minute, $hour, $dayOfMonth, $month, $dayOfWeek, $timezone);
        } catch (Exception) {
            Notification::make()
                ->title(trans('server/schedule.notification_invalid_cron'))
                ->danger()
                ->send();

            throw new Halt();
        }
    }

    public static function getNavigationLabel(): string
    {
        return trans('server/schedule.title');
    }
}
