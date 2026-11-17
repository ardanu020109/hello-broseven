<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EducationLevelController extends MasterCrudController
{
    protected function modelClass(): string
    {
        return EducationLevel::class;
    }

    protected function withCounts(): array
    {
        return ['employees'];
    }

    protected function config(): array
    {
        return [
            'title' => 'Data Pendidikan',
            'singular' => 'Pendidikan',
            'route' => 'education-levels',
            'has_active' => false,
            'search' => ['name', 'description'],
            'search_placeholder' => 'Cari jenjang pendidikan...',
            'order' => ['id'],
            'columns' => [
                ['key' => 'name', 'label' => 'Jenjang', 'bold' => true],
                ['key' => 'description', 'label' => 'Keterangan', 'type' => 'muted'],
                ['key' => 'employees_count', 'label' => 'Karyawan'],
            ],
            'fields' => [
                ['name' => 'name', 'label' => 'Jenjang Pendidikan', 'type' => 'text', 'required' => true, 'maxlength' => 50, 'placeholder' => 'S1'],
                ['name' => 'description', 'label' => 'Keterangan', 'type' => 'text', 'maxlength' => 255],
            ],
        ];
    }

    protected function rules(Request $request, ?Model $record): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('education_levels', 'name')->ignore($record?->id)],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }

    protected function usageBlocker(Model $record): ?string
    {
        return $record->employees()->withTrashed()->exists()
            ? "Jenjang \"{$record->name}\" masih dipakai data karyawan sehingga tidak dapat dihapus."
            : null;
    }
}
