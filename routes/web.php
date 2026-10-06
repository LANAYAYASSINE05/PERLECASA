<?php

use App\Models\BonCommande;
use App\Models\Decaissement;
use App\Models\Encaissement;
use App\Models\Vente;
use App\Services\PiecesCommerciales;
use App\Services\PiecesExcel;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/decaissements/{decaissement}/piece', function (Decaissement $decaissement) {
    abort_unless(auth()->user()->can('view', $decaissement), 403);
    abort_unless($decaissement->piece_justificative && Storage::disk(Decaissement::DISQUE)->exists($decaissement->piece_justificative), 404);

    $extension = pathinfo($decaissement->piece_justificative, PATHINFO_EXTENSION);

    return Storage::disk(Decaissement::DISQUE)->download($decaissement->piece_justificative, "{$decaissement->numero}-justificatif.{$extension}");
})->middleware('auth')->name('decaissements.piece');

Route::middleware('auth')->group(function () {
    Route::get('/ventes/{vente}/facture', function (Vente $vente) {
        abort_unless(auth()->user()->accede('ventes'), 403);

        return PiecesExcel::telecharger(PiecesCommerciales::facture($vente));
    })->name('ventes.facture');

    Route::get('/encaissements/{encaissement}/recu', function (Encaissement $encaissement) {
        abort_unless(auth()->user()->accede('encaissements'), 403);

        return PiecesExcel::telecharger(PiecesCommerciales::recu($encaissement));
    })->name('encaissements.recu');

    Route::get('/bons-commande/{bonCommande}/imprimer', function (BonCommande $bonCommande) {
        abort_unless(auth()->user()->accede('fournisseurs'), 403);

        return PiecesExcel::telecharger(PiecesCommerciales::bonCommande($bonCommande));
    })->name('bons-commande.imprimer');
});
