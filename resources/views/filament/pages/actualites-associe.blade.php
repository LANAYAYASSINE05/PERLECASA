<x-filament-panels::page>
    @php($actualites = $this->getActualites())

    <div class="grid gap-4">
        @forelse ($actualites as $actualite)
            <x-filament::section :icon="$actualite->epingle ? 'heroicon-s-bookmark' : 'heroicon-o-megaphone'"
                                 :icon-color="$actualite->epingle ? 'warning' : 'primary'">
                <x-slot name="heading">{{ $actualite->titre }}</x-slot>
                <x-slot name="description">
                    {{ $actualite->created_at->format('d/m/Y à H:i') }}
                    @if ($actualite->auteur) · {{ $actualite->auteur->name }} @endif
                </x-slot>

                <div class="text-sm leading-relaxed text-gray-700 dark:text-gray-300">{!! nl2br(e($actualite->contenu)) !!}</div>
            </x-filament::section>
        @empty
            <x-filament::section>
                <p class="text-sm text-gray-500">Aucune actualité publiée pour le moment.</p>
            </x-filament::section>
        @endforelse

        {{ $actualites->links() }}
    </div>
</x-filament-panels::page>
