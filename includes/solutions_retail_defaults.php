<?php

declare(strict_types=1);

function sdr_solution_definitions(): array
{
    return [
        'retail'=>['menu'=>'Retail Lighting','page'=>'Retail Lighting Solutions','sort'=>10,'image'=>'assets/img/projects/featured-retail.webp','block'=>'solutions_retail_page'],
        'hospitality'=>['menu'=>'Hospitality Lighting','page'=>'Hospitality Lighting Solutions','sort'=>20,'image'=>'assets/img/projects/featured-hospitality.webp','block'=>'solutions_detail_hospitality'],
        'museum-gallery'=>['menu'=>'Museum & Gallery','page'=>'Museum & Gallery Lighting Solutions','sort'=>30,'image'=>'assets/img/projects/featured-museum.webp','block'=>'solutions_detail_museum_gallery'],
        'office'=>['menu'=>'Office Lighting','page'=>'Office Lighting Solutions','sort'=>40,'image'=>'assets/img/projects/featured-office.webp','block'=>'solutions_detail_office'],
        'residential'=>['menu'=>'Residential Lighting','page'=>'Residential Lighting Solutions','sort'=>50,'image'=>'assets/img/project-hotel.svg','block'=>'solutions_detail_residential'],
        'outdoor-landscape'=>['menu'=>'Outdoor & Landscape','page'=>'Outdoor & Landscape Lighting Solutions','sort'=>60,'image'=>'assets/img/hero/hero-outdoor-projector.webp','block'=>'solutions_detail_outdoor_landscape'],
    ];
}

function sdr_solution_slug(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
    return trim($value, '-');
}

function sdr_solution_block_key(string $slug): string
{
    $slug = sdr_solution_slug($slug);
    $defs = sdr_solution_definitions();
    return (string)($defs[$slug]['block'] ?? ('solutions_detail_' . str_replace('-', '_', $slug)));
}

function sdr_solution_url(string $slug): string
{
    return '/solutions-' . sdr_solution_slug($slug) . '.php';
}

