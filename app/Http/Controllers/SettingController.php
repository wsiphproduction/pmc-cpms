<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('system-settings/index', [
            'projectCompletionKpi' => (int) Setting::get('project_completion_kpi', 80),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'project_completion_kpi' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        Setting::set('project_completion_kpi', (string) $data['project_completion_kpi']);

        return back()->with('success', 'Settings updated successfully.');
    }
}
