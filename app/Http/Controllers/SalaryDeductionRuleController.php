<?php

namespace App\Http\Controllers;

use App\Models\SalaryDeductionRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class SalaryDeductionRuleController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return SalaryDeductionRule::class;
    }

    protected function config(): array
    {
        return [
            'title' => 'Potongan Gaji',
            'singular' => 'Aturan potongan',
            'route' => 'deduction-rules',
            'allow_create' => false,
            'allow_delete' => false,
            'has_active' => false,
            'search' => ['status_key', 'name', 'description'],
            'search_placeholder' => 'Cari aturan potongan...',
            'order' => ['id'],
            'notice' => 'Lima status ini bersifat tetap. Dasar potongan dipilih Super Admin di halaman Data Salary: "Persentase dari gaji harian" memakai kolom Potongan (%), sedangkan "Nominal tetap per kejadian" memakai kolom Potongan Nominal (dibatasi maksimal gaji harian). Perubahan tidak mengubah payroll yang sudah dibuat.',
            'columns' => [
                ['key' => 'status_key', 'label' => 'Kunci Status'],
                ['key' => 'name', 'label' => 'Nama', 'bold' => true],
                ['key' => 'deduction_percent', 'label' => 'Potongan (%)', 'type' => 'percent'],
                ['key' => 'fixed_amount', 'label' => 'Potongan Nominal', 'type' => 'money'],
                ['key' => 'description', 'label' => 'Keterangan', 'type' => 'muted'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Nama Status', 'type' => 'text', 'required' => true, 'maxlength' => 100],
                ['name' => 'deduction_percent', 'label' => 'Persentase Potongan (0 - 100)', 'type' => 'number', 'required' => true, 'min' => 0, 'max' => 100, 'step' => '0.01', 'default' => 0, 'col' => 6],
                ['name' => 'fixed_amount', 'label' => 'Potongan Nominal per Kejadian (Rp)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01', 'default' => 0, 'col' => 6],
                ['name' => 'description', 'label' => 'Keterangan', 'type' => 'text', 'maxlength' => 255],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'deduction_percent' => ['required', 'numeric', 'between:0,100'],
            'fixed_amount' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
