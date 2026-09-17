<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <style>
        @page { margin: 0; }
        html, body { margin: 0; padding: 0; }
        .page { margin: 0; padding: 0; page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        img { display: block; width: 100%; height: 100%; }
    </style>
</head>
<body>
@foreach ($pages as $page)
    <div class="page">
        <img src="{{ $page['dataUri'] }}" alt="">
    </div>
@endforeach
</body>
</html>
