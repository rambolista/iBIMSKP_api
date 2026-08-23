<div class="report-letterhead">
    @if (count($logosByPosition))
        <div class="report-letterhead-logos">
            <div class="report-letterhead-logo-slot report-letterhead-logo-left">
                @foreach (($logosByPosition['top-left'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
            <div class="report-letterhead-logo-slot report-letterhead-logo-center">
                @foreach (($logosByPosition['top-center'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
            <div class="report-letterhead-logo-slot report-letterhead-logo-right">
                @foreach (($logosByPosition['top-right'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
        </div>
    @endif

    <div class="report-letterhead-body">
        {!! $body !!}
    </div>

    @if (count(array_intersect_key($logosByPosition, array_flip(['bottom-left', 'bottom-center', 'bottom-right']))))
        <div class="report-letterhead-logos report-letterhead-logos-bottom">
            <div class="report-letterhead-logo-slot report-letterhead-logo-left">
                @foreach (($logosByPosition['bottom-left'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
            <div class="report-letterhead-logo-slot report-letterhead-logo-center">
                @foreach (($logosByPosition['bottom-center'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
            <div class="report-letterhead-logo-slot report-letterhead-logo-right">
                @foreach (($logosByPosition['bottom-right'] ?? []) as $logo)
                    <img src="{{ $logo }}" alt="Logo">
                @endforeach
            </div>
        </div>
    @endif
</div>
