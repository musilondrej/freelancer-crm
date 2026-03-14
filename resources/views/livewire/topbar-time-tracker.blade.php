<div wire:poll.1s>
    @if ($hasActiveSession)
        {{ $this->stopTimerAction }}
    @else
        {{ $this->trackTimeAction }}
    @endif

    <x-filament-actions::modals />
</div>