function sdr_solution_page_defaults(string $slug): array
{
    $defs = sdr_solution_definitions();
    $slug = isset($defs[$slug]) ? $slug : 'retail';
    $def = $defs[$slug];
    $menu = (string)$def['menu'];
    $page = (string)$def['page'];
    $image = (string)$def['image'];
    $plain = str_replace(' Lighting Solutions', '', $page);
    $heroTitle = str_contains($menu, 'Lighting') ? str_replace(' Lighting', "\nLighting", $menu) : ($menu . "\nLighting");
    $projectUrl = 'project.php?type=' . rawurlencode($slug);
    $base = [
        'listing'=>[
            'menu_title'=>$menu,
            'card_title'=>$menu,
            'card_text'=>'Professional lighting solutions designed for ' . strtolower($plain) . ' applications.',
            'card_image'=>$image,
            'card_alt'=>$menu . ' solution',
            'link_label'=>'VIEW SOLUTION',
            'sort_order'=>(int)$def['sort'],
            'show_in_menu'=>1,
            'show_in_explore'=>1,
            'is_active'=>1,
        ],
        'meta'=>[
            'title'=>$page . ' | Artdon Lighting',
            'description'=>'Professional ' . strtolower($menu) . ' solutions by Artdon Lighting.',
        ],
        'hero'=>[
            'breadcrumb'=>'Home / Solutions / ' . $menu,
            'title'=>$heroTitle,
            'intro'=>'Create a professional lighting environment with products, optics and support tailored to your project.',
            'image'=>$image,
            'alt'=>$menu . ' hero',
            'primary_label'=>'Discuss Your Project →',
            'secondary_label'=>'View Projects →',
            'secondary_url'=>$projectUrl,
        ],
        'tabs'=>[
            ['active'=>1,'label'=>'Overview','target'=>'overview'],
            ['active'=>1,'label'=>'Challenges','target'=>'challenges'],
            ['active'=>1,'label'=>'Design Guide','target'=>'design-guide'],
            ['active'=>1,'label'=>'Recommended Products','target'=>'recommended-products'],
            ['active'=>1,'label'=>'Projects','target'=>'retail-projects'],
            ['active'=>1,'label'=>'Support','target'=>'support'],
        ],
        'challenges'=>[
            'title'=>'Lighting Challenges',
            'items'=>[
                ['active'=>1,'icon'=>'merchandise','title'=>'Visual Quality','text'=>'Deliver clear visibility, accurate color and a comfortable experience.'],
                ['active'=>1,'icon'=>'comfort','title'=>'Visual Comfort','text'=>'Control glare and brightness for people using the space.'],
                ['active'=>1,'icon'=>'beam','title'=>'Precise Accent','text'=>'Use suitable beams to highlight key areas and objects.'],
                ['active'=>1,'icon'=>'energy','title'=>'Efficiency','text'=>'Balance lighting performance with energy saving.'],
                ['active'=>1,'icon'=>'layout','title'=>'Flexibility','text'=>'Adapt the lighting system to different layouts and project needs.'],
            ],
        ],
        'guide'=>[
            'title'=>'Lighting Design Guide',
            'image'=>$image,
            'alt'=>$menu . ' design guide',
            'params'=>[
                ['active'=>1,'icon'=>'ratio','title'=>'Accent Ratio','text'=>'1:3 to 1:5'],
                ['active'=>1,'icon'=>'cri','title'=>'CRI','text'=>'90+ for best color accuracy'],
                ['active'=>1,'icon'=>'cct','title'=>'CCT','text'=>'2700K - 4000K Recommended'],
                ['active'=>1,'icon'=>'beam','title'=>'Beam Angle','text'=>'10° / 15° / 24° / 36° For different zones'],
                ['active'=>1,'icon'=>'ugr','title'=>'UGR','text'=>'< 19 for visual comfort'],
            ],
            'notes'=>[
                ['active'=>1,'title'=>'General Lighting','text'=>'Uniform ambient lighting'],
                ['active'=>1,'title'=>'Accent Lighting','text'=>'Highlight key products and displays'],
                ['active'=>1,'title'=>'Ambient Lighting','text'=>'Create comfortable atmosphere'],
                ['active'=>0,'title'=>'','text'=>''],
                ['active'=>0,'title'=>'','text'=>''],
            ],
        ],
        'products'=>[
            'title'=>'Recommended Product Families',
            'button_label'=>'View All Products →',
            'button_url'=>'products.php',
            'items'=>[
                ['active'=>1,'series'=>'SPECTRUM','subtitle'=>'Track Spotlights'],
                ['active'=>1,'series'=>'LINEAR','subtitle'=>'Linear Lighting'],
                ['active'=>1,'series'=>'MAGFIT','subtitle'=>'Magnetic System'],
                ['active'=>1,'series'=>'ORIENT','subtitle'=>'Adjustable Spotlights'],
            ],
        ],
        'applications'=>[
            'title'=>'Applications / Projects',
            'button1_label'=>'View All Projects →',
            'button1_url'=>$projectUrl,
            'button2_label'=>'Discuss Your Project →',
            'button2_url'=>'',
            'items'=>[
                ['active'=>1,'title'=>$plain . ' Project','image'=>$image,'alt'=>$plain . ' project','url'=>$projectUrl],
                ['active'=>1,'title'=>'Lighting Application','image'=>$image,'alt'=>'Lighting application','url'=>$projectUrl],
                ['active'=>1,'title'=>'Commercial Project','image'=>$image,'alt'=>'Commercial project','url'=>$projectUrl],
                ['active'=>1,'title'=>'Featured Project','image'=>$image,'alt'=>'Featured project','url'=>$projectUrl],
            ],
        ],
        'support'=>[
            'title'=>'Professional Support',
            'items'=>[
                ['active'=>1,'icon'=>'layout','title'=>'Lighting Design','text'=>'Professional lighting designs and 3D simulations.'],
                ['active'=>1,'icon'=>'optics','title'=>'Optical Expertise','text'=>'Precise optics for accent, wall wash and display.'],
                ['active'=>1,'icon'=>'oem','title'=>'OEM / ODM','text'=>'Customized solutions tailored to your brand.'],
                ['active'=>1,'icon'=>'files','title'=>'IES & Dialux','text'=>'Photometric data and lighting calculations.'],
                ['active'=>1,'icon'=>'consult','title'=>'Project Consultation','text'=>'From concept to completion, we are with you.'],
            ],
        ],
        'cta'=>[
            'title'=>"Let's Create the Right Lighting for Your Project",
            'intro'=>'Talk to our lighting experts and get a solution tailored to your needs.',
            'image'=>$image,
            'alt'=>$menu . ' project support',
            'primary_label'=>'Discuss Your Project →',
            'secondary_label'=>'Download Catalogue ↓',
            'secondary_url'=>'downloads.php',
        ],
    ];
    if ($slug !== 'retail') return $base;
    return array_replace_recursive($base, sdr_retail_page_defaults_raw());
}

