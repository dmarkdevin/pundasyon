<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Permissions\SyncPermissions;
use App\Enums\LoginEventType;
use App\Enums\SystemRole;
use App\Enums\UserStatus;
use App\Models\LoginEvent;
use App\Models\Role;
use App\Models\User;
use App\Modules\ModuleRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Demo accounts and sample data, for trying the app out. Every demo account's
 * password is "password". Run with `php artisan app:install --demo`.
 */
final class DemoSeeder extends Seeder
{
    public function run(SyncPermissions $sync): void
    {
        $sync();

        $this->account('Super Admin', 'superadmin@example.com', SystemRole::SuperAdmin->value);
        $this->account('Ana Reyes', 'admin@example.com', SystemRole::Admin->value);
        $this->account('Maria Santos', 'user@example.com', SystemRole::User->value);

        $support = Role::query()->firstOrCreate(['name' => 'support-agent', 'guard_name' => 'web']);
        $support->syncPermissions(['users.view']);
        $this->account('Ben Cruz', 'support@example.com', 'support-agent');

        // People who signed up over the last month, so the dashboard has something to show.
        if (User::query()->where('email', 'like', '%@demo.example.com')->doesntExist()) {
            User::factory()->count(30)->sequence(fn ($sequence): array => [
                'email' => "member{$sequence->index}@demo.example.com",
                'created_at' => now()->subDays(random_int(0, 29))->subMinutes(random_int(0, 600)),
            ])->create()->each(function (User $user): void {
                $user->assignRole(SystemRole::User->value);
            });

            User::query()->where('email', 'like', '%@demo.example.com')->inRandomOrder()->limit(2)->get()
                ->each(fn (User $user) => $user->forceFill(['status' => UserStatus::Suspended])->save());

            foreach (range(1, 12) as $i) {
                LoginEvent::query()->create([
                    'email' => "member{$i}@demo.example.com",
                    'type' => $i % 5 === 0 ? LoginEventType::Failed : LoginEventType::Succeeded,
                    'ip_address' => '203.0.113.'.$i,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/130.0 Safari/537.36',
                ]);
            }
        }

        // Sample records for every module made with make:module.
        foreach (ModuleRegistry::all() as $model => $module) {
            $seeder = "Database\\Seeders\\{$model}Seeder";

            if (class_exists($seeder) && Schema::hasTable($module['table'])) {
                $this->call($seeder);
            }
        }
    }

    private function account(string $name, string $email, string $role): void
    {
        $user = User::query()->firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
        $user->markEmailAsVerified();
        $user->syncRoles([$role]);
    }
}
