@props(['status'])

<span class="kn-status">
    <span class="kn-dot kn-dot-{{ strtolower($status->value) }}"></span>{{ $status->label() }}
</span>
