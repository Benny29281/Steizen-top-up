<?php

return [
    'artwork' => [
        'mobile-legends' => 'foto/1.jpg',
        'free-fire' => 'artwork/free-fire.svg',
        'pubg-mobile' => 'artwork/pubg-mobile.svg',
        'genshin-impact' => 'artwork/genshin-impact.svg',
        'honor-of-kings' => 'artwork/honor-of-kings.svg',
        'valorant' => 'artwork/valorant.svg',
        'honkai-star-rail' => 'artwork/honkai-star-rail.svg',
        'call-of-duty' => 'artwork/call-of-duty.svg',
    ],
    // demo: simulasi lokal; sandbox: pengujian Tripay. Pembayaran produksi belum aktif.
    'mode' => env('STORE_MODE', 'demo'),
    'catalog_source' => env('CATALOG_SOURCE', 'demo'),
    'catalog_max_age_hours' => 24,
    'digiflazz' => [
        'username' => env('DIGIFLAZZ_USERNAME'),
        'key' => env('DIGIFLAZZ_API_KEY'),
        'margin_percent' => env('SELLING_MARGIN_PERCENT', 10),
    ],
];
