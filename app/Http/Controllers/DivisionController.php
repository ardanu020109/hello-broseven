<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Division;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DivisionController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return Division::class;
    }

    protected function with(): array
    {
        return ['department'];
    }

    protected function withCounts(): array
    {
        return ['employees'];
    }

    protected function selectOptions(): array
    {
        return ['department_id' => Department::orderBy('name')->pluck('name', 'id')->all()];
    }

    protected function config(): array
    {
        return [
            'title' => 'Data Divisi',
            'singular' => 'Divisi',
            'route' => 'divisions',
            'search' => ['name', 'description'],
            'search_placeholder' => 'Cari nama atau deskripsi divisi...',
            'order' => ['department_id', 'name'],
            'columns' => [
                ['key' => 'name', 'label' => 'Nama Divisi', 'bold' => true],
                ['key' => 'department.name', 'label' => 'Departemen'],
                ['key' => 'description', 'label' => 'Deskripsi', 'type' => 'muted'],
                ['key' => 'employees_count', 'label' => 'Karyawan'],
                ['key' => 'is_active', 'label' => 'Status', 'type' => 'active'],
            ],
            'fields' => [
                ['name' => 'department_id', 'label' => 'Departemen', 'type' => 'select', 'required' => true],
                ['name' => 'name', 'label' => 'Nama Divisi', 'type' => 'text', 'required' => true, 'maxlength' => 100],
                ['name' => 'description', 'label' => 'Deskripsi', 'type' => 'textarea', 'maxlength' => 1000],
                ['name' => 'is_active', 'label' => 'Divisi aktif', 'type' => 'checkbox', 'default' => true],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'department_id' => ['required', Rule::exists('departments', 'id')->whereNull('deleted_at')],
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('divisions', 'name')
                    ->where('department_id', $request->input('department_id'))
                    ->ignore($record?->id),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        return $record->employees()->withTrashed()->exists()
            ? "Divisi \"{$record->name}\" masih dipakai karyawan. Nonaktifkan divisi ini sebagai gantinya."
            : null;
    }
}
