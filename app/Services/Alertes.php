<?php

namespace App\Services;

use App\Enums\StatutBonCommande;
use App\Enums\TypeDecaissement;
use App\Filament\Pages\DemandesAssocie;
use App\Filament\Pages\MesInvestissements;
use App\Filament\Resources\BonCommandeResource;
use App\Filament\Resources\ChargeFixeResource;
use App\Filament\Resources\DecaissementResource;
use App\Filament\Resources\DemandeAssocieResource;
use App\Filament\Resources\EncaissementResource;
use App\Filament\Resources\VenteResource;
use App\Models\BonCommande;
use App\Models\ChargeFixe;
use App\Models\Decaissement;
use App\Models\DemandeAssocie;
use App\Models\Encaissement;
use App\Models\User;
use App\Models\Vente;
use App\Support\Montant;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Events\DatabaseNotificationsSent;
use Filament\Notifications\Notification;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Notifications instantanées : enregistrées en base (cloche) puis poussées par Reverb.
 * Envoi synchrone, sans file d'attente ; si Reverb est arrêté, la cloche se rattrape par interrogation.
 */
class Alertes
{
    /** Coupé par DemoSeeder, qui se connecte pour créer ses opérations. */
    public static bool $actives = true;

    public static function encaissement(Encaissement $e): void
    {
        self::depuisAction('encaissements', fn () => Notification::make()
            ->success()
            ->icon('heroicon-o-banknotes')
            ->title("Encaissement {$e->numero} reçu")
            ->body(self::corps(Montant::mad($e->montant).' · '.$e->mode?->getLabel(), $e->provenance()))
            ->actions([self::voir(EncaissementResource::getUrl())]));
    }

