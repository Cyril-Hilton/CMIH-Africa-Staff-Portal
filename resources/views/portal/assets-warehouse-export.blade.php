<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'Warehouse Asset Export' }}</title>
    <style>
        body { font-family: Arial, sans-serif; color: #111; margin: 24px; }
        .letterhead { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid #d71920; padding-bottom: 16px; margin-bottom: 22px; }
        .brand { font-size: 24px; font-weight: 800; letter-spacing: 0.12em; }
        .subtitle { margin-top: 6px; color: #d71920; font-size: 12px; font-weight: 700; letter-spacing: 0.2em; text-transform: uppercase; }
        .meta { text-align: right; font-size: 12px; color: #555; line-height: 1.5; }
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        th { background: #111; color: #fff; text-transform: uppercase; letter-spacing: 0.08em; }
        th, td { border: 1px solid #ddd; padding: 8px; vertical-align: top; }
        tr:nth-child(even) td { background: #f7f7f7; }
    </style>
</head>
<body>
    <div class="letterhead">
        <div>
            <div class="brand">CMIH AFRICA</div>
            <div class="subtitle">{{ $title ?? 'Warehouse Asset Export' }}</div>
        </div>
        <div class="meta">
            Printed {{ $printedAt->format('M d, Y H:i') }}<br>
            No. 7 Afum Street, North Legon. Accra - Ghana<br>
            info@cmihgh.com | +233 542204282
        </div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach(array_keys($rows[0] ?? ['Notice' => 'No warehouse records found']) as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    @foreach($row as $value)
                        <td>{{ $value }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td>No warehouse records found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
