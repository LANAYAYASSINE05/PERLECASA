<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    /** L'administrateur vient du .env (ADMIN_EMAIL / ADMIN_PASSWORD) ; les données de démo seulement si DEMO_PASSWORD est défini. */
    public function run(): void
    {
        $admin = config('app.admin');

        if (blank($admin['email'] ?? null) || blank($admin['password'] ?? null)) {
            throw new RuntimeException('Définissez ADMIN_EMAIL et ADMIN_PASSWORD dans le fichier .env.');
        }

        User::updateOrCreate(
            ['email' => $admin['email']],
            ['name' => 'Administrateur', 'password' => $admin['password'], 'role' => Role::Administrateur, 'actif' => true],
        );

        if (filled(config('app.demo.password'))) {
            $this->call(DemoSeeder::class);
        }
    }
}
