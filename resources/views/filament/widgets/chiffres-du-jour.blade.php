@php
    $columns = $this->getColumns();
@endphp

<x-filament-widgets::widget class="fi-wi-stats-overview grid gap-y-4">
    <div class="pc-filtre-periode flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
        <h3 class="min-w-0 text-base font-semibold leading-6 text-gray-950 dark:text-white">
            {{ $this->getHeading() }}
        </h3>

        <div class="pc-filtre-periode-outils flex min-w-0 flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
            @if ($periode === 'date')
                <x-filament::input.wrapper class="w-full sm:w-auto">
                    <x-filament::input
                        type="date"
                        wire:model.live="date"
                        max="{{ today()->toDateString() }}"
                        aria-label="Date"
                    />
                </x-filament::input.wrapper>
            @endif

            <div class="pc-filtre-periode-onglets -mx-1 overflow-x-auto px-1">
                <x-filament::tabs label="Période des cartes" class="w-max max-w-none">
                    @foreach (\App\Support\PeriodeTableau::ONGLETS as $cle => $libelle)
                        <x-filament::tabs.item
                            :active="$periode === $cle"
                            wire:click="$set('periode', '{{ $cle }}')"
                        >
                            {{ $libelle }}
                        </x-filament::tabs.item>
                    @endforeach
                </x-filament::tabs>
            </div>
        </div>
    </div>

    <div
        @if ($pollingInterval = $this->getPollingInterval())
            wire:poll.{{ $pollingInterval }}
        @endif
        @class([
            'fi-wi-stats-overview-stats-ctn grid gap-4 sm:gap-6',
            'grid-cols-1' => $columns === 1,
            'grid-cols-1 sm:grid-cols-2' => $columns === 2,
            'grid-cols-1 sm:grid-cols-3' => $columns === 3,
            'grid-cols-1 sm:grid-cols-2 xl:grid-cols-4' => $columns === 4,
        ])
    >
        @foreach ($this->getCachedStats() as $stat)
            {{ $stat }}
        @endforeach
    </div>
</x-filament-widgets::widget>
