<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('ice', 20)->nullable()->after('cin');
            $table->string('adresse')->nullable()->after('email');
        });

        Schema::table('fournisseurs', function (Blueprint $table) {
            $table->string('adresse')->nullable()->after('email');
        });

        Schema::table('comptes', function (Blueprint $table) {
            $table->string('adresse_agence')->nullable()->after('rib');
        });

        Schema::table('ventes', function (Blueprint $table) {
            $table->string('numero_facture', 20)->nullable()->unique()->after('numero');
        });

        Schema::create('bons_commande', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique();
            $table->foreignId('fournisseur_id')->constrained()->restrictOnDelete();
            $table->date('date_commande');
            $table->date('date_livraison')->nullable();
            $table->string('objet')->nullable();
            $table->string('lieu_livraison')->nullable();
            $table->string('conditions_paiement')->nullable();
            $table->decimal('taux_tva', 5, 2)->default(20);
            $table->string('statut', 20)->default('en_cours');
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('lignes_bon_commande', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bon_commande_id')->constrained('bons_commande')->cascadeOnDelete();
            $table->string('reference', 50)->nullable();
            $table->string('designation');
            $table->decimal('quantite', 12, 2);
            $table->decimal('prix_unitaire', 14, 2);
            $table->unsignedSmallInteger('ordre')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_bon_commande');
        Schema::dropIfExists('bons_commande');
        Schema::table('ventes', fn (Blueprint $t) => $t->dropColumn('numero_facture'));
        Schema::table('comptes', fn (Blueprint $t) => $t->dropColumn('adresse_agence'));
        Schema::table('fournisseurs', fn (Blueprint $t) => $t->dropColumn('adresse'));
        Schema::table('clients', fn (Blueprint $t) => $t->dropColumn(['ice', 'adresse']));
    }
};
