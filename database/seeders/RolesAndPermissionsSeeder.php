<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Seed the baseline staff permissions and roles. Staff can create
     * additional roles and permissions at runtime; these are the defaults.
     */
    public function run(): void
    {
        $permissions = collect([
            'view-trainers',
            'manage-trainers',
            'view-staff',
            'manage-staff',
            'manage-roles',
        ])->map(fn (string $name): Permission => Permission::findOrCreate($name));

        $superAdmin = Role::findOrCreate('super-admin');
        $superAdmin->syncPermissions($permissions);

        Role::findOrCreate('trainer-manager')
            ->syncPermissions(['view-trainers', 'manage-trainers']);

        // Staff accounts promoted before staff roles existed keep their access.
        User::where('role', 'staff')
            ->whereDoesntHave('roles')
            ->get()
            ->each(fn (User $user) => $user->assignRole($superAdmin));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
