<div style="padding: 0.75rem 1rem;">
    <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.25rem;">
        {!! (clone $column)->description(null)->extraAttributes(['style' => 'padding: 0;'])->toEmbeddedHtml() !!}

        @unless ($column->getTable()->isReordering())
            <div style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.125rem;">
                @foreach ($column->getTable()->getRecordActions() as $recordAction)
                    @php($action = $recordAction->getClone()->record($getRecord()))

                    @if ($action->isVisible())
                        {{ $action }}
                    @endif
                @endforeach
            </div>
        @endunless
    </div>

    <p class="fi-ta-text-description">{{ $column->getDescriptionBelow() }}</p>
</div>
