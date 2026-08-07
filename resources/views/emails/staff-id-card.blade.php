<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CMIH Staff ID Card</title>
        <style>
            body { margin: 0; padding: 0; background: #0a0a0a; color: #ffffff; font-family: 'Sora', Arial, sans-serif; }
            .container { max-width: 680px; margin: 0 auto; padding: 32px 24px; }
            .card { background: #111111; border: 1px solid #333333; border-radius: 18px; padding: 24px; }
            .label { font-size: 12px; letter-spacing: 0.3em; text-transform: uppercase; color: #f0f0f0; } /* lighter */
            .title { font-size: 24px; margin: 10px 0 18px; color: #ffffff; }
            .id-card { width: 100%; background: #2a0506; border: 1px solid #e21c1e; border-radius: 16px; padding: 18px; }
            .photo { width: 180px; height: 180px; border-radius: 14px; object-fit: cover; border: 1px solid #ffffff; display: block; background-color: #222222; }
            .name { font-size: 20px; margin: 4px 0 6px; color: #ffffff; font-weight: 700; }
            .meta { font-size: 13px; letter-spacing: 0.1em; text-transform: uppercase; color: #dddddd; margin-bottom: 2px; } /* lighter */
            .field { font-size: 14px; color: #e0e0e0; margin-top: 8px; line-height: 1.4; } /* lighter */
            .field span { color: #ffffff; font-weight: 600; }
            .cta { display: inline-block; margin-top: 18px; padding: 12px 24px; border-radius: 999px; background-color: #e21c1e; color: #ffffff; text-decoration: none; font-size: 14px; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; }
            .footer { margin-top: 24px; font-size: 14px; color: #b0b0b0; text-align: center; } /* lighter */
        </style>
    </head>
    <body>
        @php
            $roleLabel = \App\Models\User::roleLabel($user->access_role);
            $departmentLabel = \App\Models\User::departmentLabel($user->department);
            $displayRole = $user->job_title ?: ($user->position_title ?: 'Team Member');

            $logoPath = public_path('images/logo/logo-light.png');
            $photoPath = $user->profile_photo_path
                ? storage_path('app/public/'.$user->profile_photo_path)
                : public_path('images/logo/logo-light.png');

            $logoAvailable = $logoPath && file_exists($logoPath);
            $photoAvailable = $photoPath && file_exists($photoPath);
            $logoSrc = $logoAvailable ? $message->embed($logoPath) : asset('images/logo/logo-light.png');
            $photoSrc = $photoAvailable ? $message->embed($photoPath) : $user->profilePhotoUrl();
        @endphp
        <div class="container">
            <div class="card">
                <div class="label">CMIH Africa</div>
                <div class="title">Your Staff ID Card</div>
                <table class="id-card" role="presentation" cellpadding="0" cellspacing="0" width="100%">
                    <tr>
                        <td style="padding-bottom: 12px;">
                            <img src="{{ $logoSrc }}" alt="CMIH Africa" width="120" style="display:block;">
                        </td>
                    </tr>
                    <tr>
                        <td>
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td width="190" valign="top" style="padding-right: 16px;">
                                        <img class="photo" src="{{ $photoSrc }}" alt="{{ $user->name }}" width="180" height="180">
                                    </td>
                                    <td valign="top">
                                        <div class="meta">Access: {{ $roleLabel }}</div>
                                        <div class="name">{{ $user->name }}</div>
                                        <div class="meta">{{ $departmentLabel }} Department</div>
                                        <div class="field">Role: <span>{{ $displayRole }}</span></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding-top: 12px;">
                            <table role="presentation" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td width="50%" valign="top" style="padding-right: 10px;">
                                        <div class="field">Staff ID: <span>{{ $user->staff_id_number }}</span></div>
                                        <div class="field">Employment Date: <span>{{ $user->start_date?->format('M d, Y') ?? 'Not set' }}</span></div>
                                    </td>
                                    <td width="50%" valign="top" style="padding-left: 10px;">
                                        <div class="field">ID Expires: <span>{{ $user->id_expires_at?->format('M d, Y') ?? 'Not set' }}</span></div>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                </table>
                <a class="cta" href="{{ $downloadUrl }}">Download or Print</a>
                <p class="footer">Keep this card for reference and renew your ID yearly.</p>
            </div>
        </div>
    </body>
</html>
