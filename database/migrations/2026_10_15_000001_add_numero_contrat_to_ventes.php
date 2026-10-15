<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventes', function (Blueprint $table) {
            $table->string('numero_contrat', 20)->nullable()->unique()->after('numero_facture');
        });
    }

    public function down(): void
    {
        Schema::table('ventes', fn (Blueprint $t) => $t->dropColumn('numero_contrat'));
    }
};
