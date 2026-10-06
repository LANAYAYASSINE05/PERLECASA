@php
    // Élévation de façade : [x, y, largeur, hauteur, colonnes de fenêtres]
    $blocs = [[20, 92, 120, 108, 3], [140, 32, 130, 168, 3], [270, 72, 120, 128, 3]];
    $sol = 200;
@endphp

<x-filament-widgets::widget>
    <section class="pc-planche pc-apparition px-6 py-7 sm:px-9 sm:py-9">
        <div class="relative flex flex-col gap-8 xl:flex-row xl:items-stretch">
            <div class="flex min-w-0 flex-1 flex-col justify-between gap-7">
                <div>
                    <p class="flex items-center gap-3 text-[11px] font-semibold uppercase tracking-[0.2em] text-or-300">
                        <span class="border border-or-300/50 px-1.5 py-px tabular-nums">00</span>
                        Tableau de bord
                    </p>

                    <h2 class="mt-4 font-serif text-3xl font-medium tracking-tight sm:text-4xl">
                        {{ $salutation }}, {{ $prenom }}
                    </h2>

                    <p class="mt-3 max-w-xl text-sm leading-relaxed text-slate-300 sm:text-base">
                        Service Finance de {{ $agence }} : ventes encaissées, virements reçus, charges fixes, fournisseurs et caisse.
                    </p>
                </div>

                @if ($actions)
                    <div class="flex flex-wrap gap-3">
                        @foreach ($actions as $action)
                            <a href="{{ $action['url'] }}" @class(['pc-bouton-planche', 'pc-principal' => $loop->first])>
                                <x-filament::icon :icon="$action['icone']" class="h-4 w-4" />
                                {{ $action['libelle'] }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>

            <svg class="pc-facade hidden w-[330px] shrink-0 self-end xl:block 2xl:w-[400px]" viewBox="0 0 410 222" aria-hidden="true">
                <path pathLength="1" d="M0 {{ $sol }}H410" />

                @foreach ($blocs as [$x, $y, $l, $h, $colonnes])
                    <rect pathLength="1" x="{{ $x }}" y="{{ $y }}" width="{{ $l }}" height="{{ $h }}" style="--pc-delai: {{ 0.1 + $loop->index * 0.15 }}s" />

                    @for ($etage = $y + 14; $etage + 26 <= $sol; $etage += 24)
                        @for ($c = 0; $c < $colonnes; $c++)
                            <rect
                                class="pc-facade-fine"
                                pathLength="1"
                                x="{{ $x + ($l / $colonnes) * $c + ($l / $colonnes - 18) / 2 }}"
                                y="{{ $etage }}"
                                width="18"
                                height="12"
                                style="--pc-delai: {{ 0.6 + ($sol - $etage) * 0.004 + $c * 0.05 }}s"
                            />
                        @endfor
                    @endfor
                @endforeach

                <rect pathLength="1" x="192" y="174" width="26" height="26" style="--pc-delai: .9s" />

                <g class="pc-facade-cote">
                    <path pathLength="1" d="M140 18H270M140 13v10M270 13v10" style="--pc-delai: 1.2s" />
                    <path pathLength="1" d="M402 32V{{ $sol }}M397 32h10M397 {{ $sol }}h10" style="--pc-delai: 1.3s" />
                </g>
                <text x="205" y="12" text-anchor="middle">14,00</text>
                <text x="396" y="120" text-anchor="middle" transform="rotate(-90 396 120)">R+6</text>
                <text x="0" y="216">ÉLÉVATION SUD · 1:200</text>
            </svg>

            <dl class="pc-cartouche w-full shrink-0 self-start sm:grid-cols-2 lg:grid-cols-4 xl:w-60 xl:grid-cols-1">
                <div>
                    <dt>Date</dt>
                    <dd>{{ $date }}</dd>
                </div>
                <div>
                    <dt>Profil</dt>
                    <dd>{{ $role }}</dd>
                </div>
                <div>
                    <dt>Trésorerie</dt>
                    <dd>{{ $tresorerie }}</dd>
                </div>
                <div>
                    <dt>Caisse à justifier</dt>
                    <dd>{{ $nonJustifiees }}</dd>
                </div>
            </dl>
        </div>
    </section>
</x-filament-widgets::widget>
