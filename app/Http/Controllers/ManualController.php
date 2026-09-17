<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ManualContent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The user manuals, read from inside the system.
 *
 * The PDFs are built ahead of time into public/manuals (see the manuals:build
 * command) and served as static files, so this controller only decides which
 * ones a given user is offered: the booklet for their own role, the complete
 * edition, and — for the administrator, who supports everybody — all of them.
 */
class ManualController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = $user->primaryRole();
        $mine = ManualContent::slugForRole($role);
        $manuals = ManualContent::manuals();

        // The administrator hands these out, so they see every booklet. Everyone
        // else is offered their own and the complete edition.
        $offered = $user->hasRole(User::ROLE_ADMIN)
            ? array_keys($manuals)
            : array_values(array_unique([$mine, 'complete']));

        return Inertia::render('manuals/index', [
            'mine' => $mine,
            'version' => ManualContent::VERSION,
            'docNo' => ManualContent::DOC_NO,
            'manuals' => array_values(array_map(
                fn (string $slug) => $this->present($slug, $manuals[$slug], $slug === $mine),
                $offered
            )),
        ]);
    }

    /**
     * One manual as the page needs it. `available` is false when the PDF has
     * not been built yet, so the page can say so rather than serve a dead link.
     */
    private function present(string $slug, array $manual, bool $isMine): array
    {
        $path = public_path("manuals/{$slug}.pdf");
        $has = is_file($path);

        return [
            'slug' => $slug,
            'title' => $manual['subtitle'],
            'audience' => $manual['audience'],
            'highlights' => $manual['at_a_glance'],
            'sections' => count($manual['sections']),
            'is_mine' => $isMine,
            'available' => $has,
            'url' => $has ? asset("manuals/{$slug}.pdf") : null,
            'size_kb' => $has ? (int) round(filesize($path) / 1024) : null,
            'updated_at' => $has ? date('M d, Y', filemtime($path)) : null,
        ];
    }
}
