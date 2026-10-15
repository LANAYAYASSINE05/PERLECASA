@php
    $columns = $this->getColumns();
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview grid gap-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ $this->getHeading() }}
        </h3>

        <div class="flex flex-wrap items-center gap-2">
            @if ($periode === 'date')
                <x-filament::input.wrapper>
                    <x-filament::input
                        type="date"
                        wire:model.live="date"
                        max="{{ today()->toDateString() }}"
                        aria-label="Date"
                    />
                </x-filament::input.wrapper>
            @endif

            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="periode" aria-label="Période des cartes">
                    @foreach (\App\Support\PeriodeTableau::OPTIONS as $cle => $libelle)
                        <option value="{{ $cle }}">{{ $libelle }}</option>
                    @endforeach
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </div>
    </div>

    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        @class([
            'fi-wi-stats-overview-stats-ctn grid gap-6',
            'md:grid-cols-1' => $columns === 1,
            'md:grid-cols-2' => $columns === 2,
            'md:grid-cols-3' => $columns === 3,
            'md:grid-cols-2 xl:grid-cols-4' => $columns === 4,
        ])
    >
        @foreach ($this->getCachedStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>
</x-filament-widgets::widget>
