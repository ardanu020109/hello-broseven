<?php

namespace App\Http\Controllers;

use App\Models\LeaveType;
use App\Models\SalaryDeductionRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeaveTypeController extends MasterCrudController
{
    private const CATEGORIES = [
        'personal' => 'Izin Pribadi',
        'sick' => 'Sakit',
        'leave' => 'Cuti',
        'other' => 'Keperluan Lainnya',
    ];

    protected function modelClass(): string
    {
        return LeaveType::class;
    }

    protected function with(): array
    {
        return ['salaryDeductionRule'];
    }

    protected function selectOptions(): array
    {
        return [
            'salary_deduction_rule_id' => SalaryDeductionRule::orderBy('id')->get()
                ->mapWithKeys(fn ($r) => [
                    $r->id => $r->name.' (potongan '.rtrim(rtrim(number_format((float) $r->deduction_percent, 2, '.', ''), '0'), '.').'%)',
                ])->all(),
        ];
    }

    protected function config(): array
    {
        return [
            'title' => 'Jenis Izin',
            'singular' => 'Jenis izin',
            'route' => 'leave-types',
            'search' => ['name', 'description'],
            'search_placeholder' => 'Cari jenis izin...',
            'order' => ['id'],
            'notice' => 'Aturan potongan gaji pada jenis izin menentukan potongan untuk izin yang sudah DISETUJUI. Izin yang masih menunggu persetujuan tidak dianggap sah.',
            'columns' => [
                ['key' => 'name', 'label' => 'Jenis Izin', 'bold' => true],
                ['key' => 'category', 'label' => 'Kategori', 'type' => 'map', 'map' => self::CATEGORIES],
                ['key' => 'salaryDeductionRule.name', 'label' => 'Aturan Potongan'],
                ['key' => 'requires_attachment', 'label' => 'Wajib Lampiran', 'type' => 'bool'],
                ['key' => 'is_active', 'label' => 'Status', 'type' => 'active'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Nama Jenis Izin', 'type' => 'text', 'required' => true, 'maxlength' => 100],
                ['name' => 'category', 'label' => 'Kategori', 'type' => 'select', 'required' => true, 'options' => self::CATEGORIES],
                ['name' => 'salary_deduction_rule_id', 'label' => 'Aturan Potongan Gaji', 'type' => 'select', 'required' => true],
                ['name' => 'description', 'label' => 'Keterangan', 'type' => 'text', 'maxlength' => 255],
                ['name' => 'requires_attachment', 'label' => 'Wajib melampirkan dokumen (mis. surat dokter)', 'type' => 'checkbox', 'default' => false],
                ['name' => 'is_active', 'label' => 'Jenis izin aktif', 'type' => 'checkbox', 'default' => true],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique('leave_types', 'name')->ignore($record?->id)],
            'category' => ['required', Rule::in(array_keys(self::CATEGORIES))],
            'salary_deduction_rule_id' => ['required', 'integer', 'exists:salary_deduction_rules,id'],
            'description' => ['nullable', 'string', 'max:255'],
            'requires_attachment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        return $record->leaveRequests()->withTrashed()->exists()
            ? "Jenis izin \"{$record->name}\" sudah dipakai pengajuan izin. Nonaktifkan jenis izin ini sebagai gantinya."
            : null;
    }
}
