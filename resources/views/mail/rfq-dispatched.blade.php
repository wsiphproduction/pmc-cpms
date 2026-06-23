<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Request for Quotation</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                {{-- Header --}}
                <tr>
                    <td style="background:#1e3a8a;padding:28px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td>
                                    <div style="font-size:11px;font-weight:700;color:#93c5fd;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                                        {{ config('app.name') }}
                                    </div>
                                    <div style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.2;">
                                        Request for Quotation
                                    </div>
                                </td>
                                <td align="right">
                                    <div style="background:#2563eb;border-radius:8px;padding:8px 16px;display:inline-block;">
                                        <div style="font-size:11px;color:#bfdbfe;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;">RFQ Ref</div>
                                        <div style="font-size:14px;color:#ffffff;font-weight:800;">{{ $project->project_no }}</div>
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
                            Dear {{ $rfq->contractor_name }},
                        </p>
                        <p style="margin:0;font-size:13.5px;color:#475569;line-height:1.7;">
                            We are pleased to invite you to submit a quotation for the following project.
                            Please review the details below and submit your best offer by the indicated due date.
                        </p>
                    </td>
                </tr>

                {{-- Project Details Card --}}
                <tr>
                    <td style="padding:24px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #2563eb;border-radius:8px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <div style="font-size:10px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:16px;">
                                        Project Information
                                    </div>
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        @php
                                            $rows = [
                                                ['Project Number',  $project->project_no],
                                                ['Project Title',   $project->title],
                                                ['Job Site',        $project->site ?? '—'],
                                                ['Project Owner',   $project->project_manager_name ?? '—'],
                                                ['Date Sent',       optional($rfq->sent_date)->format('F d, Y') ?? date('F d, Y')],
                                                ['Due Date',        optional($rfq->due_date)->format('F d, Y') ?? 'To be advised'],
                                            ];
                                        @endphp
                                        @foreach($rows as [$label, $value])
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

                {{-- Instructions --}}
                <tr>
                    <td style="padding:0 36px 28px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;">
                            <tr>
                                <td style="padding:18px 22px;">
                                    <div style="font-size:10px;font-weight:800;color:#2563eb;text-transform:uppercase;letter-spacing:0.8px;margin-bottom:10px;">
                                        Submission Instructions
                                    </div>
                                    <ul style="margin:0;padding-left:18px;font-size:12.5px;color:#1e40af;line-height:2;">
                                        <li>Prepare your itemized quotation based on the project scope.</li>
                                        <li>Submit your quotation on or before the due date indicated above.</li>
                                        <li>For clarifications, reply to this email or contact the project owner directly.</li>
                                        <li>All submitted quotes are treated with strict confidentiality.</li>
                                    </ul>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Divider --}}
                <tr>
                    <td style="padding:0 36px;">
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0;" />
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:24px 36px;">
                        <p style="margin:0 0 4px;font-size:13px;color:#475569;line-height:1.6;">
                            This RFQ was sent on behalf of <strong style="color:#1e293b;">{{ config('app.name') }}</strong>.
                            Please do not reply directly to this automated message.
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
