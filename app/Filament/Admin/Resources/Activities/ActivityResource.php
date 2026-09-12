<?php

namespace App\Filament\Admin\Resources\Activities;

use App\Enums\TablerIcon;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Components\Tables\Columns\DateTimeColumn;
use App\Models\ActivityLog;
use App\Models\ActivityLogSubject;
use App\Models\User;
use App\Traits\Filament\CanCustomizePages;
use App\Traits\Filament\CanCustomizeRelations;
use App\Traits\Filament\CanModifyTable;
use BackedEnum;
use Exception;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\HtmlString;

class ActivityResource extends Resource
{
    use CanCustomizePages;
    use CanCustomizeRelations;
    use CanModifyTable;

    protected static ?string $model = ActivityLog::class;

    protected static string|BackedEnum|null $navigationIcon = TablerIcon::Stack;

    /**
     * @throws Exception
     */
    public static function defaultTable(Table $table): Table
    {
        return $table
            ->paginated([25, 50])
            ->defaultPaginationPageOption(25)
            ->columns([
                TextColumn::make('event')
                    ->label(trans('admin/activity.event'))
                    ->html()
                    ->description(fn ($state) => $state)
                    ->icon(fn (ActivityLog $activityLog) => $activityLog->getIcon())
                    ->formatStateUsing(fn (ActivityLog $activityLog) => $activityLog->getLabel()),
                TextColumn::make('user')
                    ->label(trans('admin/activity.user'))
                    ->state(fn (ActivityLog $activityLog) => self::actorName($activityLog))
                    ->tooltip(fn (ActivityLog $activityLog) => $activityLog->getIp() ?? '')
                    ->url(fn (ActivityLog $activityLog) => $activityLog->actor instanceof User && user()?->can('update', $activityLog->actor) ? EditUser::getUrl(['record' => $activityLog->actor]) : '')
                    ->grow(false),
                TextColumn::make('subjects')
                    ->label(trans('admin/activity.subject'))
                    ->state(fn (ActivityLog $activityLog) => $activityLog->subjects
                        ->map(fn (ActivityLogSubject $subject) => class_basename($subject->subject_type) . ' #' . $subject->subject_id)
                        ->unique()
                        ->join(', '))
                    ->grow(false),
                DateTimeColumn::make('timestamp')
                    ->label(trans('admin/activity.timestamp'))
                    ->since()
                    ->sortable()
                    ->grow(false),
            ])
            ->defaultSort('timestamp', 'desc')
            ->recordActions([
                ViewAction::make()
                    ->schema([
                        TextEntry::make('event')
                            ->label(trans('admin/activity.event'))
                            ->state(fn (ActivityLog $activityLog) => new HtmlString($activityLog->getLabel())),
                        TextInput::make('user')
                            ->label(trans('admin/activity.user'))
                            ->formatStateUsing(function (ActivityLog $activityLog) {
                                $user = self::actorName($activityLog);
                                $ip = $activityLog->getIp();

                                return $ip ? "$user - $ip" : $user;
                            }),
                        DateTimePicker::make('timestamp')
                            ->label(trans('admin/activity.timestamp')),
                        KeyValue::make('properties')
                            ->label(trans('admin/activity.metadata'))
                            ->formatStateUsing(fn ($state) => Arr::dot($state)),
                    ]),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->label(trans('admin/activity.event'))
                    ->options(fn () => ActivityLog::whereNotIn('event', ActivityLog::DISABLED_EVENTS)->select('event')->distinct()->orderBy('event')->pluck('event', 'event'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('actor_id')
                    ->label(trans('admin/activity.user'))
                    ->options(fn () => User::whereIn('id', ActivityLog::whereNotNull('actor_id')->select('actor_id'))->pluck('username', 'id'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('subject_type')
                    ->label(trans('admin/activity.subject'))
                    ->options(fn () => ActivityLogSubject::select('subject_type')->distinct()->orderBy('subject_type')->pluck('subject_type', 'subject_type')->mapWithKeys(fn ($type) => [$type => class_basename($type)]))
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn (Builder $query, $value) => $query->whereHas('subjects', fn (Builder $query) => $query->where('subject_type', $value)))),
                Filter::make('timestamp')
                    ->schema([
                        DateTimePicker::make('from')
                            ->label(trans('admin/activity.from')),
                        DateTimePicker::make('until')
                            ->label(trans('admin/activity.until')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'], fn (Builder $query, $value) => $query->where('timestamp', '>=', $value))
                        ->when($data['until'], fn (Builder $query, $value) => $query->where('timestamp', '<=', $value))),
            ]);
    }

    /** @return Builder<ActivityLog> */
    public static function getEloquentQuery(): Builder
    {
        // Deliberately unscoped (and ignoring activity.hide_admin_activity):
        // this is the panel-wide audit view for admins holding "view activityLog".
        return ActivityLog::with(['actor', 'apiKey'])
            ->whereNotIn('event', ActivityLog::DISABLED_EVENTS);
    }

    public static function canViewAny(): bool
    {
        return user()?->can('view activityLog') ?? false;
    }

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    /**
     * ActivityLogPolicy::view() checks the server-panel subuser permission,
     * which never applies here; the admin viewer is gated by "view activityLog".
     */
    public static function getViewAuthorizationResponse(Model $record): Response
    {
        return static::canViewAny() ? Response::allow() : Response::deny();
    }

    /** @return array<string, PageRegistration> */
    public static function getDefaultPages(): array
    {
        return [
            'index' => ListActivities::route('/'),
        ];
    }

    public static function getNavigationLabel(): string
    {
        return trans('admin/activity.title');
    }

    public static function getNavigationGroup(): ?string
    {
        return trans('admin/dashboard.advanced');
    }

    private static function actorName(ActivityLog $activityLog): string
    {
        if (!$activityLog->actor instanceof User) {
            return $activityLog->actor_id === null ? trans('admin/activity.system') : trans('admin/activity.deleted_user');
        }

        return "{$activityLog->actor->username} ({$activityLog->actor->email})";
    }
}
