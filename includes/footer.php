<?php

declare(strict_types=1);

require_once __DIR__ . '/pretty_urls_v71868.php';

/**
 * V6.11 footer content normalisation.
 * Stores the footer as one JSON content block and keeps legacy footer data usable.
 */

function web_footer_bool(mixed $value, bool $default = true): bool
{
    if ($value === null) return $default;
    if (is_bool($value)) return $value;
    if (is_int($value) || is_float($value)) return (bool)$value;
    $value = strtolower(trim((string)$value));
    if ($value === '') return false;
    return !in_array($value, ['0', 'false', 'off', 'no'], true);
}


function web_footer_safe_hex(string $value, string $fallback = '#101010'): string
{
    $value = strtoupper(trim($value));
    if ($value !== '' && $value[0] !== '#') $value = '#'.$value;
    if (preg_match('/^#[0-9A-F]{3}$/', $value)) {
        $value = '#'.$value[1].$value[1].$value[2].$value[2].$value[3].$value[3];
    }
    return preg_match('/^#[0-9A-F]{6}$/', $value) ? $value : strtoupper($fallback);
}

function web_footer_hex_rgb(string $hex): array
{
    $hex = ltrim(web_footer_safe_hex($hex), '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

function web_footer_mix_hex(string $base, string $target, float $targetWeight): string
{
    [$br, $bg, $bb] = web_footer_hex_rgb($base);
    [$tr, $tg, $tb] = web_footer_hex_rgb($target);
    $w = max(0.0, min(1.0, $targetWeight));
    $rgb = [
        (int)round($br + ($tr - $br) * $w),
        (int)round($bg + ($tg - $bg) * $w),
        (int)round($bb + ($tb - $bb) * $w),
    ];
    return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
}

function web_footer_relative_luminance(string $hex): float
{
    $channels = [];
    foreach (web_footer_hex_rgb($hex) as $value) {
        $v = $value / 255;
        $channels[] = $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
    }
    return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
}

function web_footer_theme_tokens(string $background): array
{
    $background = web_footer_safe_hex($background, '#101010');
    // The footer uses a white logo, so the manager primarily offers dark palettes.
    // Still calculate an automatic foreground scheme for manually entered colours.
    $light = web_footer_relative_luminance($background) > 0.42;
    $target = $light ? '#000000' : '#FFFFFF';
    return [
        'background' => $background,
        'text' => web_footer_mix_hex($background, $target, $light ? 0.92 : 0.96),
        'muted' => web_footer_mix_hex($background, $target, $light ? 0.65 : 0.70),
        'soft' => web_footer_mix_hex($background, $target, $light ? 0.48 : 0.48),
        'line' => web_footer_mix_hex($background, $target, 0.16),
        'field' => web_footer_mix_hex($background, $target, 0.035),
        'field_line' => web_footer_mix_hex($background, $target, 0.23),
        'is_light' => $light,
    ];
}

function web_footer_safe_url(string $url, string $fallback = '#'): string
{
    $url = artdon_normalize_front_url_v71868(trim($url));
    if ($url === '') return $fallback;
    if (preg_match('/^(?:javascript|data|vbscript):/i', $url)) return $fallback;
    if (preg_match('/^(?:https?:\/\/|mailto:|tel:|#|\/|\.\/|\.\.\/)/i', $url)) return $url;
    // Normal website-relative paths such as products.php or downloads.php?type=x.
    if (preg_match('/^[A-Za-z0-9_\-\.\/]+(?:\?[^\s]*)?(?:#[^\s]*)?$/', $url)) return $url;
    return $fallback;
}

function web_footer_nav_map(array $site): array
{
    $map = [];
    foreach (($site['nav'] ?? []) as $menu) {
        if (!is_array($menu)) continue;
        $label = trim((string)($menu['label'] ?? ''));
        if ($label === '') continue;
        $map[strtolower($label)] = $menu;
    }
    return $map;
}

function web_footer_unique_links(array $links, int $limit = 12): array
{
    $clean = [];
    $seen = [];
    $seenHref = [];
    $seenLabel = [];
    foreach ($links as $item) {
        if (!is_array($item)) continue;
        $label = trim((string)($item['label'] ?? ($item[0] ?? '')));
        $href = trim((string)($item['href'] ?? ($item[1] ?? '')));
        if ($label === '' || $href === '') continue;
        $key = strtolower($label.'|'.$href);
        $hrefKey = strtolower(rtrim($href, '/'));
        $labelKey = strtolower(preg_replace('/\s+/', ' ', $label));
        if (isset($seen[$key]) || isset($seenHref[$hrefKey]) || isset($seenLabel[$labelKey])) continue;
        $seen[$key] = true;
        $seenHref[$hrefKey] = true;
        $seenLabel[$labelKey] = true;
        $clean[] = [
            'label' => $label,
            'href' => web_footer_safe_url($href),
            'new_tab' => web_footer_bool($item['new_tab'] ?? false, false) ? 1 : 0,
        ];
        if (count($clean) >= $limit) break;
    }
    return $clean;
}

function web_footer_default_columns(array $site): array
{
    // Keep the footer intentionally limited to these four useful groups. The
    // destinations mirror the current public sections, while the root "All"
    // links keep this list more concise and task-oriented than the header menu.
    $definitions = [
        [
            'title'=>'Products',
            'links'=>[
                ['label'=>'All Products', 'href'=>'/products.php'],
                ['label'=>'Track Lights', 'href'=>'/products.php?category=track-lights'],
                ['label'=>'Downlights', 'href'=>'/products.php?category=downlights'],
                ['label'=>'Magnetic Systems', 'href'=>'/products.php?category=magnetic-systems'],
                ['label'=>'Surface & Pendant Lights', 'href'=>'/products.php?category=surface-pendant-lights'],
                ['label'=>'Linear Lighting', 'href'=>'/products.php?category=linear-lighting'],
                ['label'=>'Outdoor Lighting', 'href'=>'/products.php?category=outdoor-lighting'],
                ['label'=>'LED Strips & Profiles', 'href'=>'/products.php?category=led-strips-profiles'],
                ['label'=>'Track Systems & Accessories', 'href'=>'/products.php?category=track-systems-accessories'],
            ],
        ],
        [
            'title'=>'Solutions',
            'links'=>[
                ['label'=>'All Solutions', 'href'=>'/solutions.php'],
                ['label'=>'Retail Lighting', 'href'=>'/solutions-retail.php'],
                ['label'=>'Hospitality Lighting', 'href'=>'/solutions-hospitality.php'],
                ['label'=>'Museum & Gallery', 'href'=>'/solutions-museum-gallery.php'],
                ['label'=>'Office Lighting', 'href'=>'/solutions-office.php'],
                ['label'=>'Residential Lighting', 'href'=>'/solutions-residential.php'],
                ['label'=>'Outdoor & Landscape', 'href'=>'/solutions-outdoor-landscape.php'],
            ],
        ],
        [
            'title'=>'Projects',
            'links'=>[
                ['label'=>'All Projects', 'href'=>'/project.php'],
                ['label'=>'Retail', 'href'=>'/project.php?type=retail'],
                ['label'=>'Hospitality', 'href'=>'/project.php?type=hospitality'],
                ['label'=>'Office', 'href'=>'/project.php?type=office'],
                ['label'=>'Residential', 'href'=>'/project.php?type=residential'],
                ['label'=>'Museum & Gallery', 'href'=>'/project.php?type=museum'],
                ['label'=>'Commercial', 'href'=>'/project.php?type=commercial'],
            ],
        ],
        [
            'title'=>'Resources',
            'links'=>[
                ['label'=>'All Resources', 'href'=>'/resources.php'],
                ['label'=>'Catalogue / Downloads', 'href'=>'/resources-downloads.php'],
                ['label'=>'Blog & Insights', 'href'=>'/resources-blog.php'],
                ['label'=>'FAQ', 'href'=>'/resources-faq.php'],
                ['label'=>'Videos', 'href'=>'/resources-videos.php'],
            ],
        ],
    ];

    return array_map(static fn(array $definition): array => [
            'active' => 1,
            'title' => $definition['title'],
            'links' => web_footer_unique_links($definition['links'], 12),
        ], $definitions);
}

function web_footer_navigation_version(): int
{
    return 718173;
}

function web_footer_default_socials(array $site): array
{
    // Keep the three main channels visible by default so the footer manager is
    // immediately usable. A # URL is rendered as a disabled preview icon until
    // the administrator replaces it with the real profile address.
    $core = [
        'instagram'=>['Instagram','instagram'],
        'linkedin'=>['LinkedIn','linkedin'],
        'youtube'=>['YouTube','youtube'],
    ];
    $items = [];
    foreach ($core as $key => [$label, $icon]) {
        $url = trim((string)($site[$key] ?? ''));
        $items[] = [
            'label'=>$label,
            'short'=>$icon,
            'href'=>$url !== '' ? web_footer_safe_url($url) : '#social-'.$key,
            'new_tab'=>1,
        ];
    }

    $optional = [
        'facebook'=>['Facebook','facebook'],
        'pinterest'=>['Pinterest','pinterest'],
        'x'=>['X','x'],
        'tiktok'=>['TikTok','tiktok'],
    ];
    foreach ($optional as $key => [$label, $icon]) {
        $url = trim((string)($site[$key] ?? ''));
        if ($url === '') continue;
        $items[] = ['label'=>$label, 'short'=>$icon, 'href'=>web_footer_safe_url($url), 'new_tab'=>1];
    }

    $whatsapp = preg_replace('/\D+/', '', (string)($site['whatsapp'] ?? ''));
    if ($whatsapp !== '') {
        $items[] = ['label'=>'WhatsApp', 'short'=>'whatsapp', 'href'=>'https://wa.me/'.$whatsapp, 'new_tab'=>1];
    }
    return $items;
}

function web_footer_social_icon_key(array $social): string
{
    $source = strtolower(trim((string)($social['short'] ?? '').' '.(string)($social['label'] ?? '')));
    $source = preg_replace('/[^a-z0-9▶]+/', ' ', $source) ?? $source;
    $rules = [
        'instagram' => ['instagram','insta',' ig '],
        'linkedin' => ['linkedin','linked in',' in '],
        'youtube' => ['youtube','you tube','yt','▶'],
        'whatsapp' => ['whatsapp','what app',' wa '],
        'facebook' => ['facebook',' fb '],
        'pinterest' => ['pinterest',' pin '],
        'tiktok' => ['tiktok','tik tok',' tt '],
        'x' => ['twitter',' x '],
    ];
    $haystack = ' '.$source.' ';
    foreach ($rules as $key => $needles) {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) return $key;
        }
    }
    return 'link';
}

