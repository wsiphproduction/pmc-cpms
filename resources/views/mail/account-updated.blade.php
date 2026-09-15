<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Account Updated</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:40px 0;">
    <tr>
        <td align="center">
            <table width="620" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08);">

                {{-- Header --}}
                <tr>
                    <td style="background:#065f46;padding:28px 36px;">
                        <div style="font-size:11px;font-weight:700;color:#6ee7b7;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                            {{ config('app.name') }}
                        </div>
                        <div style="font-size:22px;font-weight:800;color:#ffffff;line-height:1.2;">
                            Account Updated
                        </div>
                    </td>
                </tr>

                {{-- Summary --}}
                <tr>
                    <td style="padding:32px 36px 0;">
                        <p style="margin:0;font-size:13.5px;color:#475569;line-height:1.7;">
                            Hi <strong style="color:#0f172a;">{{ $user->name }}</strong>,
                            the following changes were saved to your account on
                            <strong style="color:#0f172a;">{{ $changedAt }}</strong>.
                        </p>
                    </td>
                </tr>

                {{-- Detail card --}}
                <tr>
                    <td style="padding:24px 36px;">
                        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f8fafc;border:1px solid #e2e8f0;border-left:4px solid #059669;border-radius:8px;">
                            <tr>
                                <td style="padding:20px 24px;">
                                    <table width="100%" cellpadding="0" cellspacing="0">
                                        @foreach($changes as [$label, $before, $after])
                                        <tr>
                                            <td style="padding:5px 0;font-size:12px;color:#64748b;font-weight:600;width:38%;vertical-align:top;">
                                                {{ $label }}
                                            </td>
                                            <td style="padding:5px 0;font-size:12.5px;color:#0f172a;font-weight:700;">
                                                @if($before !== '')
                                                    <span style="color:#94a3b8;font-weight:500;text-decoration:line-through;">{{ $before }}</span>
                                                    &rarr;
                                                @endif
                                                {{ $after }}
                                            </td>
                                        </tr>
                                        @endforeach
                                    </table>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>

                {{-- Warning --}}
                <tr>
                    <td style="padding:0 36px 24px;">
                        <p style="margin:0;font-size:13px;color:#475569;line-height:1.7;">
                            If you made these changes, no further action is needed. If you did not,
                            change your password right away and let your administrator know.
                        </p>
                    </td>
                </tr>

                {{-- CTA --}}
                <tr>
                    <td align="center" style="padding:0 36px 32px;">
                        <a href="{{ $accountUrl }}"
                           style="display:inline-block;background:#059669;color:#ffffff;font-size:13.5px;font-weight:800;text-decoration:none;padding:12px 30px;border-radius:8px;">
                            Open My Account
                        </a>
                    </td>
                </tr>

                {{-- Footer --}}
                <tr>
                    <td style="padding:0 36px 28px;">
                        <hr style="border:none;border-top:1px solid #e2e8f0;margin:0 0 18px;" />
                        <p style="margin:0;font-size:11px;color:#94a3b8;">
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
