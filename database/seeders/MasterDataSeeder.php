<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Division;
use App\Models\EducationLevel;
use App\Models\EmployeeStatus;
use App\Models\LeaveType;
use App\Models\SalaryDeductionRule;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        // Persentase potongan awal. Seluruhnya dapat diubah super admin/admin.
        $rules = [
            ['present', 'Hadir', 0, 'Hadir tepat waktu, tanpa potongan'],
            ['late', 'Terlambat', 10, 'Hadir melewati batas toleransi'],
            ['absent', 'Absent / Tidak Hadir', 100, 'Tidak hadir tanpa izin yang disetujui'],
            ['leave', 'Izin', 50, 'Izin yang telah disetujui admin'],
            ['sick', 'Sakit', 50, 'Sakit yang telah disetujui admin'],
        ];
        $ruleIds = [];
        foreach ($rules as [$key, $name, $percent, $desc]) {
            $rule = SalaryDeductionRule::updateOrCreate(
                ['status_key' => $key],
                ['name' => $name, 'deduction_percent' => $percent, 'description' => $desc]
            );
            $ruleIds[$key] = $rule->id;
        }

        $leaveTypes = [
            ['Izin Pribadi', 'personal', 'leave', false],
            ['Sakit', 'sick', 'sick', true],
            ['Cuti', 'leave', 'leave', false],
            ['Keperluan Lainnya', 'other', 'leave', false],
        ];
        foreach ($leaveTypes as [$name, $category, $ruleKey, $needsAttachment]) {
            LeaveType::updateOrCreate(
                ['name' => $name],
                [
                    'category' => $category,
                    'salary_deduction_rule_id' => $ruleIds[$ruleKey],
                    'requires_attachment' => $needsAttachment,
                    'is_active' => true,
                ]
            );
        }

        foreach ([
            ['Tetap', 'Karyawan tetap'],
            ['Kontrak', 'Karyawan kontrak'],
            ['Magang', 'Peserta magang'],
            ['Freelance', 'Pekerja lepas'],
        ] as [$name, $desc]) {
            EmployeeStatus::updateOrCreate(['name' => $name], ['description' => $desc, 'is_active' => true]);
        }

        foreach (['SD', 'SMP', 'SMA/SMK', 'D3', 'S1', 'S2', 'S3'] as $level) {
            EducationLevel::updateOrCreate(['name' => $level]);
        }

        // Struktur awal; dapat diubah/ditambah dari menu Data Master.
        $hr = Department::updateOrCreate(
            ['code' => 'HRD'],
            ['name' => 'Human Resources', 'description' => 'Pengelolaan sumber daya manusia', 'is_active' => true]
        );
        $ops = Department::updateOrCreate(
            ['code' => 'OPS'],
            ['name' => 'Operasional', 'description' => 'Kegiatan operasional harian', 'is_active' => true]
        );
        Division::updateOrCreate(['department_id' => $hr->id, 'name' => 'Rekrutmen & Administrasi'], ['is_active' => true]);
        Division::updateOrCreate(['department_id' => $ops->id, 'name' => 'Pelayanan'], ['is_active' => true]);
    }
}
