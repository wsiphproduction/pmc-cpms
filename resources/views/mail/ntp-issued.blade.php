<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Notice to Proceed</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                {{-- Header --}}
                <tr>
                    <td style="background:#065f46;padding:28px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="font-size:11px;font-weight:700;color:#6ee7b7;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                                        {{ config('app.name') }}
                                    </div>
                                    <div style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.2;">
                                        Notice to Proceed
                                    </div>
                                </td>
                                <td align="right">
                                    <div style="background:#047857;border-radius:8px;padding:8px 16px;display:inline-block;">
                                        <div style="font-size:11px;color:#a7f3d0;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">NTP No.</div>
                                        <div style="font-size:14px;color:#ffffff;font-weight:800;">{{ $ntp->ntp_no }}</div>
                                    </div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Greeting --}}
                <tr>
                    <td style="padding:32px 36px 0;">
                        <p style="margin:0 0 6px;font-size:15px;font-weight:700;color:#1e293b;">
                            Dear {{ $ntp->contractor_name }},
                        </p>
                        <p style="margin:0;font-size:13.5px;color:#475569;line-height:1.7;">
                            We are pleased to inform you that the Notice to Proceed for the project below has completed
                            its approval and is hereby <strong style="color:#065f46;">issued</strong>. You may proceed
                            with the works in accordance with the approved scope, cost and schedule.
                        </p>
                    </td>
                </tr>

                {{-- Contract terms --}}
                <tr>
                    <td style="padding:24px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #059669;border-radius:8px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <div style="font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:16px;">
                                        Contract Details
                                    </div>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        @php
                                            $rows = [
                                                ['Project Number',  $project->project_no],
                                                ['Project Title',   $project->title],
                                                ['Job Site',        $project->site ?? '—'],
                                                ['Service Contractor', $ntp->contractor_name],
                                                ['Baseline Start',  optional($ntp->baseline_start)->format('F d, Y') ?? '—'],
                                                ['Baseline End',    optional($ntp->baseline_end)->format('F d, Y') ?? '—'],
                                                ['Approved Cost',   'PhP ' . number_format((float) $ntp->approved_cost, 2)],
                                                ['Date Issued',     optional($ntp->issued_date)->format('F d, Y') ?? '—'],
                                            ];
                                        @endphp
                                        @foreach($rows as [$label, $value])
                                        <tr>
                                            <td style="padding:5px 0;font-size:12px;color:#64748b;font-weight:600;width:40%;vertical-align:top;">
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

                {{-- Approval record --}}
                @if(collect($signatories)->where('status', 'approved')->isNotEmpty())
                <tr>
                    <td style="padding:0 36px 24px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;">
                            <tr>
                                <td style="padding:18px 22px;">
                                    <div style="font-size:10px;font-weight:800;color:#15803d;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:12px;">
                                        Approved By
                                    </div>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        @foreach(collect($signatories)->where('status', 'approved') as $step)
                                        <tr>
                                            <td style="padding:4px 0;font-size:12px;color:#166534;font-weight:600;width:52%;">
                                                {{ $step['role_label'] }}{{ $step['actor'] ? ' — ' . $step['actor'] : '' }}
                                            </td>
                                            <td style="padding:4px 0;font-size:12px;color:#15803d;font-weight:700;text-align:right;">
                                                {{ $step['acted_at'] ?? '' }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                @endif

                {{-- Instructions --}}
                <tr>
                    <td style="padding:0 36px 28px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <tr>
                                <td style="padding:18px 22px;">
                                    <div style="font-size:10px;font-weight:800;color:#2563eb;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:10px;">
                                        Next Steps
                                    </div>
                                    <ul style="margin:0;padding-left:18px;font-size:12.5px;color:#1e40af;line-height:2;">
                                        <li>Mobilise in line with the baseline start date shown above.</li>
                                        <li>Coordinate with the project owner before any work begins on site.</li>
                                        <li>Any change to scope, cost or schedule requires an approved variation order.</li>
                                        <li>Acknowledge receipt of this notice by replying to this email.</li>
                                    </ul>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:0 36px 28px;">
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 18px;" />
                        <p style="margin:0 0 4px;font-size:13px;color:#475569;line-height:1.6;">
                            This notice was issued on behalf of <strong style="color:#1e293b;">{{ config('app.name') }}</strong>.
                        </p>
                        <p style="margin:12px 0 0;font-size:11px;color:#94a3b8;">
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
