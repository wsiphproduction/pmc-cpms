<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Non-Compliance Record</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                <tr>
                    <td style="background:#7f1d1d;padding:28px 36px;">
                        <div style="font-size:11px;font-weight:700;color:#fecaca;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                            {{ config('app.name') }}
                        </div>
                        <div style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.2;">
                            Non-Compliance Record {{ $finding->control_no }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:32px 36px 0;">
                        <p style="margin:0 0 6px;font-size:15px;font-weight:700;color:#1e293b;">
                            Dear {{ $finding->contractor_name }},
                        </p>
                        <p style="margin:0;font-size:13.5px;color:#475569;line-height:1.7;">
                            The Project Management Department has recorded a non-compliance on the project below and
                            requires the corrective action stated. Please review it and send your explanation or
                            comments, with any supporting documents, through the link below.
                        </p>
                    </td>
                </tr>

                <tr>
                    <td style="padding:24px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #b91c1c;border-radius:8px;">
                            <tr>
                                <td style="padding:20px 24px;font-size:13px;color:#334155;line-height:1.6;">
                                    <div><strong>Project:</strong> {{ $project->project_no }} — {{ $project->title }}</div>
                                    <div><strong>Date identified:</strong> {{ $finding->date_identified?->format('F d, Y') }}</div>
                                    @if ($finding->target_date)
                                        <div><strong>Target date:</strong> {{ $finding->target_date->format('F d, Y') }}</div>
                                    @endif
                                    @if ($finding->categoryLabel())
                                        <div><strong>Category:</strong> {{ $finding->categoryLabel() }}</div>
                                    @endif
                                    <div style="margin-top:12px;"><strong>Deficiency</strong></div>
                                    <div style="white-space:pre-wrap;">{{ $finding->deficiency }}</div>
                                    <div style="margin-top:12px;"><strong>Required corrective action</strong></div>
                                    <div style="white-space:pre-wrap;">{{ $finding->corrective_action }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                <tr>
                    <td align="center" style="padding:0 36px 32px;">
                        <a href="{{ $portalUrl }}" style="display:inline-block;background:#b91c1c;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;padding:12px 28px;border-radius:8px;">
                            View and Respond
                        </a>
                        <p style="margin:14px 0 0;font-size:11.5px;color:#94a3b8;line-height:1.6;">
                            This link is for your company only — please do not forward it.<br>
                            If the button does not work, copy this address into your browser:<br>
                            <span style="color:#475569;">{{ $portalUrl }}</span>
                        </p>
                    </td>
                </tr>

            </table>
        </td>
    </tr>
</table>
</body>
</html>
