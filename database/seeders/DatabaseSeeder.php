<?php

namespace Database\Seeders;

use App\Models\Counter;
use App\Models\ShopSetting;
use App\Models\User;
use App\Services\Numbering;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $superAdminEmail = config('services.super_admin.email');
        $superAdminPassword = config('services.super_admin.password');

        if (blank($superAdminEmail) || blank($superAdminPassword)) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env before seeding.');
        }

        User::query()->updateOrCreate(
            ['email' => $superAdminEmail],
            [
                'name' => 'Super Admin',
                'password' => $superAdminPassword,
                'role' => 'Super Admin',
                'status' => 'active',
            ],
        );

        if (ShopSetting::query()->doesntExist()) {
            ShopSetting::query()->create([
                'name' => 'M-Fixpro',
                'phone' => '',
                'notify_emails' => [],
            ]);
        }

        foreach (array_keys(Numbering::PREFIXES) as $counterName) {
            Counter::query()->firstOrCreate(['name' => $counterName], ['value' => 0]);
        }
    }
}