function web_footer_social_icon_svg(array $social): string
{
    $key = web_footer_social_icon_key($social);
    return match ($key) {
        'instagram' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.25" y="3.25" width="17.5" height="17.5" rx="5" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4.15" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="17.35" cy="6.75" r="1.1" fill="currentColor"/></svg>',
        'linkedin' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.25" y="3.25" width="17.5" height="17.5" rx="2.2" fill="none" stroke="currentColor" stroke-width="1.7"/><circle cx="7.65" cy="8.15" r="1.15" fill="currentColor"/><path d="M6.7 10.7h1.9v6.7H6.7zm4 0h1.85v.92c.82-1.08 1.95-1.25 2.72-1.25 2.04 0 3.13 1.27 3.13 3.72v3.31h-1.93v-3.05c0-1.43-.46-2.25-1.65-2.25-1.34 0-2.18.91-2.18 2.55v2.75H10.7z" fill="currentColor"/></svg>',
        'youtube' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 7.15c-.2-.82-.84-1.46-1.66-1.68C17.07 5.05 12 5.05 12 5.05s-5.07 0-6.54.42A2.43 2.43 0 0 0 3.8 7.15 25.2 25.2 0 0 0 3.4 12c0 1.62.14 3.24.4 4.85.2.82.84 1.46 1.66 1.68 1.47.42 6.54.42 6.54.42s5.07 0 6.54-.42a2.43 2.43 0 0 0 1.66-1.68c.27-1.61.4-3.23.4-4.85 0-1.62-.13-3.24-.4-4.85Z" fill="none" stroke="currentColor" stroke-width="1.55"/><path d="m10.1 9 5 3-5 3V9Z" fill="currentColor"/></svg>',
        'whatsapp' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20.2 11.75a8.18 8.18 0 0 1-12.03 7.18L4 20l1.1-4a8.2 8.2 0 1 1 15.1-4.25Z" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8.2 7.7c.22-.5.45-.52.82-.53h.34c.14 0 .34.06.45.34l.9 2.1c.08.2.06.38-.04.55l-.7 1c-.11.15-.09.33 0 .48.44.8 1.14 1.52 1.93 2 .17.1.36.12.51-.02l1.15-1.08c.16-.15.35-.18.55-.08l2.02.96c.24.12.34.27.32.48-.05.73-.42 1.5-1.03 1.92-.55.38-1.27.55-2.16.36-1.38-.3-3.12-1.28-4.5-2.68-1.4-1.41-2.3-3.1-2.48-4.3-.13-.8.05-1.14.36-1.52.2-.25.4-.36.56-.44Z" fill="currentColor"/></svg>',
        'facebook' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.65 20v-7h2.4l.36-2.75h-2.76V8.5c0-.8.22-1.35 1.38-1.35h1.47V4.7a20 20 0 0 0-2.14-.12c-2.12 0-3.57 1.28-3.57 3.65v2.02H8.4V13h2.39v7h2.86Z" fill="currentColor"/></svg>',
        'x' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4.8 18.8 19.2M18.4 4.8 5.2 19.2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>',
        'tiktok' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.2 4.5c.3 2.3 1.55 3.75 3.8 4.15v2.05c-1.48-.04-2.68-.47-3.8-1.3v5.2c0 3.08-2.18 5.2-5.02 5.2-2.61 0-4.68-2-4.68-4.6 0-2.85 2.34-4.92 5.2-4.58v2.18c-1.42-.18-2.92.58-2.92 2.33 0 1.36 1.04 2.42 2.42 2.42 1.62 0 2.72-1.12 2.72-3.04V4.5h2.28Z" fill="currentColor"/></svg>',
        'pinterest' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3.5a8.5 8.5 0 0 0-3.1 16.42c-.08-1.4-.02-3.08.35-4.65l1.1-4.65s-.28-.58-.28-1.43c0-1.34.77-2.34 1.74-2.34.82 0 1.22.62 1.22 1.36 0 .83-.53 2.06-.8 3.2-.23.96.48 1.74 1.43 1.74 1.72 0 3.04-1.81 3.04-4.43 0-2.31-1.66-3.93-4.04-3.93-2.75 0-4.37 2.06-4.37 4.2 0 .83.32 1.72.72 2.2.08.1.09.18.07.28l-.27 1.1c-.04.18-.14.22-.33.14-1.24-.58-2.02-2.4-2.02-3.86 0-3.14 2.28-6.02 6.58-6.02 3.45 0 6.14 2.46 6.14 5.75 0 3.43-2.16 6.19-5.16 6.19-1 0-1.95-.52-2.27-1.14l-.62 2.35c-.22.86-.82 1.94-1.23 2.6A8.5 8.5 0 1 0 12 3.5Z" fill="currentColor"/></svg>',
        default => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9.5 14.5 14.5 9.5M8.1 16.9l-1 .95a3.5 3.5 0 0 1-4.95-4.95l3.15-3.15a3.5 3.5 0 0 1 4.95 0M15.9 7.1l1-.95a3.5 3.5 0 0 1 4.95 4.95l-3.15 3.15a3.5 3.5 0 0 1-4.95 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>',
    };
}

