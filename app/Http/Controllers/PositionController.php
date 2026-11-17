<?php

namespace App\Http\Controllers;

use App\Models\Position;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PositionController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return Position::class;
    }

    protected function withCounts(): array
    {
        return ['employees'];
    }

    protected function config(): array
    {
        return [
            'title' => 'Data Jabatan',
            'singular' => 'Jabatan',
            'route' => 'positions',
            'search' => ['code', 'name', 'description'],
            'search_placeholder' => 'Cari kode, nama, atau deskripsi jabatan...',
            'order' => ['name'],
            'notice' => 'Gaji harian jabatan dipakai sebagai dasar perhitungan gaji (Fase 8). Perubahan nominal tidak mengubah payroll yang sudah ditutup.',
            'columns' => [
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'name', 'label' => 'Nama Jabatan', 'bold' => true],
                ['key' => 'daily_salary', 'label' => 'Gaji Harian', 'type' => 'money'],
                ['key' => 'employees_count', 'label' => 'Karyawan'],
                ['key' => 'is_active', 'label' => 'Status', 'type' => 'active'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Kode Jabatan', 'type' => 'text', 'required' => true, 'col' => 4, 'maxlength' => 20, 'placeholder' => 'STF'],
                ['name' => 'name', 'label' => 'Nama Jabatan', 'type' => 'text', 'required' => true, 'col' => 8, 'maxlength' => 100],
                ['name' => 'daily_salary', 'label' => 'Gaji Harian (Rp)', 'type' => 'number', 'required' => true, 'min' => 0, 'step' => '0.01', 'default' => 0],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'maxlength' => 1000],
                ['name' => 'is_active', 'label' => 'Jabatan aktif', 'type' => 'checkbox', 'default' => true],
            ],
        ];
    }

    protected function prepare(Request $request): void
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('positions', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'daily_salary' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        return $record->employees()->withTrashed()->exists()
            ? "Jabatan \"{$record->name}\" masih dipakai karyawan sehingga tidak dapat dihapus. Nonaktifkan jabatan ini sebagai gantinya."
            : null;
    }
}
