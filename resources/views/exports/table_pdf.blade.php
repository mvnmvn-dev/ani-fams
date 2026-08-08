<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #1a1a1a; margin: 24px; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        .meta { color: #666; margin-bottom: 16px; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #142E4C; color: #fff; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) td { background: #f5f7fa; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p class="meta">Generated {{ date('Y-m-d H:i:s') }} · {{ count($rows) }} row(s)</p>
    <table>
        <thead>
            <tr>
                @foreach ($headers as $header)
                    <th>{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ is_scalar($cell) || $cell === null ? $cell : json_encode($cell) }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ max(count($headers), 1) }}">No records.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
