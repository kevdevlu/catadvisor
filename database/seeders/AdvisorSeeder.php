<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Advisor;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdvisorSeeder extends Seeder
{
    /**
     * One account per advisor in the dataset, plus the read-only admin.
     * Every account uses DEMO_PASSWORD.
     */
    public function run(): void
    {
        $password = config('catadvisor.demo_password');

        if (blank($password)) {
            throw new RuntimeException('Set DEMO_PASSWORD in .env before seeding.');
        }

        $accounts = [
            ['lindqvist', 'Lindqvist', Role::Advisor],
            ['okafor', 'Okafor', Role::Advisor],
            ['reyes', 'Reyes', Role::Advisor],
            ['admin', 'Admin', Role::Admin],
        ];

        foreach ($accounts as [$username, $displayName, $role]) {
            Advisor::create([
                'username' => $username,
                'display_name' => $displayName,
                'role' => $role,
                'password' => $password,
            ]);
        }
    }
}
