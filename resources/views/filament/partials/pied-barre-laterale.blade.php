@php
    $user = auth()->user();
@endphp

@if ($user)
    @php
        $initiales = str($user->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $mot) => mb_strtoupper(mb_substr($mot, 0, 1)))
            ->implode('');
    @endphp

    <div class="pc-sidebar-pied" x-data="{}" x-show="$store.sidebar.isOpen">
        <div class="pc-sidebar-pied-nom">
            <span class="pc-sidebar-pied-initiales" aria-hidden="true">{{ $initiales ?: '?' }}</span>
            <span class="min-w-0">
                <strong class="truncate">{{ $user->name }}</strong>
                <span class="block truncate">{{ $user->email }}</span>
            </span>
        </div>

        <dl>
            <div>
                <dt>Profil</dt>
                <dd class="truncate">{{ $user->role?->getLabel() ?? 'Aucun rôle' }}</dd>
            </div>
            <div>
                <dt>Service</dt>
                <dd class="truncate">Finance</dd>
            </div>
        </dl>
    </div>
@endif
