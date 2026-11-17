<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Support\AuditLabels;
use App\Support\DateInput;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        $term = trim((string) $request->query('q'));
        $like = '%'.addcslashes($term, '%_\\').'%';

        $actions = AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action');
        $action = $actions->contains($request->query('action')) ? (string) $request->query('action') : '';

        $from = DateInput::date($request->query('from'));
        $to = DateInput::date($request->query('to'));
        if ($from && $to && $from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $logs = AuditLog::query()
            ->with(['user' => fn ($q) => $q->withTrashed()->select('id', 'name', 'email', 'role')])
            ->when($action !== '', fn ($q) => $q->where('action', $action))
            ->when($term !== '', fn ($q) => $q->where(function ($w) use ($like) {
                $w->where('ip_address', 'like', $like)
                    ->orWhereHas('user', fn ($u) => $u->withTrashed()->where(function ($x) use ($like) {
                        $x->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('username', 'like', $like);
                    }));
            }))
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from->toDateString()))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to->toDateString()))
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $details = $logs->getCollection()->mapWithKeys(fn (AuditLog $l) => [$l->id => [
            'time' => $l->created_at->format('d/m/Y H:i:s'),
            'user' => $l->user ? $l->user->name.' ('.$l->user->email.')' : 'Sistem / tamu',
            'action' => AuditLabels::action($l->action).' ['.$l->action.']',
            'subject' => AuditLabels::subject($l->auditable_type, $l->auditable_id),
            'ip' => $l->ip_address ?: '-',
            'agent' => $l->user_agent ?: '-',
            'reason' => $l->reason ?: '-',
            'old' => AuditLabels::pretty($l->old_values),
            'new' => AuditLabels::pretty($l->new_values),
        ]]);

        return view('audit-logs.index', [
            'logs' => $logs,
            'details' => $details,
            'actions' => $actions,
            'action' => $action,
            'filtered' => $term !== '' || $action !== '' || $from || $to,
        ]);
    }
}
