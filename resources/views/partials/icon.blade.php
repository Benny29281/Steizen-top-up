<svg class="icon {{ $class ?? '' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
    @case('search')<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>@break
    @case('arrow')<path d="M5 12h14m-5-5 5 5-5 5"/>@break
    @case('receipt')<path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h4"/>@break
    @case('gamepad')<path d="M8 7h8c3 0 5 11 2 11-2 0-3-3-4-3h-4c-1 0-2 3-4 3C3 18 5 7 8 7Z"/><path d="M8 10v4m-2-2h4m5-1h.1m2 2h.1M12 7V4"/>@break
    @case('shield')<path d="m12 3 8 3v6c0 5-8 9-8 9s-8-4-8-9V6l8-3Z"/><path d="m8 12 3 3 5-6"/>@break
    @case('spark')<path d="m12 3 2.5 6.5L21 12l-6.5 2.5L12 21l-2.5-6.5L3 12l6.5-2.5L12 3Z"/>@break
    @case('grid')<rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/>@break
    @case('chevron')<path d="m9 5 7 7-7 7"/>@break
    @case('user')<circle cx="12" cy="8" r="4"/><path d="M5 21v-2a7 7 0 0 1 14 0v2"/>@break
    @case('check')<path d="m5 12 4 4L19 6"/>@break
@endswitch
</svg>
