<?php

use App\Models\Project;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function makeProjectForHealth(int $completion, $deadline): Project
{
    return Project::create([
        'project_no' => 'PRJ-HEALTH-' . uniqid(),
        'title' => 'Health Test Project',
        'site' => 'Main Plant',
        'asset_id' => 'A1',
        'class_name' => 'Minor',
        'priority' => '1',
        'status_key' => 'ONGOING',
        'work_force' => 'In-House',
        'wr_no' => 'WR-1',
        'wr_date' => now(),
        'dept_owner' => 'Engineering',
        'cost_code' => 'CC-001',
        'category' => 'General',
        'service_type' => 'Repair',
        'deadline' => $deadline,
        'completion_percent' => $completion,
    ]);
}

it('reports "Ahead" when a project reaches 100% on or before its deadline', function () {
    expect(makeProjectForHealth(100, now()->addDays(5))->health())->toBe('Ahead');
    expect(makeProjectForHealth(100, now())->health())->toBe('Ahead');
});

it('reports "Completed" when a project reaches 100% after its deadline', function () {
    expect(makeProjectForHealth(100, now()->subDays(5))->health())->toBe('Completed');
});

it('still reports On-Time / Delayed for in-progress projects', function () {
    // Fresh project (0 days elapsed) with time left is On-Time.
    expect(makeProjectForHealth(10, now()->addDays(30))->health())->toBe('On-Time');
});
