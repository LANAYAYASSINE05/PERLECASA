<div class="pc-form">
    <div class="pc-carte-bord">
        <div class="pc-cartouche-haut" data-anim-form>
            <img class="pc-carte-logo" src="{{ asset('images/logo.png') }}" alt="Perle Casa Immobilier">
            <div>
                <p class="pc-cartouche-label">Objet</p>
                <p class="pc-cartouche-valeur">Accès au service Finance</p>
            </div>
        </div>

        <div class="pc-carte-form">
            <div class="pc-form-head">
                <h1 class="pc-title" data-anim-form>Connexion</h1>
                <p class="pc-subtitle" data-anim-form>Utilisez l'adresse e-mail professionnelle que l'administrateur vous a communiquée.</p>
            </div>

            <div data-anim-form>
                <x-filament-panels::form id="form" wire:submit="authenticate" novalidate>
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="true"
                    />
                </x-filament-panels::form>
            </div>

            <div class="pc-help" data-anim-form>
                <x-filament::icon icon="heroicon-o-lifebuoy" class="pc-help-icon" />
                <p>
                    Mot de passe oublié ou compte bloqué ? L'administrateur de l'agence peut le débloquer.
                    <span>Accès réservé au service Finance.</span>
                </p>
            </div>
        </div>

        <dl class="pc-cartouche-bas" data-anim-form>
            <div><dt>Planche</dt><dd>01</dd></div>
            <div><dt>Échelle</dt><dd>1:100</dd></div>
            <div><dt>Lieu</dt><dd>Casablanca</dd></div>
            <div><dt>Date</dt><dd>{{ now()->format('d/m/Y') }}</dd></div>
        </dl>
    </div>

    <x-filament-actions::modals />
</div>
