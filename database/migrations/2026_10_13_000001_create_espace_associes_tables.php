<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_associes', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('categorie', 20);
            $table->date('date_document');
            $table->string('fichier');
            $table->string('nom_original')->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['categorie', 'date_document']);
        });

        Schema::create('demandes_associes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('associe_id')->constrained('users')->restrictOnDelete();
            $table->string('type', 20);
            $table->string('objet');
            $table->text('message');
            $table->decimal('montant', 14, 2)->nullable();
            $table->string('statut', 20)->default('en_attente');
            $table->text('reponse')->nullable();
            $table->foreignId('traite_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('traite_le')->nullable();
            $table->timestamps();
            $table->index(['statut', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_associes');
        Schema::dropIfExists('documents_associes');
    }
};
