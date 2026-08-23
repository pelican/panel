<?php

namespace App\Livewire;

use Closure;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Concerns\HasActions;
use Filament\Notifications\Concerns\HasBody;
use Filament\Notifications\Concerns\HasIcon;
use Filament\Notifications\Concerns\HasId;
use Filament\Notifications\Concerns\HasStatus;
use Filament\Notifications\Concerns\HasTitle;
use Filament\Support\Components\ViewComponent;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use Livewire\Livewire;

final class AlertBanner extends ViewComponent implements Arrayable
{
    use HasActions;
    use HasBody;
    use HasIcon;
    use HasId;
    use HasStatus;
    use HasTitle;

    protected bool|Closure $closable = false;

    protected string $view = 'livewire.alerts.alert-banner';

    protected string $viewIdentifier = 'alert-banner';

    public function __construct(string $id)
    {
        $this->id($id);
    }

    public static function make(string $id): AlertBanner
    {
        $static = new self($id);
        $static->configure();

        return $static;
    }

    /**
     * @return array{id: string, title: ?string, body: ?string, status: ?string, icon: string|\BackedEnum|Htmlable|null, closeable: bool, actions: array<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->getId(),
            'title' => $this->getTitle(),
            'body' => $this->getBody(),
            'status' => $this->getStatus(),
            'icon' => $this->getIcon(),
            'closeable' => $this->isCloseable(),
            'actions' => collect($this->getActions())->toArray(),
        ];
    }

    /**
     * @param  array{id: string, title: ?string, body: ?string, status: ?string, icon: string|\BackedEnum|Htmlable|null, closeable: bool, actions: array<array<string, mixed>>}  $data
     */
    public static function fromArray(array $data): AlertBanner
    {
        $static = AlertBanner::make($data['id']);

        $static->title($data['title']);
        $static->body($data['body']);
        $static->status($data['status']);
        $static->icon($data['icon']);
        $static->closable($data['closeable']);
        $static->actions(array_map(
            fn (array $action): Action|ActionGroup => match (array_key_exists('actions', $action)) {
                true => ActionGroup::fromArray($action),
                false => Action::fromArray($action),
            },
            $data['actions'] ?? [],
        ));

        return $static;
    }

    public function closable(bool|Closure $closable = true): AlertBanner
    {
        $this->closable = $closable;

        return $this;
    }

    public function isCloseable(): bool
    {
        return $this->evaluate($this->closable);
    }

    public function send(): AlertBanner
    {
        $data = $this->toArray();

        if (Livewire::isLivewireRequest()) {
            $data['from_livewire'] = true;
        }

        session()->push('alert-banners', $data);

        return $this;
    }
}
