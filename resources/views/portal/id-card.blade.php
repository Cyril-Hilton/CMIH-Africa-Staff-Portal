<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>CMIH Staff ID Card</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Sora:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <style>
            * { box-sizing: border-box; }
            body { margin: 0; font-family: 'Sora', sans-serif; background: #050505; color: #ffffff; }
            .page { min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; gap: 16px; }
            .toolbar { width: min(480px, 100%); display: flex; gap: 10px; justify-content: space-between; }
            .toolbar a, .toolbar button { border: 1px solid rgba(255,255,255,0.2); background: rgba(255,255,255,0.06); color: #ffffff; padding: 8px 16px; border-radius: 999px; font-size: 10px; letter-spacing: 0.2em; text-transform: uppercase; text-decoration: none; cursor: pointer; }
            .card { width: min(480px, 100%); min-height: 302px; height: auto; border-radius: 16px; padding: 20px; border: 1px solid rgba(226, 28, 30, 0.35); background: linear-gradient(140deg, rgba(226, 28, 30, 0.2), rgba(0, 0, 0, 0.9)); box-shadow: 0 12px 32px rgba(0, 0, 0, 0.45); display: flex; flex-direction: column; }
            .card-inner { flex: 1; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; }
            .header { display: flex; justify-content: space-between; align-items: center; }
            .logo { height: 24px; }
            .tag { font-size: 9px; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(255,255,255,0.7); }
            .body { display: grid; grid-template-columns: auto 1fr; gap: 16px; align-items: center; }
            .photo { width: 100px; height: 100px; border-radius: 12px; object-fit: cover; border: 1px solid rgba(255,255,255,0.15); }
            .name { font-size: 18px; margin: 4px 0; font-weight: 700; line-height: 1.2; }
            .meta { font-size: 10px; letter-spacing: 0.15em; text-transform: uppercase; color: rgba(255,255,255,0.6); }
            .field { font-size: 10px; color: rgba(255,255,255,0.6); margin-top: 4px; line-height: 1.4; }
            .field span { color: #ffffff; font-weight: 600; display: block; margin-top: 1px; font-size: 11px; }
            .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: auto; }
            .divider { height: 1px; background: rgba(255,255,255,0.1); margin: 4px 0; }

            /* Mobile tweaks for very small screens */
            @media (max-width: 480px) {
                .card { padding: 12px; min-height: auto; }
                .photo { width: 70px; height: 70px; border-radius: 8px; flex-shrink: 0; }
                .name { font-size: 14px; line-height: 1.2; }
                .grid { gap: 6px; }
                .body { gap: 10px; }
                .field span { font-size: 10px; }
            }

            @media print {
                body { background: #ffffff; color: #000000; }
                .page { display: block; padding: 0; margin: 0; }
                .toolbar { display: none; }
                .card { 
                    width: 85.6mm; 
                    height: 53.98mm; 
                    aspect-ratio: 1.586 / 1; 
                    box-shadow: none; 
                    border: 1px solid #000; 
                    padding: 4mm;
                    background: #fff;
                    color: #000;
                    overflow: hidden;
                    display: block;
                }
                .card-inner { height: 100%; display: block; }
                .header, .body, .grid { display: flex; } /* Reset flex/grid for print if needed or keep simpler */
                .header { justify-content: space-between; margin-bottom: 2mm; }
                .body { display: flex; gap: 3mm; margin-bottom: 2mm; }
                .logo { height: 5mm; filter: invert(1); } /* Invert logo for white paper if needed */
                .photo { width: 18mm; height: 18mm; border-color: #000; }
                .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 2mm; }
                .divider { background: #000; margin: 2mm 0; }
                .name { color: #000; font-size: 10pt; }
                .meta { color: #333; font-size: 6pt; }
                .field { color: #333; font-size: 6pt; }
                .field span { color: #000; font-size: 7pt; }
            }
        </style>
    </head>
    <body>
        @php
            $roleLabel       = \App\Models\User::roleLabel($user->access_role);
            $departmentLabel = \App\Models\User::departmentLabel($user->department);
            $displayRole     = $user->job_title ?: 'Team Member';
            $jobLevel        = $user->position_title ?: '';
        @endphp

        <div class="page">
            <div class="toolbar">
                <a href="{{ route('portal.profile') }}">Back to Profile</a>
                <button type="button" onclick="window.print()">Download / Print</button>
            </div>

            <div class="card">
                <div class="card-inner">
                    <div class="header">
                        <img src="{{ asset('images/logo/logo-light.png') }}" alt="CMIH Africa" class="logo">
                        <span class="tag">Staff ID Card</span>
                    </div>

                    <div class="body">
                        <img src="{{ $user->profilePhotoUrl() }}" alt="{{ $user->name }}" class="photo">
                        <div>
                            <div class="meta">Access: {{ $roleLabel }}</div>
                            <div class="name">{{ $user->name }}</div>
                            <div class="meta">{{ $departmentLabel }} Department</div>
                            <div class="field">Role: <span>{{ $displayRole }}</span></div>
                            @if ($jobLevel)
                                <div class="field">Job Level: <span>{{ $jobLevel }}</span></div>
                            @endif
                        </div>
                    </div>

                    <div class="divider"></div>

                    <div class="grid">
                        <div class="field">Staff ID: <span>{{ $user->staff_id_number ?? 'Pending' }}</span></div>
                        <div class="field">Employment Date: <span>{{ $user->start_date?->format('M d, Y') ?? 'Not set' }}</span></div>
                        <div class="field">ID Expires: <span>{{ $user->id_expires_at?->format('M d, Y') ?? 'Not set' }}</span></div>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
