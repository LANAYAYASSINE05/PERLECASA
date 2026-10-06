<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('comptable')->after('email');
            $table->boolean('actif')->default(true)->after('role');
        });

        Schema::create('compteurs', function (Blueprint $table) {
            $table->id();
            $table->string('prefixe', 10);
            $table->unsignedSmallInteger('annee');
            $table->unsignedInteger('valeur')->default(0);
            $table->unique(['prefixe', 'annee']);
        });

        Schema::create('programmes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('ville')->default('Casablanca');
            $table->timestamps();
        });

        Schema::create('appartements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('programme_id')->constrained()->restrictOnDelete();
            $table->string('reference', 20);
            $table->string('typologie', 30);
            $table->decimal('surface', 8, 2);
            $table->decimal('prix', 14, 2);
            $table->string('statut', 20)->default('disponible');
            $table->timestamps();
            $table->unique(['programme_id', 'reference']);
        });

        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('prenom')->nullable();
            $table->string('cin', 20)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('ventes', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('appartement_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->date('date_vente');
            $table->decimal('prix_vente', 14, 2);
            $table->timestamps();
        });

        Schema::create('comptes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('type', 20);
            $table->string('banque')->nullable();
            $table->string('rib', 40)->nullable();
            $table->decimal('solde_initial', 14, 2)->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('fournisseurs', function (Blueprint $table) {
            $table->id();
            $table->string('raison_sociale');
            $table->string('ice', 20)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::create('charges_fixes', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->string('categorie', 30);
            $table->decimal('montant', 14, 2);
            $table->string('periodicite', 20)->default('mensuelle');
            $table->foreignId('fournisseur_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::create('encaissements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('type', 30);
            $table->date('date_operation');
            $table->foreignId('vente_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('emetteur')->nullable();
            $table->foreignId('compte_id')->constrained()->restrictOnDelete();
            $table->string('mode', 20);
            $table->string('reference', 60)->nullable();
            $table->decimal('montant', 14, 2);
            $table->string('libelle')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'date_operation']);
        });

        Schema::create('decaissements', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->string('type', 30);
            $table->date('date_operation');
            $table->foreignId('charge_fixe_id')->nullable()->constrained('charges_fixes')->restrictOnDelete();
            $table->string('periode', 7)->nullable();
            $table->foreignId('fournisseur_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('reference_facture', 60)->nullable();
            $table->string('operateur')->nullable();
            $table->string('beneficiaire')->nullable();
            $table->foreignId('compte_id')->constrained()->restrictOnDelete();
            $table->string('mode', 20);
            $table->decimal('montant', 14, 2);
            $table->string('motif')->nullable();
            $table->string('piece_justificative')->nullable();
            $table->boolean('justifie')->default(false);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['type', 'date_operation']);
            $table->index(['type', 'justifie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decaissements');
        Schema::dropIfExists('encaissements');
        Schema::dropIfExists('charges_fixes');
        Schema::dropIfExists('fournisseurs');
        Schema::dropIfExists('comptes');
        Schema::dropIfExists('ventes');
        Schema::dropIfExists('clients');
        Schema::dropIfExists('appartements');
        Schema::dropIfExists('programmes');
        Schema::dropIfExists('compteurs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'actif']);
        });
    }
};
