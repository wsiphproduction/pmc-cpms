<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use App\Models\ProjectNtp;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            // Absolute URL for the PMC crest on the printed PMD forms. Resolved
            // by the asset helper so it still points at the right place when the
            // app is served from a sub-directory or a separate asset host.
            'logo_url' => asset('logow.png'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],
            'auth' => [
                'user' => $request->user() ? array_merge(
                    $request->user()->toArray(),
                    ['role' => $request->user()->roles->first()?->name]
                ) : null,
            ],
            'notifications' => $request->user()
                ? Notification::where('recipient', $request->user()->id)
                    ->latest()
                    ->take(8)
                    ->get()
                    ->map(fn (Notification $n) => [
                        'id' => $n->id,
                        'message' => $n->message,
                        'link' => $n->link,
                        'is_read' => $n->is_read,
                        'created_at' => $n->created_at?->diffForHumans(),
                    ])
                : [],
            'unread_notifications_count' => $request->user()
                ? Notification::where('recipient', $request->user()->id)->where('is_read', false)->count()
                : 0,
            // NTPs awaiting review, scoped the same way as NtpReviewController::index
            // (admins see all pending; everyone else only their requested projects).
            'ntp_reviews_count' => $request->user()
                ? ProjectNtp::where('status', 'pending_review')
                    ->when(
                        ! $request->user()->hasRole('admin'),
                        fn ($q) => $q->whereHas('project.projectRequest', fn ($rq) => $rq->where('requester_id', $request->user()->id))
                    )
                    ->count()
                : 0,
        ]);
    }
}
