<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = $request->user()->id;
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $notifications = AppNotification::query()
            ->where('user_id', $userId)
            ->when($filter === 'unread', fn ($q) => $q->whereNull('read_at'))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
            'filter' => $filter,
            'counts' => [
                'all' => AppNotification::where('user_id', $userId)->count(),
                'unread' => AppNotification::where('user_id', $userId)->whereNull('read_at')->count(),
            ],
        ]);
    }

    /** Tandai terbaca, lalu buka halaman tujuan (hanya path internal). */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = AppNotification::where('user_id', $request->user()->id)->findOrFail($id);
        $notification->markAsRead();

        $path = $notification->url ? parse_url($notification->url, PHP_URL_PATH) : null;
        if (is_string($path) && str_starts_with($path, '/')) {
            $query = parse_url($notification->url, PHP_URL_QUERY);

            return redirect()->to(url($path.($query ? '?'.$query : '')));
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    public function destroy(Request $request, string $id): RedirectResponse
    {
        AppNotification::where('user_id', $request->user()->id)->findOrFail($id)->delete();

        return back()->with('success', 'Notifikasi dihapus.');
    }
}
