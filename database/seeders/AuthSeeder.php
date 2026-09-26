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

        // Demo tenant profile (Presentation Release S4) - identity scans are not seeded
        $tenantId = DB::table('auth_users')->where('username', 'tenant')->value('id');
        DB::table('tenant_profiles')->updateOrInsert(
            ['user_id' => $tenantId],
            [
                'user_id' => $tenantId,
                'phone' => '+263 771 234 567',
                'city' => 'Harare',
                'employment_status' => 'employed',
                'salary_band' => '501_to_1000',
                'preferred_contact' => 'platform',
                'about' => 'Demo tenant profile - quiet professional with references available.',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Demo owner KYC (Presentation Release S5): both identity types recorded
        // as approved so the derived badge tier lands on gold for the marketplace
        // demo. These are metadata-only rows - no scan files are written and there
        // is no owner KYC page to ever download them (downloads 404 on a missing
        // file by design). The badge itself is never seeded directly; it is
        // recomputed through the KYC tier ladder below.
        $ownerId = DB::table('auth_users')->where('username', 'owner')->value('id');
        foreach (['national_id', 'driving_licence'] as $type) {
            DB::table('identity_documents')->updateOrInsert(
                ['user_id' => $ownerId, 'type' => $type],
                [
                    'user_id' => $ownerId,
                    'type' => $type,
                    'file_path' => 'kyc/'.$ownerId.'/'.$type.'-demo.jpg',
                    'original_name' => ($type === 'national_id' ? 'demo-national-id' : 'demo-driving-licence').'.jpg',
                    'mime' => 'image/jpeg',
                    'size' => 0,
                    'status' => 'approved',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }
        $owner = \App\Models\User::whereKey($ownerId)->first();
        if ($owner) {
            app(\App\Services\TenantProfileService::class)->recomputeBadgeTier($owner);
        }
    }
}