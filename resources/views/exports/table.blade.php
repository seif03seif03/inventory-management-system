{{--
    Shared PDF table template for every report export.

    Deliberately NOT extending layouts/app.blade.php: that layout carries a
    sidebar, a topbar and CDN-hosted fonts, none of which belong in — or can be
    fetched by — a generated PDF. Styles are inline because dompdf resolves a
    limited subset of CSS and no external stylesheet is guaranteed to be
    reachable when the PDF is rendered.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #202124;
            margin: 0;
        }
        .head { border-bottom: 1.5px solid #202124; padding-bottom: 6px; margin-bottom: 10px; }
        .head h1 { font-size: 15px; margin: 0 0 3px; }
        .meta { font-size: 8px; color: #5F6368; }
        .filters { margin-top: 4px; font-size: 8px; color: #5F6368; }
        .filters strong { color: #202124; }
        table { width: 100%; border-collapse: collapse; }
        thead th {
            background: #F1F3F4;
            border-bottom: 1px solid #BDC1C6;
            padding: 5px 6px;
            text-align: left;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tbody td { padding: 4px 6px; border-bottom: 1px solid #E8EAED; }
        tbody tr:nth-child(even) td { background: #FAFAFA; }
        .empty { padding: 20px; text-align: center; color: #5F6368; }
        .foot { margin-top: 10px; font-size: 8px; color: #5F6368; text-align: right; }
    </style>
</head>
<body>

    <div class="head">
        <h1>{{ $title }}</h1>
        <div class="meta">Inventory Management &middot; generated {{ $generatedAt }}</div>

        @if (!empty($filters))
            <div class="filters"><strong>Filters:</strong> {{ implode(' | ', $filters) }}</div>
        @endif
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($headings as $heading)
                    <th>{{ $heading }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td class="empty" colspan="{{ count($headings) }}">No rows matched the selected filters.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="foot">{{ is_countable($rows) ? count($rows) : 0 }} row(s)</div>

</body>
</html>