function sdr_retail_page_defaults_raw(): array
{
    return [
        'listing'=>[
            'menu_title'=>'Retail Lighting',
            'card_title'=>'Retail Lighting',
            'card_text'=>'Highlight products, enhance ambience and drive sales.',
            'card_image'=>'assets/img/projects/featured-retail.webp',
            'card_alt'=>'Retail lighting solution',
            'link_label'=>'VIEW SOLUTION',
            'sort_order'=>10,
            'show_in_menu'=>1,
            'show_in_explore'=>1,
            'is_active'=>1,
        ],
        'meta'=>[
            'title'=>'Retail Lighting Solutions | Artdon Lighting',
            'description'=>'Professional retail lighting solutions for fashion stores, luxury boutiques, jewelry stores, supermarkets and commercial showrooms.',
        ],
        'hero'=>[
            'breadcrumb'=>'Home / Solutions / Retail Lighting',
            'title'=>"Retail Lighting\nSolutions",
            'intro'=>'Create engaging retail environments that highlight merchandise, enhance brand experience and drive customer satisfaction.',
            'image'=>'assets/img/projects/featured-retail.webp',
            'alt'=>'Retail lighting solution for commercial store',
            'primary_label'=>'Discuss Your Project →',
            'secondary_label'=>'View Retail Projects →',
            'secondary_url'=>'project.php?type=retail',
        ],
        'challenges'=>[
            'title'=>'Retail Lighting Challenges',
            'items'=>[
                ['active'=>1,'icon'=>'merchandise','title'=>'Highlight Merchandise','text'=>'Accentuate products with precise beams, contrast and color quality.'],
                ['active'=>1,'icon'=>'experience','title'=>'Enhance Customer Experience','text'=>'Create comfortable scenes that guide attention and support buying decisions.'],
                ['active'=>1,'icon'=>'comfort','title'=>'Ensure Visual Comfort','text'=>'Control glare for shoppers, staff and reflective displays.'],
                ['active'=>1,'icon'=>'energy','title'=>'Improve Energy Efficiency','text'=>'Use efficient optics and controls to reduce operating cost.'],
                ['active'=>1,'icon'=>'layout','title'=>'Adapt to Different Layouts','text'=>'Support changing shop layouts with flexible track and modular systems.'],
            ],
        ],
        'guide'=>[
            'title'=>'Lighting Design Guide',
            'image'=>'assets/img/hero-retail.svg',
            'alt'=>'Retail lighting design guide',
        ],
        'applications'=>[
            'title'=>'Explore Retail Applications',
            'button1_label'=>'View All Retail Applications →',
            'button1_url'=>'solutions.php#solSolutionsStrip',
            'button2_label'=>'View Retail Projects →',
            'button2_url'=>'project.php?type=retail',
            'items'=>[
                ['active'=>1,'title'=>'Fashion Store','image'=>'assets/img/projects/featured-retail.webp','alt'=>'Fashion store retail lighting','url'=>'solutions-retail-fashion-store.php'],
                ['active'=>1,'title'=>'Luxury Boutique','image'=>'assets/img/project-retail.svg','alt'=>'Luxury boutique retail lighting','url'=>'solutions-retail-luxury-boutique.php'],
                ['active'=>1,'title'=>'Jewelry Store','image'=>'assets/img/projects/featured-museum.webp','alt'=>'Jewelry store retail lighting','url'=>'solutions-retail-jewelry-store.php'],
                ['active'=>1,'title'=>'Shopping Mall','image'=>'assets/img/project-1.svg','alt'=>'Shopping mall retail lighting','url'=>'solutions-retail-shopping-mall.php'],
                ['active'=>1,'title'=>'Supermarket','image'=>'assets/img/project-2.svg','alt'=>'Supermarket retail lighting','url'=>'solutions-retail-supermarket.php'],
                ['active'=>1,'title'=>'Showroom','image'=>'assets/img/hero/hero-track-systems.webp','alt'=>'Showroom retail lighting','url'=>'solutions-retail-showroom.php'],
            ],
        ],
        'cta'=>[
            'title'=>"Let's Create the Right Lighting for Your Retail Project",
            'image'=>'assets/img/projects/featured-retail.webp',
            'alt'=>'Retail lighting project support',
        ],
    ];
}

