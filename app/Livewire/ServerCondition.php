<?php

namespace App\Livewire;

use App\Models\Server;
use Illuminate\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class ServerCondition extends Component
{
    #[Locked]
    public Server $server;

    public function render(): View
    {
        return view('livewire.server-condition', [
            'condition' => $this->server->condition,
        ]);
    }

    public function placeholder(): View
    {
        return view('livewire.server-condition-placeholder');
    }
}
