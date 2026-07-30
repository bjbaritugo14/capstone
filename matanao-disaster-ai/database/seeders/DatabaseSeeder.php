<?php

namespace Database\Seeders;

use App\Models\Barangay;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $superAdminRole = Role::firstOrCreate(['role_name' => 'super_admin']);
        $adminRole = Role::firstOrCreate(['role_name' => 'admin']);
        $mdrrmoRole = Role::firstOrCreate(['role_name' => 'mdrrmo']);
        $validatorRole = Role::firstOrCreate(['role_name' => 'validator']);
        $dswdRole = Role::firstOrCreate(['role_name' => 'dswd']);
        $fieldOfficerRole = Role::firstOrCreate(['role_name' => 'field_officer']);

        User::firstOrCreate(
            ['email' => 'superadmin@example.com'],
            [
                'role_id' => $superAdminRole->role_id,
                'full_name' => 'Super Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'role_id' => $adminRole->role_id,
                'full_name' => 'System Administrator',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        User::firstOrCreate(
            ['email' => 'mdrrmo@example.com'],
            [
                'role_id' => $mdrrmoRole->role_id,
                'full_name' => 'MDRRMO Officer',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        User::firstOrCreate(
            ['email' => 'validator@example.com'],
            [
                'role_id' => $validatorRole->role_id,
                'full_name' => 'Validation Officer',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        User::firstOrCreate(
            ['email' => 'dswd@example.com'],
            [
                'role_id' => $dswdRole->role_id,
                'full_name' => 'DSWD Officer',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        User::firstOrCreate(
            ['email' => 'field@example.com'],
            [
                'role_id' => $fieldOfficerRole->role_id,
                'full_name' => 'Field Officer',
                'password' => Hash::make('password'),
                'status' => 'active',
            ],
        );

        $matanaoBarangays = [
            'Asbang',
            'Asinan',
            'Bagumbayan',
            'Bangkal',
            'Buas',
            'Buri',
            'Cabligan',
            'Camanchiles',
            'Ceboza',
            'Colonsabak',
            'Dongan-Pekong',
            'Kabasagan',
            'Kapok',
            'Kauswagan',
            'Kibao',
            'La Suerte',
            'Langa-an',
            'Lower Marber',
            'Manga',
            'New Katipunan',
            'New Murcia',
            'New Visayas',
            'Poblacion',
            'Saboy',
            'San Jose',
            'San Miguel',
            'San Vicente',
            'Saub',
            'Sinaragan',
            'Sinawilan',
            'Tamlangon',
            'Tibongbong',
            'Towak',
        ];

        foreach ($matanaoBarangays as $barangayName) {
            Barangay::firstOrCreate([
                'barangay_name' => $barangayName,
                'municipality' => 'Matanao',
                'province' => 'Davao del Sur',
            ]);
        }
    }
}
