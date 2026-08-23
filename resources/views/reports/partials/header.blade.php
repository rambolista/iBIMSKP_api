@php
    $hasTopRow = fn ($group) => ! empty($group['top-left']) || ! empty($group['top-center']) || ! empty($group['top-right']);
    $hasBottomRow = fn ($group) => ! empty($group['bottom-left']) || ! empty($group['bottom-center']) || ! empty($group['bottom-right']);
    $logoImages = fn ($urls) => collect($urls)->map(fn ($url) => '<img src="'.$url.'" alt="Logo">')->implode(' ');
@endphp
<div class="report-letterhead">
    @if ($fullTemplate)
        <div class="report-letterhead-watermark">
            <img src="{{ $fullTemplate }}" alt="Watermark">
        </div>
    @endif

    @if ($hasTopRow($background))
        <table class="report-letterhead-logo-row report-letterhead-logo-row-bg"><tr>
            <td class="report-letterhead-logo-cell-left">{!! $logoImages($background['top-left'] ?? []) !!}</td>
            <td class="report-letterhead-logo-cell-center">{!! $logoImages($background['top-center'] ?? []) !!}</td>
            <td class="report-letterhead-logo-cell-right">{!! $logoImages($background['top-right'] ?? []) !!}</td>
        </tr></table>
    @endif

    <div class="report-letterhead-content">
        @if ($hasTopRow($foreground))
            <table class="report-letterhead-logo-row"><tr>
                <td class="report-letterhead-logo-cell-left">{!! $logoImages($foreground['top-left'] ?? []) !!}</td>
                <td class="report-letterhead-logo-cell-center">{!! $logoImages($foreground['top-center'] ?? []) !!}</td>
                <td class="report-letterhead-logo-cell-right">{!! $logoImages($foreground['top-right'] ?? []) !!}</td>
            </tr></table>
        @endif

        <div class="report-letterhead-body">
            {!! $body !!}
        </div>

        @if ($hasBottomRow($foreground))
            <table class="report-letterhead-logo-row"><tr>
                <td class="report-letterhead-logo-cell-left">{!! $logoImages($foreground['bottom-left'] ?? []) !!}</td>
                <td class="report-letterhead-logo-cell-center">{!! $logoImages($foreground['bottom-center'] ?? []) !!}</td>
                <td class="report-letterhead-logo-cell-right">{!! $logoImages($foreground['bottom-right'] ?? []) !!}</td>
            </tr></table>
        @endif
    </div>

    @if ($hasBottomRow($background))
        <table class="report-letterhead-logo-row report-letterhead-logo-row-bg report-letterhead-logo-row-bg-bottom"><tr>
            <td class="report-letterhead-logo-cell-left">{!! $logoImages($background['bottom-left'] ?? []) !!}</td>
            <td class="report-letterhead-logo-cell-center">{!! $logoImages($background['bottom-center'] ?? []) !!}</td>
            <td class="report-letterhead-logo-cell-right">{!! $logoImages($background['bottom-right'] ?? []) !!}</td>
        </tr></table>
    @endif
</div>
