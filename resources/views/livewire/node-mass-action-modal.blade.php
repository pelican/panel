<div x-on:mass-action-close.window="$wire.unmountAction()">
    @livewire(\App\Livewire\NodeMassActionTable::class, ['node' => $node, 'action' => $action], key("node-mass-action-$action-$node->id"))
</div>
