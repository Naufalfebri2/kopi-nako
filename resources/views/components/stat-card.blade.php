@props(['icon', 'value', 'label', 'tone' => 'default'])

<div class="kn-stat kn-stat-{{ $tone }}">
    <div class="kn-stat-icon"><i class="{{ $icon }}"></i></div>
    <div class="kn-stat-value">{{ $value }}</div>
    <div class="kn-stat-label">{{ $label }}</div>
</div>
