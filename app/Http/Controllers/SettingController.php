<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    /**
     * Signatory setting keys mapped to their human-readable role labels.
     * These names are printed as the signatories on generated reports.
     */
    private const SIGNATORIES = [
        'signatory_pmd_assistant_manager' => 'PMD Assistant Manager',
        'signatory_pmd_manager'           => 'PMD Manager',
        'signatory_ecs_division_manager'  => 'ECS Division Manager',
    ];

    public function index(): Response
    {
        $signatories = [];
        foreach (self::SIGNATORIES as $key => $label) {
            $signatories[] = [
                'key'   => $key,
                'label' => $label,
                'name'  => (string) Setting::get($key, ''),
            ];
        }

        return Inertia::render('system-settings/index', [
            'projectCompletionKpi' => (int) Setting::get('project_completion_kpi', 80),
            'signatories'          => $signatories,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $rules = [
            'project_completion_kpi' => ['sometimes', 'required', 'integer', 'min:1', 'max:100'],
        ];
        foreach (array_keys(self::SIGNATORIES) as $key) {
            $rules[$key] = ['sometimes', 'nullable', 'string', 'max:191'];
        }

        $data = $request->validate($rules);

        if (array_key_exists('project_completion_kpi', $data)) {
            Setting::set('project_completion_kpi', (string) $data['project_completion_kpi']);
        }

        foreach (array_keys(self::SIGNATORIES) as $key) {
            if (array_key_exists($key, $data)) {
                Setting::set($key, trim((string) ($data[$key] ?? '')));
            }
        }

        return back()->with('success', 'Settings updated successfully.');
    }
}
