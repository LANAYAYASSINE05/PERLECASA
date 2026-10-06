@php
    $jour = now()->locale('fr');
@endphp

<p class="pc-topbar-date hidden lg:flex">
    <span>
        <small>Date</small>
        <strong>{{ ucfirst($jour->isoFormat('dddd')) }}</strong> {{ $jour->isoFormat('D MMMM YYYY') }}
    </span>
    <span>
        <small>Lieu</small>
        <strong>Casablanca</strong>
    </span>
</p>
