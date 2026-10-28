<?php

use App\Support\RecentDeploys;

beforeEach(function () {
    $this->log = tempnam(sys_get_temp_dir(), 'deploy');

    // As scripts/deploy.sh writes it, migrate and optimize output included.
    file_put_contents($this->log, implode("\n", [
        '[2026-10-19 10:00:01] Deploying 1111111 -> aaaaaaa',
        '   INFO  Nothing to migrate.',
        '[2026-10-19 10:00:09] Deployed aaaaaaa: chore: name the full system on the login page badge',
        '[2026-10-22 09:00:01] Deploying aaaaaaa -> bbbbbbb',
        '[2026-10-22 09:00:08] Deployed bbbbbbb: fix: stop copying someone@example.com on RFQ and NTP mails',
        '[2026-10-23 09:00:01] Deploying bbbbbbb -> ccccccc',
        '[2026-10-23 09:00:05] Deploy FAILED — site brought back up on whatever code is checked out.',
        '[2026-10-26 09:00:08] Deployed ddddddd: feat(ntp): email project-side reviewers when an NTP reaches their step',
        '[2026-10-27 09:00:08] Deployed ddddddd: feat(ntp): email project-side reviewers when an NTP reaches their step',
        "[2026-10-28 15:56:02] Deployed eeeeeee: Merge branch 'master' into production",
        '[2026-10-28 15:57:02] Deployed fffffff: feat: weekly reminders for stalled NTPs',
        '',
    ]));
});

afterEach(fn () => @unlink($this->log));

it('lists the user-facing deploys, newest first, once each', function () {
    expect(RecentDeploys::latest(5, $this->log))->toBe([
        ['sha' => 'fffffff', 'type' => 'New', 'text' => 'Weekly reminders for stalled NTPs', 'date' => 'Oct 28, 2026'],
        ['sha' => 'ddddddd', 'type' => 'New', 'text' => 'Email project-side reviewers when an NTP reaches their step', 'date' => 'Oct 27, 2026'],
        ['sha' => 'bbbbbbb', 'type' => 'Fix', 'text' => 'Stop copying on RFQ and NTP mails', 'date' => 'Oct 22, 2026'],
    ]);
});

it('stops at the limit', function () {
    expect(RecentDeploys::latest(2, $this->log))->toHaveCount(2);
});

it('shows nothing where there is no deploy log, as on a dev machine', function () {
    expect(RecentDeploys::latest(5, $this->log . '.missing'))->toBe([]);
});

it('hands the updates to the login page', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/login')->has('updates'));
});
