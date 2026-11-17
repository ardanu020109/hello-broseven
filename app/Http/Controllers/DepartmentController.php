<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DepartmentController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return Department::class;
    }

    protected function withCounts(): array
    {
        return ['employees', 'divisions'];
    }

    protected function config(): array
    {
        return [
            'title' => 'Data Departemen',
            'singular' => 'Departemen',
            'route' => 'departments',
            'search' => ['code', 'name', 'description'],
            'search_placeholder' => 'Cari kode, nama, atau deskripsi departemen...',
            'order' => ['name'],
            'columns' => [
                ['key' => 'code', 'label' => 'Kode'],
                ['key' => 'name', 'label' => 'Nama Departemen', 'bold' => true],
                ['key' => 'description', 'label' => 'Deskripsi', 'type' => 'muted'],
                ['key' => 'divisions_count', 'label' => 'Divisi'],
                ['key' => 'employees_count', 'label' => 'Karyawan'],
                ['key' => 'is_active', 'label' => 'Status', 'type' => 'active'],
            ],
            'fields' => [
                ['name' => 'code', 'label' => 'Kode Departemen', 'type' => 'text', 'required' => true, 'col' => 4, 'maxlength' => 20, 'placeholder' => 'HRD'],
                ['name' => 'name', 'label' => 'Nama Departemen', 'type' => 'text', 'required' => true, 'col' => 8, 'maxlength' => 100],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'maxlength' => 1000],
                ['name' => 'is_active', 'label' => 'Departemen aktif', 'type' => 'checkbox', 'default' => true],
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
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/', Rule::unique('departments', 'code')->ignore($record?->id)],
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        if ($record->employees()->withTrashed()->exists()) {
            return "Departemen \"{$record->name}\" masih dipakai karyawan. Nonaktifkan departemen ini sebagai gantinya.";
        }
        if ($record->divisions()->exists()) {
            return "Departemen \"{$record->name}\" masih memiliki divisi. Hapus atau pindahkan divisinya terlebih dahulu.";
        }

        return null;
    }
}
