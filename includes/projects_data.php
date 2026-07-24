<?php
declare(strict_types=1);

if (!function_exists('artdon_projects_asset')) {
    function artdon_projects_asset(array $candidates): string
    {
        $root = dirname(__DIR__);
        foreach ($candidates as $candidate) {
            $path = ltrim((string)$candidate, '/');
            if ($path !== '' && is_file($root . '/' . $path)) {
                return $path;
            }
        }
        return 'assets/img/projects/featured-retail.webp';
    }
}

if (!function_exists('artdon_projects_categories')) {
    function artdon_projects_categories(): array
    {
        return [
            'All Projects',
            'Retail',
            'Hospitality',
            'Office',
            'Residential',
            'Museum & Gallery',
            'Commercial',
        ];
    }
}

if (!function_exists('artdon_projects_regions')) {
    function artdon_projects_regions(): array
    {
        return ['All Regions', 'Asia', 'Europe', 'Middle East', 'North America', 'Oceania'];
    }
}

if (!function_exists('artdon_projects_list')) {
    function artdon_projects_list(): array
    {
        $fallbackRetail = ['assets/img/projects/featured-retail.webp', 'assets/img/projects/featured-office.webp'];
        $fallbackHospitality = ['assets/img/projects/featured-hospitality.webp', 'assets/img/projects/featured-retail.webp'];
        $fallbackOffice = ['assets/img/projects/featured-office.webp', 'assets/img/projects/featured-retail.webp'];
        $fallbackMuseum = ['assets/img/projects/featured-museum.webp', 'assets/img/projects/featured-retail.webp'];

        return [
            [
                'title' => 'ZARA Flagship Store',
                'slug' => 'zara-flagship-store',
                'category' => 'Retail',
                'region' => 'Europe',
                'location' => 'Milan, Italy',
                'products' => 'Spectrum · Slim · Intero',
                'description' => 'High-CRI retail lighting with track spotlights and wall washers for premium merchandise presentation and customer comfort.',
                'image' => artdon_projects_asset($fallbackRetail),
                'detail_url' => 'project-detail.php?slug=zara-flagship-store',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'title' => 'Park Hyatt Hotel',
                'slug' => 'park-hyatt-hotel',
                'category' => 'Hospitality',
                'region' => 'Asia',
                'location' => 'Bangkok, Thailand',
                'products' => 'Emma · Silo · Flexi',
                'description' => 'Warm and comfortable hospitality lighting that enhances guest experience and architectural beauty.',
                'image' => artdon_projects_asset($fallbackHospitality),
                'detail_url' => 'project-detail.php?slug=park-hyatt-hotel',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'title' => 'Google Office',
                'slug' => 'google-office',
                'category' => 'Office',
                'region' => 'Europe',
                'location' => 'London, UK',
                'products' => 'Adj · Voli',
                'description' => 'Efficient and uniform lighting improves visual comfort for collaborative workspaces and daily operations.',
                'image' => artdon_projects_asset($fallbackOffice),
                'detail_url' => 'project-detail.php?slug=google-office',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'title' => 'Louvre Museum',
                'slug' => 'louvre-museum',
                'category' => 'Museum & Gallery',
                'region' => 'Europe',
                'location' => 'Paris, France',
                'products' => 'Optimax · Hicore',
                'description' => "Precise accent lighting highlights artwork details while preserving the museum's serene atmosphere.",
                'image' => artdon_projects_asset($fallbackMuseum),
                'detail_url' => 'project-detail.php?slug=louvre-museum',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'title' => 'Apple Store',
                'slug' => 'apple-store',
                'category' => 'Retail',
                'region' => 'Middle East',
                'location' => 'Dubai, UAE',
                'products' => 'Spectrum · Magentra',
                'description' => 'Clean and consistent lighting design creates a welcoming environment for customers.',
                'image' => artdon_projects_asset($fallbackRetail),
                'detail_url' => 'project-detail.php?slug=apple-store',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'title' => 'Vitra Campus',
                'slug' => 'vitra-campus',
                'category' => 'Office',
                'region' => 'Europe',
                'location' => 'Weil am Rhein, Germany',
                'products' => 'Slim · Adj',
                'description' => 'Well-balanced lighting for productive workspaces with excellent visual comfort.',
                'image' => artdon_projects_asset($fallbackOffice),
                'detail_url' => 'project-detail.php?slug=vitra-campus',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'title' => 'Mandarin Oriental Hotel',
                'slug' => 'mandarin-oriental-hotel',
                'category' => 'Hospitality',
                'region' => 'Asia',
                'location' => 'Tokyo, Japan',
                'products' => 'Emma · Flexi · Hicore',
                'description' => 'Elegant lighting design creates a relaxing atmosphere for luxury hospitality.',
                'image' => artdon_projects_asset($fallbackHospitality),
                'detail_url' => 'project-detail.php?slug=mandarin-oriental-hotel',
                'sort_order' => 70,
                'is_active' => true,
            ],
            [
                'title' => 'Uniqlo Store',
                'slug' => 'uniqlo-store',
                'category' => 'Retail',
                'region' => 'Asia',
                'location' => 'Seoul, Korea',
                'products' => 'Spectrum · Slim',
                'description' => 'High performance track lighting ensures clear product visibility and energy efficiency.',
                'image' => artdon_projects_asset($fallbackRetail),
                'detail_url' => 'project-detail.php?slug=uniqlo-store',
                'sort_order' => 80,
                'is_active' => true,
            ],
            [
                'title' => 'Residential Villa',
                'slug' => 'residential-villa',
                'category' => 'Residential',
                'region' => 'Asia',
                'location' => 'Singapore',
                'products' => 'BeamX · Voli',
                'description' => 'Architectural lighting enhances the beauty of the space and elevates daily living.',
                'image' => artdon_projects_asset(['assets/img/projects/featured-office.webp', 'assets/img/projects/featured-retail.webp']),
                'detail_url' => 'project-detail.php?slug=residential-villa',
                'sort_order' => 90,
                'is_active' => true,
            ],
        ];
    }
}