function sdr_retail_page_defaults(): array
{
    return sdr_solution_page_defaults('retail');
}

function sdr_solution_page_merge(string $slug, array $saved): array
{
    $data = sdr_solution_page_defaults($slug);
    foreach ($data as $section => $sectionDefault) {
        if (!isset($saved[$section]) || !is_array($saved[$section])) continue;
        $data[$section] = array_replace($sectionDefault, array_intersect_key($saved[$section], $sectionDefault));
        foreach (['items','params','notes'] as $listKey) {
            if (!isset($sectionDefault[$listKey]) || !is_array($sectionDefault[$listKey])) continue;
            if (!isset($saved[$section][$listKey]) || !is_array($saved[$section][$listKey])) continue;
            $list = [];
            foreach ($saved[$section][$listKey] as $i => $row) {
                if (!is_array($row)) continue;
                $base = is_array($sectionDefault[$listKey][$i] ?? null) ? $sectionDefault[$listKey][$i] : [];
                $list[] = array_replace($base, $row);
            }
            for ($i = count($list); $i < count($sectionDefault[$listKey]); $i++) {
                if (is_array($sectionDefault[$listKey][$i] ?? null)) {
                    $list[] = $sectionDefault[$listKey][$i];
                }
            }
            $data[$section][$listKey] = $list;
        }
    }
    if (isset($saved['tabs']) && is_array($saved['tabs'])) {
        $tabs = [];
        foreach ($saved['tabs'] as $i => $row) {
            if (!is_array($row)) continue;
            $base = is_array($data['tabs'][$i] ?? null) ? $data['tabs'][$i] : ['active'=>1,'label'=>'','target'=>'overview'];
            $tabs[] = array_replace($base, $row);
        }
        if ($tabs) $data['tabs'] = $tabs;
    }
    return $data;
}

function sdr_retail_page_merge(array $saved): array
{
    return sdr_solution_page_merge('retail', $saved);
}

function sdr_solution_get_page(string $slug): array
{
    $block = function_exists('web_get_block') ? web_get_block(sdr_solution_block_key($slug)) : [];
    return sdr_solution_page_merge($slug, is_array($block) ? $block : []);
}

function sdr_solution_all_pages(): array
{
    $pages = [];
    foreach (sdr_solution_definitions() as $slug => $def) {
        $data = sdr_solution_get_page($slug);
        $listing = is_array($data['listing'] ?? null) ? $data['listing'] : [];
        $pages[] = [
            'slug'=>$slug,
            'url'=>sdr_solution_url($slug),
            'sort_order'=>(int)($listing['sort_order'] ?? $def['sort']),
            'show_in_menu'=>(int)($listing['show_in_menu'] ?? 1),
            'show_in_explore'=>(int)($listing['show_in_explore'] ?? 1),
            'is_active'=>(int)($listing['is_active'] ?? 1),
            'menu_title'=>(string)($listing['menu_title'] ?? $def['menu']),
            'card_title'=>(string)($listing['card_title'] ?? $def['menu']),
            'card_text'=>(string)($listing['card_text'] ?? ''),
            'card_image'=>(string)($listing['card_image'] ?? $def['image']),
            'card_alt'=>(string)($listing['card_alt'] ?? $def['menu']),
            'link_label'=>(string)($listing['link_label'] ?? 'VIEW SOLUTION'),
            'data'=>$data,
        ];
    }
    usort($pages, static fn(array $a, array $b): int => (($a['sort_order'] <=> $b['sort_order']) ?: strcmp($a['slug'], $b['slug'])));
    return $pages;
}

function sdr_solution_menu_items(): array
{
    return array_values(array_filter(sdr_solution_all_pages(), static fn(array $page): bool => !empty($page['is_active']) && !empty($page['show_in_menu'])));
}

function sdr_solution_explore_items(): array
{
    return array_values(array_filter(sdr_solution_all_pages(), static fn(array $page): bool => !empty($page['is_active']) && !empty($page['show_in_explore'])));
}
