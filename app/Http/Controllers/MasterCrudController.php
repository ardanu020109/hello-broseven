<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pola CRUD bersama untuk seluruh Data Master.
 * Controller turunan hanya mendefinisikan model, konfigurasi tampilan, dan aturan validasi.
 */
abstract class MasterCrudController extends Controller
{
    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /** Konfigurasi tampilan: title, singular, route, search, columns, fields, dst. */
    abstract protected function config(): array;

    abstract protected function rules(Request $request, ?Model $record): array;

    protected function with(): array
    {
        return [];
    }

    protected function withCounts(): array
    {
        return [];
    }

    /** Opsi untuk field bertipe select: ['nama_field' => [value => label]]. */
    protected function selectOptions(): array
    {
        return [];
    }

    /** Normalisasi input sebelum validasi (mis. kode huruf besar). */
    protected function prepare(Request $request): void
    {
    }

    /** Kembalikan pesan jika data tidak boleh dihapus karena masih dipakai. */
    protected function usageBlocker(Model $record): ?string
    {
        return null;
    }

    private function resolved(): array
    {
        return $this->config() + [
            'allow_create' => true,
            'allow_delete' => true,
            'has_active' => true,
            'order' => ['id'],
            'notice' => null,
            'search_placeholder' => 'Cari...',
        ];
    }

    public function index(Request $request): View
    {
        $cfg = $this->resolved();
        $query = $this->modelClass()::query();

        if ($with = $this->with()) {
            $query->with($with);
        }
        foreach ($this->withCounts() as $relation) {
            $query->withCount($relation);
        }

        $term = trim((string) $request->query('q'));
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function (Builder $q) use ($cfg, $like) {
                foreach ($cfg['search'] as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            });
        }

        if ($cfg['has_active'] && in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        foreach ($cfg['order'] as $column) {
            $query->orderBy($column);
        }

        return view('master.index', [
            'cfg' => $cfg,
            'records' => $query->paginate(10)->withQueryString(),
            'options' => $this->selectOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $cfg = $this->resolved();
        abort_unless($cfg['allow_create'], 404);

        $data = $this->validatePayload($request, null);
        $record = $this->modelClass()::create($data);

        AuditLog::record('create', $record, null, $data);

        return redirect()->route($cfg['route'].'.index')
            ->with('success', $cfg['singular'].' berhasil ditambahkan.');
    }

    public function update(Request $request, string $id): RedirectResponse
    {
        $cfg = $this->resolved();
        $record = $this->modelClass()::findOrFail($id);

        $data = $this->validatePayload($request, $record);
        $old = $record->only(array_keys($data));
        $record->update($data);

        AuditLog::record('update', $record, $old, $data);

        return back()->with('success', $cfg['singular'].' berhasil diperbarui.');
    }

    public function destroy(string $id): RedirectResponse
    {
        $cfg = $this->resolved();
        abort_unless($cfg['allow_delete'], 404);

        $record = $this->modelClass()::findOrFail($id);

        if ($reason = $this->usageBlocker($record)) {
            return back()->with('error', $reason);
        }

        $old = $record->toArray();
        $record->delete();

        AuditLog::record('delete', $record, $old);

        return back()->with('success', $cfg['singular'].' berhasil dihapus.');
    }

    public function toggle(string $id): RedirectResponse
    {
        $cfg = $this->resolved();
        abort_unless($cfg['has_active'], 404);

        $record = $this->modelClass()::findOrFail($id);
        $was = (bool) $record->is_active;
        $record->update(['is_active' => !$was]);

        AuditLog::record('toggle_active', $record, ['is_active' => $was], ['is_active' => !$was]);

        return back()->with('success', $cfg['singular'].($was ? ' dinonaktifkan.' : ' diaktifkan.'));
    }

    private function validatePayload(Request $request, ?Model $record): array
    {
        $this->prepare($request);
        $validated = $request->validate($this->rules($request, $record));

        // Checkbox yang tidak dicentang dikirim sebagai "0" oleh input hidden.
        foreach ($this->resolved()['fields'] as $field) {
            if ($field['type'] === 'checkbox') {
                $validated[$field['name']] = $request->boolean($field['name']);
            }
        }

        return $validated;
    }
}
