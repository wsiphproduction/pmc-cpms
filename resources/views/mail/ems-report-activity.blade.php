<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>EMS Survey Report Update</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                {{-- Header --}}
                <tr>
                    <td style="background:#14532d;padding:28px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="font-size:11px;font-weight:700;color:#86efac;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                                        {{ config('app.name') }}
                                    </div>
                                    <div style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.2;">
                                        EMS Survey Report Update
                                    </div>
                                </td>
                                <td align="right">
                                    <div style="background:#166534;border-radius:8px;padding:8px 16px;display:inline-block;">
                                        <div style="font-size:11px;color:#bbf7d0;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">Control No.</div>
                                        <div style="font-size:14px;color:#ffffff;font-weight:800;">{{ $report->control_no }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- What happened --}}
                <tr>
                    <td style="padding:32px 36px 0;">
                        <p style="margin:0 0 10px;font-size:13.5px;color:#475569;line-height:1.7;">
                            Hi <strong style="color:#0f172a;">{{ $recipient->name }}</strong>,
                        </p>
                        <p style="margin:0;font-size:14.5px;color:#0f172a;font-weight:600;line-height:1.6;">
                            {{ $headline }}
                        </p>
                    </td>
                </tr>

                {{-- Detail card --}}
                <tr>
                    <td style="padding:24px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #16a34a;border-radius:8px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        @foreach([
                                            ['Project', $project ? $project->project_no . ' – ' . $project->title : '—'],
                                            ['Location', $report->location ?: '—'],
                                            ['Contractor', $report->contractor ?: '—'],
                                            ['Target Dates', $report->target_start && $report->target_end
                                                ? $report->target_start->format('M d, Y') . ' – ' . $report->target_end->format('M d, Y')
                                                : '—'],
                                            ['Status', $statusLabel . ($awaitingLabel ? ' · with the ' . $awaitingLabel : '')],
                                        ] as [$label, $value])
                                        <tr>
                                            <td style="padding:5px 0;font-size:12px;color:#64748b;font-weight:600;width:38%;vertical-align:top;">
                                                {{ $label }}
                                            </td>
                                            <td style="padding:5px 0;font-size:12.5px;color:#0f172a;font-weight:700;">
                                                {{ $value }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- CTA --}}
                <tr>
                    <td align="center" style="padding:0 36px 32px;">
                        <a href="{{ $url }}"
                           style="display:inline-block;background:#16a34a;color:#ffffff;font-size:13.5px;font-weight:800;text-decoration:none;padding:12px 30px;border-radius:8px;">
                            Open in {{ config('app.name') }}
                        </a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:0 36px 28px;">
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 18px;" />
                        <p style="margin:0;font-size:11px;color:#94a3b8;">
                            This is an automated message. The same update is in your notifications when you sign in.<br />
                            © {{ date('Y') }} {{ config('app.name') }} · Project Construction Management System
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>

</body>
</html>
