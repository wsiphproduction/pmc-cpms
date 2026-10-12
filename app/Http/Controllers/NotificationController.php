<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $filter = $request->query('filter') === 'unread' ? 'unread' : 'all';

        $query = Notification::where('recipient', auth()->id())->latest()->latest('id');

        if ($filter === 'unread') {
            $query->where('is_read', false);
        }

        return Inertia::render('notifications/index', [
            'items' => $query->paginate(20)->withQueryString()
                ->through(fn (Notification $n) => [
                    'id' => $n->id,
                    'message' => $n->message,
                    'link' => $n->link,
                    'is_read' => $n->is_read,
                    'created_at' => $n->created_at?->toIso8601String(),
                    'created_ago' => $n->created_at?->diffForHumans(),
                ]),
            'filter' => $filter,
            'total_count' => Notification::where('recipient', auth()->id())->count(),
        ]);
    }

    public function open(Notification $notification): RedirectResponse
    {
        abort_if($notification->recipient !== auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return redirect($notification->link ?? route('dashboard'));
    }

    public function read(Notification $notification): RedirectResponse
    {
        abort_if($notification->recipient !== auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return back();
    }

    public function readAll(): RedirectResponse
    {
        Notification::where('recipient', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back();
    }
}
