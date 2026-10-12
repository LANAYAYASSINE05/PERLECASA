<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['encaissements', 'decaissements'] as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->foreignId('associe_id')->nullable()->after('type')->constrained('users')->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['encaissements', 'decaissements'] as $nom) {
            Schema::table($nom, function (Blueprint $table) {
                $table->dropConstrainedForeignId('associe_id');
            });
        }
    }
};
