<?php

namespace App\Http\Controllers;

use App\Models\EmployeeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeeStatusController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return EmployeeStatus::class;
    }

    protected function withCounts(): array
    {
        return ['employees'];
    }

    protected function config(): array
    {
        return [
            'title' => 'Status Karyawan',
            'singular' => 'Status karyawan',
            'route' => 'employee-statuses',
            'search' => ['name', 'description'],
            'search_placeholder' => 'Cari status karyawan...',
            'order' => ['id'],
            'columns' => [
                ['key' => 'name', 'label' => 'Status', 'bold' => true],
                ['key' => 'description', 'label' => 'Keterangan', 'type' => 'muted'],
                ['key' => 'employees_count', 'label' => 'Karyawan'],
                ['key' => 'is_active', 'label' => 'Status Data', 'type' => 'active'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Nama Status', 'type' => 'text', 'required' => true, 'maxlength' => 50, 'placeholder' => 'Tetap'],
                ['name' => 'description', 'label' => 'Keterangan', 'type' => 'text', 'maxlength' => 255],
                ['name' => 'is_active', 'label' => 'Status aktif', 'type' => 'checkbox', 'default' => true],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('employee_statuses', 'name')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        return $record->employees()->withTrashed()->exists()
            ? "Status \"{$record->name}\" masih dipakai karyawan. Nonaktifkan status ini sebagai gantinya."
            : null;
    }
}
