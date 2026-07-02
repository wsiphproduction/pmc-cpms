<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\RedirectResponse;

class NotificationController extends Controller
{
    public function open(Notification $notification): RedirectResponse
    {
        abort_if($notification->recipient !== auth()->id(), 403);

        $notification->update(['is_read' => true]);

        return redirect($notification->link ?? route('dashboard'));
    }

    public function readAll(): RedirectResponse
    {
        Notification::where('recipient', auth()->id())
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return back();
    }
}