function web_footer_defaults(array $site = []): array
{
    $company = trim((string)($site['company'] ?? 'Artdon Lighting Limited'));
    $year = date('Y');
    return [
        'schema_version' => 6128,
        'navigation_version' => web_footer_navigation_version(),
        'visibility_confirmed' => 1,
        'theme' => [
            'background' => '#101010',
        ],
        'brand' => [
            'active' => 1,
            'logo' => trim((string)($site['footer_logo'] ?? 'assets/img/logo-artdon-footer.png')),
            'light_logo' => trim((string)($site['logo'] ?? 'assets/img/logo-artdon.png')),
            'logo_alt' => $company,
            'home_url' => 'index.php',
            'tagline' => trim((string)($site['tagline'] ?? 'Architectural Lighting for Commercial Spaces')),
        ],
        'columns' => web_footer_default_columns($site),
        'contact' => [
            'active' => 1,
            'heading' => 'Contact',
            'company' => $company,
            'address' => trim((string)($site['location'] ?? 'Zhongshan, Guangdong, China')),
            'contact_label' => 'Contact',
            'contact_value' => trim((string)($site['contact_name'] ?? 'Sukie')),
            'email_label' => 'Email',
            'email_value' => trim((string)($site['email'] ?? 'sales@artdon.cn')),
            'telephone_label' => 'Tel',
            'telephone_value' => trim((string)($site['telephone'] ?? '+86-760-22211886')),
            'mobile_label' => 'WhatsApp',
            'mobile_value' => trim((string)($site['mobile'] ?? '+86-13925332972')),
            'whatsapp_number' => preg_replace('/\D+/', '', (string)($site['whatsapp'] ?? '8613925332972')),
            'button_label' => 'About Us',
            'button_url' => '/about.php',
        ],
        'newsletter' => [
            'active' => 1,
            'title' => 'Stay inspired',
            'text' => 'Subscribe for product updates, project references and lighting insights.',
            'placeholder' => 'Enter your email address',
            'button' => 'Subscribe',
        ],
        'bottom' => [
            'copyright' => '© '.$year.' '.$company,
            'legal_active' => 1,
            'legal' => [
                ['label'=>'Privacy Policy','href'=>'contact.php#privacy','new_tab'=>0],
                ['label'=>'Cookie Policy','href'=>'contact.php#cookies','new_tab'=>0],
                ['label'=>'Terms & Conditions','href'=>'contact.php#terms','new_tab'=>0],
                ['label'=>'Download Center','href'=>'downloads.php','new_tab'=>0],
            ],
            'social_active' => 1,
            'social' => web_footer_default_socials($site),
        ],
    ];
}

