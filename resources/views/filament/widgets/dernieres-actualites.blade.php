<x-filament-widgets::widget>
    @php($actualites = $this->getActualites())

    <x-filament::section icon="heroicon-o-megaphone" icon-color="primary">
        <x-slot name="heading">Actualités</x-slot>

        <div class="grid gap-4 divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($actualites as $actualite)
                <article class="pt-4 first:pt-0">
                    <div class="flex items-center gap-2">
                        @if ($actualite->epingle)
                            <x-filament::icon icon="heroicon-s-bookmark" class="h-4 w-4 text-warning-500" />
                        @endif
                        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">{{ $actualite->titre }}</h3>
                    </div>
                    <p class="mt-0.5 text-xs text-gray-500">
                        {{ $actualite->created_at->format('d/m/Y à H:i') }}
                        @if ($actualite->auteur) · {{ $actualite->auteur->name }} @endif
                    </p>
                    <div class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{!! nl2br(e($actualite->contenu)) !!}</div>
                </article>
            @empty
                <p class="text-sm text-gray-500">Aucune actualité publiée pour le moment.</p>
            @endforelse
        </div>

        @if ($actualites->hasPages())
            <div class="mt-4">{{ $actualites->links() }}</div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
