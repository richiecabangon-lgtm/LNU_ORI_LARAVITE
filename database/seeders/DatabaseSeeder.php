<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\AcademicYear;
use App\Models\College;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $cas = College::query()->create([
            'code' => 'CAS',
            'name' => 'College of Arts and Sciences',
            'is_active' => true,
        ]);

        $coe = College::query()->create([
            'code' => 'COE',
            'name' => 'College of Education',
            'is_active' => true,
        ]);

        $itDept = Department::query()->create([
            'college_id' => $cas->id,
            'code' => 'IT',
            'name' => 'Information Technology Department',
            'is_active' => true,
        ]);

        Department::query()->create([
            'college_id' => $cas->id,
            'code' => 'MATH',
            'name' => 'Mathematics Department',
            'is_active' => true,
        ]);

        Department::query()->create([
            'college_id' => $coe->id,
            'code' => 'ELEM',
            'name' => 'Elementary Education Department',
            'is_active' => true,
        ]);

        AcademicYear::query()->create([
            'name' => '2025-2026',
            'starts_on' => '2025-08-01',
            'ends_on' => '2026-07-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        AcademicYear::query()->create([
            'name' => '2024-2025',
            'starts_on' => '2024-08-01',
            'ends_on' => '2025-07-31',
            'is_current' => false,
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'System Administrator',
            'email' => 'admin@lnu.edu.ph',
            'password' => Hash::make('password'),
            'role' => UserRole::Admin,
            'employee_id' => 'ADM-001',
            'designation' => 'ORI Administrator',
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Research Coordinator',
            'email' => 'coordinator@lnu.edu.ph',
            'password' => Hash::make('password'),
            'role' => UserRole::Coordinator,
            'employee_id' => 'COORD-001',
            'designation' => 'Research Coordinator',
            'college_id' => $cas->id,
            'department_id' => $itDept->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);

        User::query()->create([
            'name' => 'Faculty Researcher',
            'email' => 'faculty@lnu.edu.ph',
            'password' => Hash::make('password'),
            'role' => UserRole::Faculty,
            'employee_id' => 'FAC-001',
            'designation' => 'Assistant Professor',
            'college_id' => $cas->id,
            'department_id' => $itDept->id,
            'email_verified_at' => now(),
            'is_active' => true,
        ]);
    }
}
