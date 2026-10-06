@php
    $livewire ??= null;

    // Plan d'un appartement type sur une base de 600 x 440 (1 unité = 2,4 cm).
    $murs = [
        'M40 40H560V400H140', 'M90 400H40V40',
        'M340 40V110', 'M340 160V190', 'M340 240V400',
        'M340 180H560',
        'M40 260H90', 'M150 260H340',
        'M200 260V300', 'M200 345V400',
    ];
    $portes = [
        'M90 400V350A50 50 0 0 1 140 400',
        'M340 190H390A50 50 0 0 1 340 240',
        'M200 300H245A45 45 0 0 1 200 345',
    ];
    $fenetres = [[100, 36, 120, 8], [400, 36, 100, 8], [552, 250, 8, 90], [36, 100, 8, 100]];
    $pieces = [
        ['Séjour', '28,6 m²', 190, 150],
        ['Cuisine', '11,2 m²', 450, 105],
        ['Chambre', '16,4 m²', 450, 216],
        ['Salle de bain', '5,8 m²', 262, 352],
        ['Entrée', '7,1 m²', 120, 320],
    ];
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="pc-login">
        <div class="pc-papier" aria-hidden="true"></div>
        <div class="pc-viseur" aria-hidden="true"><span class="pc-viseur-x"></span><span class="pc-viseur-y"></span></div>

        <section class="pc-scene">
            <div class="pc-accroche">
                <p class="pc-accroche-lieu" data-anim>Casablanca</p>
                <h2 class="pc-brand-title" data-anim>Service Finance</h2>
                <p class="pc-accroche-texte" data-anim>Les ventes encaissées, les virements reçus, les charges, les fournisseurs et la caisse, au même endroit.</p>
            </div>

            <svg class="pc-plan" viewBox="-10 -10 640 470" aria-hidden="true">
                <g class="pc-cotes">
                    <path class="pc-cote" d="M40 14H560M40 8V20M560 8V20M14 40V400M8 40H20M8 400H20" />
                    <text class="pc-cote-texte" x="300" y="8" text-anchor="middle">12,40 m</text>
                    <text class="pc-cote-texte" x="8" y="224" text-anchor="middle" transform="rotate(-90 8 224)">8,60 m</text>
                </g>

                <g class="pc-mobilier">
                    <rect x="70" y="190" width="150" height="42" rx="8" />
                    <rect x="70" y="190" width="150" height="12" rx="4" />
                    <circle cx="255" cy="120" r="30" />
                    <rect x="398" y="252" width="130" height="126" rx="4" />
                    <rect x="408" y="260" width="50" height="22" rx="4" />
                    <rect x="468" y="260" width="50" height="22" rx="4" />
                    <path d="M352 52H548V80M352 52V150H380V80H548" />
                    <rect x="254" y="272" width="74" height="44" rx="14" />
                    <circle cx="312" cy="380" r="9" />
                </g>

                @foreach ($murs as $mur)
                    <path class="pc-mur" d="{{ $mur }}" />
                @endforeach

                @foreach ($fenetres as [$fx, $fy, $fl, $fh])
                    <g class="pc-baie">
                        <rect x="{{ $fx }}" y="{{ $fy }}" width="{{ $fl }}" height="{{ $fh }}" />
                        @if ($fl > $fh)
                            <path d="M{{ $fx }} {{ $fy + $fh / 2 }}h{{ $fl }}" />
                        @else
                            <path d="M{{ $fx + $fl / 2 }} {{ $fy }}v{{ $fh }}" />
                        @endif
                    </g>
                @endforeach

                @foreach ($portes as $porte)
                    <path class="pc-porte" d="{{ $porte }}" />
                @endforeach

                @foreach ($pieces as [$nom, $surface, $px, $py])
                    <g class="pc-piece">
                        <text class="pc-piece-nom" x="{{ $px }}" y="{{ $py }}" text-anchor="middle">{{ $nom }}</text>
                        <text class="pc-piece-surface" x="{{ $px }}" y="{{ $py + 16 }}" text-anchor="middle">{{ $surface }}</text>
                    </g>
                @endforeach

                <g class="pc-nord" transform="translate(600 420)">
                    <circle r="18" />
                    <path class="pc-nord-fleche" d="M0-14L6 8 0 3-6 8Z" />
                    <text y="-22" text-anchor="middle">N</text>
                </g>
            </svg>
        </section>

        <main class="pc-form-side">
            {{ $slot }}
        </main>
    </div>
</x-filament-panels::layout.base>