function web_footer_normalize(array $footer, array $site = []): array
{
    $defaults = web_footer_defaults($site);

    // New schema.
    if ((int)($footer['schema_version'] ?? 0) >= 611 || isset($footer['brand'], $footer['contact'], $footer['bottom'])) {
        $theme = array_merge($defaults['theme'], is_array($footer['theme'] ?? null) ? $footer['theme'] : []);
        $theme['background'] = web_footer_safe_hex((string)($theme['background'] ?? '#101010'));
        $brand = array_merge($defaults['brand'], is_array($footer['brand'] ?? null) ? $footer['brand'] : []);
        $contact = array_merge($defaults['contact'], is_array($footer['contact'] ?? null) ? $footer['contact'] : []);

        /*
         * V6.12.8 visibility repair:
         * Earlier colour saves could leave the brand/contact/social switches off.
         * Repair those older records once, then honour intentional visibility choices
         * after the new guard marker has been saved.
         */
        $visibilityGuardVersion = (int)($footer['visibility_guard_version'] ?? 0);
        if ($visibilityGuardVersion < 6128) {
            if (trim((string)($brand['logo'] ?? '')) !== '' || trim((string)($brand['tagline'] ?? '')) !== '') {
                $brand['active'] = 1;
            }
            if (trim((string)($contact['company'] ?? '')) !== '' || trim((string)($contact['email_value'] ?? '')) !== '' || trim((string)($contact['mobile_value'] ?? '')) !== '') {
                $contact['active'] = 1;
            }
        }
        $brand['light_logo'] = trim((string)($brand['light_logo'] ?? '')) ?: 'assets/img/logo-artdon.png';
        $newsletter = array_merge($defaults['newsletter'], is_array($footer['newsletter'] ?? null) ? $footer['newsletter'] : []);
        $bottom = array_merge($defaults['bottom'], is_array($footer['bottom'] ?? null) ? $footer['bottom'] : []);
        if ((int)($footer['visibility_guard_version'] ?? 0) < 6128) {
            $newsletter['active'] = 1;
            $bottom['social_active'] = 1;
        }
        $columns = is_array($footer['columns'] ?? null) ? $footer['columns'] : $defaults['columns'];
        $normalColumns = [];
        foreach ($columns as $column) {
            if (!is_array($column)) continue;
            $title = trim((string)($column['title'] ?? ''));
            if ($title === '') continue;
            $normalColumns[] = [
                'active' => web_footer_bool($column['active'] ?? true) ? 1 : 0,
                'title' => $title,
                'links' => web_footer_unique_links(is_array($column['links'] ?? null) ? $column['links'] : [], 20),
            ];
            if (count($normalColumns) >= 8) break;
        }
        if (!$normalColumns) $normalColumns = $defaults['columns'];
        if ((int)($footer['navigation_version'] ?? 0) < web_footer_navigation_version()) {
            $normalColumns = $defaults['columns'];
        }
        $bottom['legal'] = web_footer_unique_links(is_array($bottom['legal'] ?? null) ? $bottom['legal'] : [], 12);
        $bottom['social'] = web_footer_unique_links(is_array($bottom['social'] ?? null) ? $bottom['social'] : [], 12);
        if (web_footer_bool($bottom['social_active'] ?? true) && !$bottom['social']) {
            $bottom['social'] = web_footer_default_socials($site);
        }
        foreach ($bottom['social'] as $i => $item) {
            $raw = is_array(($footer['bottom']['social'] ?? [])[$i] ?? null) ? $footer['bottom']['social'][$i] : [];
            $bottom['social'][$i]['short'] = trim((string)($raw['short'] ?? $item['short'] ?? $item['label'] ?? ''));
        }
        return [
            'schema_version'=>6128,
            'navigation_version'=>web_footer_navigation_version(),
            'visibility_confirmed'=>1,
            'visibility_guard_version'=>6128,
            'theme'=>$theme,
            'brand'=>$brand,
            'columns'=>$normalColumns,
            'contact'=>$contact,
            'newsletter'=>$newsletter,
            'bottom'=>$bottom,
        ];
    }

    // Legacy V6.10 footer block. Keep its text while rebuilding the layout.
    $defaults['newsletter']['title'] = trim((string)($footer['newsletter_title'] ?? $defaults['newsletter']['title']));
    $defaults['newsletter']['text'] = trim((string)($footer['newsletter_text'] ?? $defaults['newsletter']['text']));
    $defaults['newsletter']['placeholder'] = trim((string)($footer['newsletter_placeholder'] ?? $defaults['newsletter']['placeholder']));
    $defaults['newsletter']['button'] = trim((string)($footer['newsletter_button'] ?? $defaults['newsletter']['button']));
    $defaults['contact']['heading'] = trim((string)($footer['contact_heading'] ?? $defaults['contact']['heading']));
    $defaults['contact']['contact_label'] = trim((string)($footer['contact_person_label'] ?? $defaults['contact']['contact_label']));
    $defaults['contact']['email_label'] = trim((string)($footer['email_label'] ?? $defaults['contact']['email_label']));
    $defaults['contact']['telephone_label'] = trim((string)($footer['telephone_label'] ?? $defaults['contact']['telephone_label']));
    $defaults['contact']['mobile_label'] = trim((string)($footer['mobile_label'] ?? $defaults['contact']['mobile_label']));
    $defaults['bottom']['copyright'] = trim((string)($footer['copyright'] ?? $defaults['bottom']['copyright']));
    if (is_array($footer['legal'] ?? null) && $footer['legal']) {
        $defaults['bottom']['legal'] = web_footer_unique_links($footer['legal'], 12);
    }
    return $defaults;
}
