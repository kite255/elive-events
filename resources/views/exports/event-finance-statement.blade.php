<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>eLive Events — Financial statement</title>
<style>
@page { margin: 28px; }
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #161943; }
h1 { font-size: 18px; margin-bottom: 6px; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td { padding: 6px; border-bottom: 1px solid #e6e8ef; vertical-align: top; overflow-wrap: break-word; word-wrap: break-word; }
tr { page-break-inside: avoid; }
.section td { background: #161943; color: #fff; font-weight: bold; }
.spacer td { height: 8px; border: 0; }
</style></head>
<body>
<h1>eLive Events | Financial statement</h1>
<table>
@foreach (array_slice($rows, 1) as $row)
    @if (count($row) === 0)
        <tr class="spacer"><td colspan="7"></td></tr>
    @elseif (count($row) === 1)
        <tr class="section"><td colspan="7">{{ $row[0] }}</td></tr>
    @else
        <tr>
            @foreach (array_pad($row, 7, null) as $value)
                <td>{{ $value ?? '—' }}</td>
            @endforeach
        </tr>
    @endif
@endforeach
</table>
</body></html>
