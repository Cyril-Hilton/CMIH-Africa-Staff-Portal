<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Leave Request {{ $statusLabel }}</title>
        <style>
            body { margin: 0; padding: 0; background: #0a0a0a; color: #ffffff; font-family: 'Sora', Arial, sans-serif; }
            .container { max-width: 640px; margin: 0 auto; padding: 32px 24px; }
            .card { background: #111111; border: 1px solid #333333; border-radius: 16px; padding: 24px; }
            .label { font-size: 12px; letter-spacing: 0.3em; text-transform: uppercase; color: #f0f0f0; }
            .title { font-size: 28px; margin: 12px 0 8px; color: #ffffff; }
            .text { font-size: 14px; line-height: 1.6; color: #e0e0e0; margin-bottom: 12px; }
            .key { font-weight: 700; color: #ffffff; }
            .badge { display: inline-block; border-radius: 999px; border: 1px solid #14532d; background: #052e16; color: #bbf7d0; padding: 6px 10px; font-size: 11px; letter-spacing: 0.14em; text-transform: uppercase; font-weight: 700; }
            .button { display: inline-block; margin-top: 16px; background: #dc2626; color: #ffffff !important; text-decoration: none; border-radius: 12px; padding: 12px 18px; font-size: 12px; letter-spacing: 0.18em; text-transform: uppercase; font-weight: 800; }
            .footer { margin-top: 24px; font-size: 12px; color: #b0b0b0; text-align: center; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="card">
                <div class="label">CMIH Africa</div>
                <h1 class="title">Leave Request {{ $statusLabel }}</h1>
                <p class="text">Hello {{ $staff->name }},</p>
                <p class="text">Your {{ ucfirst($leave->leave_type) }} leave request has been marked as <span class="badge">{{ $statusLabel }}</span>.</p>
                <p class="text"><span class="key">Start Date:</span> {{ $leave->start_date->format('M d, Y') }}</p>
                <p class="text"><span class="key">End Date:</span> {{ $leave->end_date->format('M d, Y') }}</p>
                <p class="text"><span class="key">Line Manager:</span> {{ $lineManager?->name ?? 'Not selected' }}</p>
                <p class="text"><span class="key">Duty Cover:</span> {{ $coveringStaff?->name ?? 'Not selected' }}</p>
                @if($note)
                    <p class="text"><span class="key">Note:</span> {{ $note }}</p>
                @endif
                <a href="{{ route('portal.leaves') }}" class="button">View Leave Request</a>
                <p class="footer">CMIH Africa — We Make It Happen</p>
            </div>
        </div>
    </body>
</html>
