<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $roles = [
            ['name' => 'Superuser', 'description' => 'Has complete access to all system functions'],
            ['name' => 'Admin', 'description' => 'Administrative access to most system functions'],
            ['name' => 'Owner', 'description' => 'Property owner/landlord portal access'],
            ['name' => 'Tenant', 'description' => 'Tenant portal access to search and apply for properties'],
            ['name' => 'Staff', 'description' => 'Access to administrative functions'],
            ['name' => 'Contractor', 'description' => 'Verified tradesperson contractor portal access (jobs only)'],
        ];

        foreach ($roles as $role) {
            DB::table('auth_roles')->updateOrInsert(
                ['name' => $role['name']],
                [
                    'name' => $role['name'],
                    'description' => $role['description'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        // Create superuser
        $superuserId = DB::table('auth_users')->insertGetId([
            'name' => 'System Administrator',
            'email' => 'admin@system.local',
            'username' => 'admin',
            'password' => Hash::make('password123'),
            'status' => 'active',
            'email_verified_at' => now(),
            'password_changed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign superuser role
        $superuserRoleId = DB::table('auth_roles')->where('name', 'Superuser')->value('id');
        DB::table('auth_user_roles')->updateOrInsert(
            ['user_id' => $superuserId, 'role_id' => $superuserRoleId],
            [
                'user_id' => $superuserId,
                'role_id' => $superuserRoleId,
                'assigned_by' => $superuserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Demo users for the two main portal personas
        $demoUsers = [
            [
                'name' => 'Demo Owner',
                'email' => 'owner@dzimba.local',
                'username' => 'owner',
                'role' => 'Owner',
                'verified' => true,
            ],
            [
                'name' => 'Demo Tenant',
                'email' => 'tenant@dzimba.local',
                'username' => 'tenant',
                'role' => 'Tenant',
            ],
            [
                'name' => 'Demo Admin',
                'email' => 'staff@dzimba.local',
                'username' => 'staff',
                'role' => 'Admin',
            ],
            [
                'name' => 'Demo Contractor',
                'email' => 'contractor@dzimba.local',
                'username' => 'contractor',
                'role' => 'Contractor',
                'verified' => true,
            ],
        ];

        foreach ($demoUsers as $userData) {
            $userId = DB::table('auth_users')->insertGetId([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'username' => $userData['username'],
                'password' => Hash::make('password123'),
                'status' => 'active',
                'email_verified_at' => now(),
                'password_changed_at' => now(),
                'verified' => $userData['verified'] ?? false,
                'verified_at' => isset($userData['verified']) && $userData['verified'] ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $roleId = DB::table('auth_roles')->where('name', $userData['role'])->value('id');
            DB::table('auth_user_roles')->insert([
                'user_id' => $userId,
                'role_id' => $roleId,
                'assigned_by' => $superuserId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}