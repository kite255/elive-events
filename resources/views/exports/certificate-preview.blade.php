<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    html, body { margin: 0; padding: 0; }
    body { font-family: DejaVu Sans, sans-serif; }
    .canvas { position: relative; overflow: hidden; }
    .element { position: absolute; overflow: hidden; white-space: pre-wrap; word-wrap: break-word; }
    .qr { border: 1pt dashed #64748b; text-align: center; color: #334155; font-size: 9pt; }
    .sample { position: absolute; top: 0; left: 0; right: 0; text-align: center; background: #fff; color: #a61b1b; font-size: 10pt; font-weight: bold; padding: 3pt; border-bottom: 1pt solid #a61b1b; }
</style>
</head>
<body>
<div class="canvas" style="width: {{ $template->width * 0.75 }}pt; height: {{ $template->height * 0.75 }}pt; background-color: {{ $definition['background_color'] }};">
    @foreach ($definition['elements'] as $element)
        @php
            $family = match ($element['font_family']) {
                'serif' => 'DejaVu Serif',
                'mono' => 'DejaVu Sans Mono',
                default => 'DejaVu Sans',
            };
            $image = $images[$element['asset_id'] ?? 0] ?? null;
            $scale = $image ? min($element['width'] / $image['width'], $element['height'] / $image['height']) : 0;
        @endphp
        <div class="element" style="left: {{ $element['x'] * 0.75 }}pt; top: {{ $element['y'] * 0.75 }}pt; width: {{ $element['width'] * 0.75 }}pt; height: {{ $element['height'] * 0.75 }}pt; font-family: {{ $family }}; font-size: {{ $element['font_size'] * 0.75 }}pt; font-weight: {{ $element['font_weight'] >= 600 ? 'bold' : 'normal' }}; font-style: {{ $element['font_style'] }}; color: {{ $element['color'] }}; text-align: {{ $element['text_align'] }}; line-height: {{ $element['line_height'] }};">@if ($element['type'] === 'image' && $image)<img src="{{ $image['src'] }}" style="position: absolute; left: {{ ($element['width'] - $image['width'] * $scale) * 0.375 }}pt; top: {{ ($element['height'] - $image['height'] * $scale) * 0.375 }}pt; width: {{ $image['width'] * $scale * 0.75 }}pt; height: {{ $image['height'] * $scale * 0.75 }}pt;" alt="">@elseif ($element['type'] === 'qr')<div class="qr">QR preview<br>Not scannable</div>@elseif ($element['type'] === 'text'){{ $element['text'] ?? '' }}@else{{ $values[$element['type']] ?? '' }}@endif</div>
    @endforeach
    <div class="sample">SAMPLE — NOT ISSUED</div>
</div>
</body>
</html>
