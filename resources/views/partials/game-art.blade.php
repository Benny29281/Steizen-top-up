@if($artwork = config('store.artwork.'.$game->slug))
    <img class="game-art-image" src="{{ asset($artwork) }}" alt="" width="640" height="480" loading="lazy" decoding="async">
@else
    <span class="game-monogram">{{ $game->short }}</span>
@endif