    public static function decaissementCree(Decaissement $d): void
    {
        $nonJustifie = $d->type === TypeDecaissement::OperationCaisse && ! $d->justifie;

        self::depuisAction('decaissements', fn () => Notification::make()
            ->status($nonJustifie ? 'danger' : 'info')
            ->icon($nonJustifie ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-arrow-up-tray')
            ->title($nonJustifie ? "Opération de caisse {$d->numero} non justifiée" : "Décaissement {$d->numero} enregistré")
            ->body(self::corps(Montant::mad($d->montant), $d->destinataire()))
            ->actions([self::voir(DecaissementResource::getUrl())]));
    }

    public static function decaissementModifie(Decaissement $d): void
    {
        if ($d->type !== TypeDecaissement::OperationCaisse || ! $d->wasChanged('justifie') || ! $d->justifie) {
            return;
        }

        self::depuisAction('decaissements', fn () => Notification::make()
            ->success()
            ->icon('heroicon-o-document-check')
            ->title("Opération de caisse {$d->numero} justifiée")
            ->body(self::corps(Montant::mad($d->montant), $d->destinataire()))
            ->actions([self::voir(DecaissementResource::getUrl())]));
    }

    public static function vente(Vente $v): void
    {
        self::depuisAction('ventes', fn () => Notification::make()
            ->success()
            ->icon('heroicon-o-home-modern')
            ->title("Nouvelle vente {$v->numero}")
            ->body(self::corps(Montant::mad($v->prix_vente), trim("{$v->appartement?->reference} · {$v->client?->nomComplet()}", ' ·')))
            ->actions([self::voir(VenteResource::getUrl())]));
    }

    public static function bonCommandeCree(BonCommande $b): void
    {
        self::depuisAction('fournisseurs', fn () => Notification::make()
            ->info()
            ->icon('heroicon-o-clipboard-document-list')
            ->title("Bon de commande {$b->numero} créé")
            ->body(self::corps((string) $b->fournisseur?->raison_sociale, (string) $b->objet))
            ->actions([self::voir(BonCommandeResource::getUrl())]));
    }

    public static function bonCommandeModifie(BonCommande $b): void
    {
        if (! $b->wasChanged('statut') || $b->statut === StatutBonCommande::EnCours) {
            return;
        }

        self::depuisAction('fournisseurs', fn () => Notification::make()
            ->status($b->statut === StatutBonCommande::Livre ? 'success' : 'warning')
            ->icon('heroicon-o-truck')
            ->title("Bon de commande {$b->numero} ".mb_strtolower($b->statut->getLabel()))
            ->body(self::corps((string) $b->fournisseur?->raison_sociale, (string) $b->objet))
            ->actions([self::voir(BonCommandeResource::getUrl())]));
    }

    /** Prévient l'associé concerné, et lui seul, d'un mouvement sur son apport ou son remboursement. */
    public static function investissement(Encaissement|Decaissement $m, string $evenement): void
    {
        if (! self::$actives || ! auth()->check() || ! $m->associe_id) {
            return;
        }

        $nature = $m instanceof Encaissement ? 'Apport' : 'Remboursement';
        $associeId = $m->associe_id;
        $notification = Notification::make()
            ->status($evenement === 'supprimé' ? 'warning' : 'info')
            ->icon('heroicon-o-chart-pie')
            ->title("{$nature} {$m->numero} {$evenement}")
            ->body(self::corps(Montant::mad($m->montant), $m->date_operation?->format('d/m/Y') ?? ''))
            ->actions([self::voir(MesInvestissements::getUrl())]);

        DB::afterCommit(function () use ($associeId, $notification): void {
            $associe = User::where('actif', true)->find($associeId);

            if ($associe) {
                self::notifier($associe, $notification);
            }
        });
    }

    public static function demandeAssocie(DemandeAssocie $d): void
    {
        self::depuisAction('associes', fn () => Notification::make()
            ->warning()
            ->icon('heroicon-o-chat-bubble-left-right')
            ->title("Demande de {$d->associe?->name} : {$d->objet}")
            ->body(self::corps($d->type->getLabel(), $d->montant ? Montant::mad($d->montant) : ''))
            ->actions([self::voir(DemandeAssocieResource::getUrl())]));
    }

    public static function reponseDemande(DemandeAssocie $d): void
    {
        if (! self::$actives || ! auth()->check()) {
            return;
        }

        $notification = Notification::make()
            ->status($d->statut->getColor())
            ->icon('heroicon-o-chat-bubble-left-right')
            ->title("Votre demande « {$d->objet} » : ".mb_strtolower($d->statut->getLabel()))
            ->body(e((string) $d->reponse))
            ->actions([self::voir(DemandesAssocie::getUrl())]);
        $associeId = $d->associe_id;

        DB::afterCommit(function () use ($associeId, $notification): void {
            if ($associe = User::where('actif', true)->find($associeId)) {
                self::notifier($associe, $notification);
            }
        });
    }

    /** Rappel quotidien (commande finance:alertes-charges) : retards, et échéances du mois le 1er. */
    public static function charges(): int
    {
        $charges = ChargeFixe::where('actif', true)->orderBy('libelle')->get();
        $envoyees = 0;

        $retards = $charges->filter(fn (ChargeFixe $c) => $c->estEnRetard());
        if ($retards->isNotEmpty()) {
            self::envoyer('charges', Notification::make()
                ->warning()
                ->icon('heroicon-o-clock')
                ->title(trans_choice('{1} 1 charge fixe en retard|[2,*] :count charges fixes en retard', $retards->count()))
                ->body(self::liste($retards))
                ->actions([self::voir(ChargeFixeResource::getUrl())]));
            $envoyees++;
        }

        $mois = now()->format('Y-m');
        $echeances = $charges->filter(fn (ChargeFixe $c) => $c->prochainePeriode() === $mois);
        if (now()->day === 1 && $echeances->isNotEmpty()) {
            self::envoyer('charges', Notification::make()
                ->info()
                ->icon('heroicon-o-calendar-days')
                ->title(trans_choice('{1} 1 charge fixe à régler ce mois|[2,*] :count charges fixes à régler ce mois', $echeances->count()))
                ->body(self::liste($echeances))
                ->actions([self::voir(ChargeFixeResource::getUrl())]));
            $envoyees++;
        }

        return $envoyees;
    }

    /** Les actions faites dans l'application seulement : ni seeders, ni tinker. */
    private static function depuisAction(string $ecran, \Closure $notification): void
    {
        if (! self::$actives || ! auth()->check()) {
            return;
        }

        DB::afterCommit(fn () => self::envoyer($ecran, $notification(), auth()->id()));
    }

    private static function envoyer(string $ecran, Notification $notification, ?int $auteur = null): void
    {
        $destinataires = User::where('actif', true)
            ->when($auteur, fn ($q) => $q->whereKeyNot($auteur))
            ->get()
            ->filter(fn (User $u) => $u->accede($ecran) && ! $u->estAssocie());

        $diffusion = true;

        foreach ($destinataires as $user) {
            $diffusion = self::notifier($user, $notification, $diffusion);
        }
    }

    /** Enregistre en base puis pousse par Reverb ; renvoie false si Reverb est injoignable. */
    private static function notifier(User $user, Notification $notification, bool $diffusion = true): bool
    {
        $user->notifyNow($notification->toDatabase());

        if (! $diffusion) {
            return false;
        }

        try {
            $user->notifyNow($notification->toBroadcast());
            dispatch_sync(new BroadcastEvent(new DatabaseNotificationsSent($user)));
        } catch (Throwable $e) {
            Log::notice('Notification non diffusée (Reverb injoignable) : '.$e->getMessage());

            return false;
        }

        return true;
    }

    private static function corps(string $principal, string $detail): string
    {
        $auteur = auth()->user()?->name;

        return e(collect([$principal, $detail, $auteur ? "par {$auteur}" : null])->filter()->implode(' · '));
    }

    private static function liste(Collection $charges): string
    {
        return $charges->take(5)->map(fn (ChargeFixe $c) => "{$c->libelle} (".Montant::mad($c->montant).')')->implode(', ')
            .($charges->count() > 5 ? '…' : '');
    }

    private static function voir(string $url): Action
    {
        return Action::make('voir')->label('Voir')->url($url)->markAsRead();
    }
}
