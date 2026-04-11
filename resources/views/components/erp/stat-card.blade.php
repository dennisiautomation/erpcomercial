@props(['icon', 'color' => 'primary', 'value', 'label', 'prefix' => '', 'trend' => null, 'trendLabel' => ''])
<div class="stat-card">
    <div class="d-flex align-items-center gap-3">
        <div class="stat-icon {{ $color }}">
            <i class="bi bi-{{ $icon }}"></i>
        </div>
        <div>
            <div class="stat-value">{{ $prefix }}{{ $value }}</div>
            <div class="stat-label">{{ $label }}</div>
            @if($trend !== null)
                <div class="stat-trend {{ $trend >= 0 ? 'up' : 'down' }}">
                    <i class="bi bi-{{ $trend >= 0 ? 'arrow-up' : 'arrow-down' }}"></i>
                    {{ abs($trend) }}% {{ $trendLabel }}
                </div>
            @endif
        </div>
    </div>
</div>
