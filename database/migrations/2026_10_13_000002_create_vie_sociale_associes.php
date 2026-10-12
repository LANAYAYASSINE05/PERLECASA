<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actualites', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('contenu');
            $table->boolean('epingle')->default(false);
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('evenements', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->string('type', 20);
            $table->dateTime('date_debut');
            $table->string('lieu')->nullable();
            $table->text('description')->nullable();
            $table->decimal('montant', 14, 2)->nullable();
            $table->foreignId('cree_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index('date_debut');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('telephone', 30)->nullable()->after('email');
            $table->string('cin', 20)->nullable()->after('telephone');
            $table->string('adresse')->nullable()->after('cin');
            $table->string('banque', 100)->nullable()->after('adresse');
            $table->string('rib', 24)->nullable()->after('banque');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['telephone', 'cin', 'adresse', 'banque', 'rib']);
        });
        Schema::dropIfExists('evenements');
        Schema::dropIfExists('actualites');
    }
};
