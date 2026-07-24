<?php
/**
 * Artdon Lighting V7.0.9.2 product/series card runtime.
 * Loaded inline by products.php / series.php / product.php / product-card pages.
 */
declare(strict_types=1);

if (!function_exists('artdon_v7092_defaults')) {
    function artdon_v7092_defaults(): array
    {
        return [
            'hide_view_details' => '1',
            'hide_info_icon' => '1',
            'font_scale_percent' => '100',
            'series_title_font_size' => '18',
            'series_subtitle_font_size' => '13',
            'series_spec_label_font_size' => '13',
            'series_spec_value_font_size' => '14',
            'series_tag_font_size' => '11',
            'product_title_font_size' => '16',
            'product_subtitle_font_size' => '12',
            'product_spec_label_font_size' => '12',
            'product_spec_value_font_size' => '13',
            'product_tag_font_size' => '11',
            'description_font_size' => '12',
            'meta_font_size' => '11',
            'family_heading_font_size' => '28',
            'badge_font_size' => '11',
            'badge_top' => '14',
            'badge_left' => '14',
            'badge_radius' => '999',
            'card_width' => '',
            'card_min_height' => '',
            'image_subject_scale' => '',
        ];
    }
}

if (!function_exists('artdon_v7092_db')) {
    function artdon_v7092_db(): ?PDO
    {
        static $resolved = false;
        static $pdo = null;
        if ($resolved) return $pdo instanceof PDO ? $pdo : null;
        $resolved = true;
        try {
            if (function_exists('web_db')) {
                $error = null;
                $candidate = web_db($error);
                if ($candidate instanceof PDO) $pdo = $candidate;
            }
        } catch (Throwable $e) { $pdo = null; }
        if (!$pdo) {
            foreach (['pdo','db','dbh'] as $key) {
                if (isset($GLOBALS[$key]) && $GLOBALS[$key] instanceof PDO) { $pdo = $GLOBALS[$key]; break; }
            }
        }
        if ($pdo instanceof PDO) {
            try {
                $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
                if ($name !== '' && strcasecmp($name, 'artdon_web') !== 0) return null;
                $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                return $pdo;
            } catch (Throwable $e) { return null; }
        }
        return null;
    }
}

if (!function_exists('artdon_v7092_settings')) {
    function artdon_v7092_settings(): array
    {
        $settings = artdon_v7092_defaults();
        $pdo = artdon_v7092_db();
        if (!$pdo) return $settings;
        try {
            foreach ($pdo->query('SELECT setting_key,setting_value FROM artdon_card_settings')->fetchAll() as $row) {
                $key = (string)($row['setting_key'] ?? '');
                if ($key !== '' && array_key_exists($key, $settings)) $settings[$key] = (string)($row['setting_value'] ?? '');
            }
        } catch (Throwable $e) {}
        $settings['hide_view_details'] = '1';
        $settings['hide_info_icon'] = '1';
        return $settings;
    }
}

if (!function_exists('artdon_v7092_flags')) {
    function artdon_v7092_flags(): array
    {
        $flags = [];
        $pdo = artdon_v7092_db();
        if (!$pdo) return $flags;
        try {
            try {
                $rows = $pdo->query("SELECT id,item_type,item_id,item_name,badge_type,badge_text,badge_style,badge_position,badge_animation,enabled FROM artdon_card_flags WHERE enabled=1 AND COALESCE(badge_type,'')<>'none' ORDER BY id DESC")->fetchAll();
            } catch (Throwable $e) {
                $rows = $pdo->query("SELECT id,item_type,item_id,item_name,badge_type,badge_text,enabled FROM artdon_card_flags WHERE enabled=1 AND badge_type IN ('new','star') ORDER BY id DESC")->fetchAll();
            }
            $styleAllow=['rect'=>1,'capsule'=>1,'polygon16'=>1,'circle'=>1,'outline'=>1,'black'=>1,'corner'=>1,'ribbon'=>1,'breathing-dot'=>1,'topline'=>1];
            $posAllow=['top-left'=>1,'top-right'=>1,'bottom-left'=>1,'bottom-right'=>1];
            $animAllow=['none'=>1,'breathe'=>1,'pulse'=>1];
            foreach ($rows as $row) {
                $type = ((string)($row['item_type'] ?? 'series') === 'product') ? 'product' : 'series';
                $badgeType = ((string)($row['badge_type'] ?? 'new') === 'star') ? 'star' : 'new';
                $text = trim((string)($row['badge_text'] ?? ''));
                if ($text === '') $text = $badgeType === 'star' ? '★' : 'NEW';
                $style = strtolower(trim((string)($row['badge_style'] ?? 'capsule')));
                if (!isset($styleAllow[$style])) $style = $badgeType === 'star' ? 'polygon16' : 'capsule';
                $position = strtolower(trim((string)($row['badge_position'] ?? 'top-left')));
                if (!isset($posAllow[$position])) $position = 'top-left';
                $animation = strtolower(trim((string)($row['badge_animation'] ?? 'none')));
                if (!isset($animAllow[$animation])) $animation = 'none';
                if ($style === 'breathing-dot' && $animation === 'none') $animation = 'breathe';
                $flags[] = [
                    'id'=>(int)($row['id'] ?? 0),
                    'item_type'=>$type,
                    'item_id'=>trim((string)($row['item_id'] ?? '')),
                    'item_name'=>trim((string)($row['item_name'] ?? '')),
                    'badge_type'=>$badgeType,
                    'badge_text'=>$text,
                    'badge_style'=>$style,
                    'badge_position'=>$position,
                    'badge_animation'=>$animation,
                ];
            }
        } catch (Throwable $e) {}
        return $flags;
    }
}

if (!function_exists('artdon_card_runtime_v7092_output')) {
    function artdon_card_runtime_v7092_output(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $settingsJson = json_encode(artdon_v7092_settings(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $flagsJson = json_encode(artdon_v7092_flags(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        if (!is_string($settingsJson)) $settingsJson = '{}';
        if (!is_string($flagsJson)) $flagsJson = '[]';
        ?>
<!-- ARTDON_V7092_RUNTIME_START -->
<style id="artdon-v7092-runtime-style">
:root{
  --artdon-v7092-series-title:18px;--artdon-v7092-series-subtitle:13px;
  --artdon-v7092-series-label:13px;--artdon-v7092-series-value:14px;--artdon-v7092-series-tag:11px;
  --artdon-v7092-product-title:16px;--artdon-v7092-product-subtitle:12px;
  --artdon-v7092-product-label:12px;--artdon-v7092-product-value:13px;--artdon-v7092-product-tag:11px;
  --artdon-v7092-description:12px;--artdon-v7092-meta:11px;--artdon-v7092-family-title:28px;
  --artdon-v7092-badge-size:11px;--artdon-v7092-badge-top:14px;--artdon-v7092-badge-left:14px;--artdon-v7092-badge-radius:999px;
}
/* Fixed removals. These rules are intentionally stronger than every old card patch. */
html body .catalog-card-info,html body .catalog-info,html body .info-icon,html body .info-dot,
html body .card-info-icon,html body .product-info-icon,html body .series-info-icon,html body .circle-info,
html body [data-artdon-v7092-hidden="info-icon"]{display:none!important;visibility:hidden!important;opacity:0!important;width:0!important;height:0!important;min-width:0!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;pointer-events:none!important}
html body .catalog-card-link::before,html body .catalog-card-link::after,
html body .catalog-card-body::before,html body .catalog-card-body::after,
html body .catalog-card::before,html body .catalog-card::after,
html body .product-card::before,html body .product-card::after,
html body .series-card::before,html body .series-card::after,
html body .artdon-v7092-pseudo-details-before::before,html body .artdon-v7092-pseudo-details-after::after{content:none!important;display:none!important;visibility:hidden!important;opacity:0!important;width:0!important;height:0!important;margin:0!important;padding:0!important;border:0!important;pointer-events:none!important}
html body .catalog-card .view-details,html body .catalog-card .view-detail,html body .catalog-card .details-btn,
html body .catalog-card .btn-details,html body .catalog-card .catalog-card-cta,html body .catalog-card .catalog-card-action,
html body .catalog-card .catalog-card-button,html body .product-card .view-details,html body .series-card .view-details,
html body [data-artdon-v7092-hidden="view-details"],html body [data-artdon-v7092-hidden="view-details-wrap"]{display:none!important;visibility:hidden!important;opacity:0!important;width:0!important;height:0!important;min-width:0!important;min-height:0!important;margin:0!important;padding:0!important;border:0!important;overflow:hidden!important;pointer-events:none!important}
/* Series cards on products.php. */
html body .catalog-card-v51 .catalog-card-body>h3,html body .catalog-card .catalog-card-body>h3{font-size:var(--artdon-v7092-series-title)!important;line-height:1.16!important}
html body .catalog-card-v51 .catalog-card-subtitle,html body .catalog-card .catalog-card-subtitle{font-size:var(--artdon-v7092-series-subtitle)!important;line-height:1.42!important}
html body .catalog-card-v51 .catalog-card-metrics dt,html body .catalog-card .catalog-card-metrics dt{font-size:var(--artdon-v7092-series-label)!important;line-height:1.36!important}
html body .catalog-card-v51 .catalog-card-metrics dd,html body .catalog-card .catalog-card-metrics dd{font-size:var(--artdon-v7092-series-value)!important;line-height:1.36!important}
html body .catalog-card-v51 .catalog-card-tags span,html body .catalog-card .catalog-card-tags span{font-size:var(--artdon-v7092-series-tag)!important}
html body .catalog-family-divider h3{font-size:var(--artdon-v7092-family-title)!important}
/* Concrete product cards inside a series/detail page. */
html body .family-variant-grid h3,html body .family-related-grid h3,html body .variant-siblings>div>a h3,
html body .series-product-card h3,html body .series-variant-card h3,html body .product-variant-card h3,
html body .variant-card h3,html body .series-products article h3,html body .series-products a h3{font-size:var(--artdon-v7092-product-title)!important;line-height:1.16!important}
html body .family-variant-grid p,html body .family-related-grid p,html body .variant-siblings>div>a>p,
html body .series-product-card .subtitle,html body .series-variant-card .subtitle,html body .product-variant-card .subtitle,
html body .variant-card .subtitle{font-size:var(--artdon-v7092-product-subtitle)!important}
html body .series-product-card dt,html body .series-variant-card dt,html body .product-variant-card dt,html body .variant-card dt{font-size:var(--artdon-v7092-product-label)!important}
html body .series-product-card dd,html body .series-variant-card dd,html body .product-variant-card dd,html body .variant-card dd{font-size:var(--artdon-v7092-product-value)!important}
html body .series-product-card .tags span,html body .series-variant-card .tags span,html body .product-variant-card .tags span,
html body .variant-card .tags span,html body .variant-tags span{font-size:var(--artdon-v7092-product-tag)!important}
html body .catalog-card .description,html body .catalog-card .card-description,html body .series-product-card .description,
html body .product-variant-card .description{font-size:var(--artdon-v7092-description)!important}
html body .catalog-card small,html body .series-product-card small,html body .product-variant-card small,
html body .variant-card small,html body .variant-siblings>div>a>span{font-size:var(--artdon-v7092-meta)!important}
/* Keep the existing V7.0.8 frame, image square and spacing. Only optional values override them. */
html body .catalog-card-image,html body .family-variant-grid figure,html body .family-related-grid figure,
html body .variant-siblings figure,html body .series-product-card figure,html body .product-variant-card figure{position:relative!important}
html body .artdon-card-badge-v7092{position:absolute!important;z-index:40!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;width:auto!important;height:auto!important;min-width:0!important;min-height:0!important;margin:0!important;padding:7px 11px 6px!important;border:0!important;border-radius:4px!important;background:#d71920!important;color:#fff!important;font-size:var(--artdon-v7092-badge-size)!important;font-weight:900!important;line-height:1!important;letter-spacing:.08em!important;text-transform:uppercase!important;box-shadow:0 6px 16px rgba(0,0,0,.10)!important;pointer-events:none!important;text-decoration:none!important}
html body .artdon-card-badge-v7092.pos-top-left{top:var(--artdon-v7092-badge-top)!important;left:var(--artdon-v7092-badge-left)!important;right:auto!important;bottom:auto!important}html body .artdon-card-badge-v7092.pos-top-right{top:var(--artdon-v7092-badge-top)!important;right:var(--artdon-v7092-badge-left)!important;left:auto!important;bottom:auto!important}html body .artdon-card-badge-v7092.pos-bottom-left{bottom:var(--artdon-v7092-badge-top)!important;left:var(--artdon-v7092-badge-left)!important;top:auto!important;right:auto!important}html body .artdon-card-badge-v7092.pos-bottom-right{bottom:var(--artdon-v7092-badge-top)!important;right:var(--artdon-v7092-badge-left)!important;top:auto!important;left:auto!important}
html body .artdon-card-badge-v7092.style-rect{border-radius:4px!important;background:#d71920!important;color:#fff!important}html body .artdon-card-badge-v7092.style-capsule{border-radius:999px!important;background:#d71920!important;color:#fff!important;padding:7px 13px 6px!important}html body .artdon-card-badge-v7092.style-polygon16{width:46px!important;height:46px!important;padding:0!important;background:#d71920!important;color:#fff!important;border-radius:0!important;clip-path:polygon(50% 0%,61% 8%,75% 7%,82% 19%,93% 25%,92% 39%,100% 50%,92% 61%,93% 75%,81% 82%,75% 93%,61% 92%,50% 100%,39% 92%,25% 93%,18% 81%,7% 75%,8% 61%,0% 50%,8% 39%,7% 25%,19% 18%,25% 7%,39% 8%)!important;font-size:10px!important;text-align:center!important}html body .artdon-card-badge-v7092.style-circle{width:44px!important;height:44px!important;border-radius:50%!important;padding:0!important;background:#d71920!important;color:#fff!important;text-align:center!important;font-size:10px!important}html body .artdon-card-badge-v7092.style-outline{background:rgba(255,255,255,.88)!important;color:#d71920!important;border:1px solid #d71920!important;box-shadow:none!important}html body .artdon-card-badge-v7092.style-black{background:#111!important;color:#fff!important;border-radius:3px!important}.artdon-card-badge-v7092.style-black:before{content:"";width:5px;height:5px;border-radius:50%;background:#d71920;margin-right:7px}html body .artdon-card-badge-v7092.style-corner{top:0!important;left:0!important;right:auto!important;bottom:auto!important;border-radius:0!important;background:#d71920!important;color:#fff!important;clip-path:polygon(0 0,100% 0,0 100%)!important;width:64px!important;height:64px!important;padding:8px 24px 28px 8px!important;align-items:flex-start!important;justify-content:flex-start!important;font-size:9px!important;box-shadow:none!important}.artdon-card-badge-v7092.style-corner.pos-top-right{left:auto!important;right:0!important;clip-path:polygon(0 0,100% 0,100% 100%)!important;padding:8px 8px 28px 24px!important}.artdon-card-badge-v7092.style-corner.pos-bottom-left{top:auto!important;bottom:0!important;clip-path:polygon(0 0,100% 100%,0 100%)!important;padding:28px 24px 8px 8px!important}.artdon-card-badge-v7092.style-corner.pos-bottom-right{top:auto!important;left:auto!important;right:0!important;bottom:0!important;clip-path:polygon(100% 0,100% 100%,0 100%)!important;padding:28px 8px 8px 24px!important}html body .artdon-card-badge-v7092.style-ribbon{border-radius:0!important;background:#d71920!important;color:#fff!important;padding:8px 12px 9px!important}.artdon-card-badge-v7092.style-ribbon:after{content:"";position:absolute;left:0;right:0;bottom:-8px;margin:auto;width:0;height:0;border-left:9px solid transparent;border-right:9px solid transparent;border-top:8px solid #d71920}html body .artdon-card-badge-v7092.style-breathing-dot{background:transparent!important;color:#d71920!important;box-shadow:none!important;padding:0!important;gap:7px!important}.artdon-card-badge-v7092.style-breathing-dot:before{content:"";width:10px;height:10px;border-radius:50%;background:#d71920;box-shadow:0 0 0 0 rgba(215,25,32,.45);animation:artdonBadgeBreathe 1.7s ease-in-out infinite}html body .artdon-card-badge-v7092.style-topline{top:0!important;left:0!important;right:0!important;bottom:auto!important;width:100%!important;justify-content:flex-start!important;border-radius:0!important;background:transparent!important;color:#d71920!important;border-top:3px solid #d71920!important;padding:8px 12px 0!important;box-shadow:none!important}
html body .artdon-card-badge-v7092.anim-breathe{animation:artdonBadgeSoftBreathe 1.8s ease-in-out infinite!important}html body .artdon-card-badge-v7092.anim-pulse{animation:artdonBadgePulse 1.2s ease-in-out infinite!important}@keyframes artdonBadgeBreathe{0%,100%{box-shadow:0 0 0 0 rgba(215,25,32,.45)}50%{box-shadow:0 0 0 9px rgba(215,25,32,0)}}@keyframes artdonBadgeSoftBreathe{0%,100%{transform:scale(1)}50%{transform:scale(1.045)}}@keyframes artdonBadgePulse{0%,100%{opacity:1}50%{opacity:.72}}
html.artdon-v7092-has-card-width body .catalog-card-v51,html.artdon-v7092-has-card-width body .series-product-card,
html.artdon-v7092-has-card-width body .product-variant-card{width:var(--artdon-v7092-card-width)!important;max-width:100%!important}
html.artdon-v7092-has-card-height body .catalog-card-v51 .catalog-card-body,html.artdon-v7092-has-card-height body .series-product-card,
html.artdon-v7092-has-card-height body .product-variant-card{min-height:var(--artdon-v7092-card-min-height)!important}
html.artdon-v7092-has-image-scale body .catalog-card-v51 .catalog-card-image img,
html.artdon-v7092-has-image-scale body .series-product-card figure img,
html.artdon-v7092-has-image-scale body .product-variant-card figure img{width:var(--artdon-v7092-image-scale)!important;height:var(--artdon-v7092-image-scale)!important;max-width:var(--artdon-v7092-image-scale)!important;max-height:var(--artdon-v7092-image-scale)!important;object-fit:contain!important}
</style>
<script id="artdon-v7092-runtime-script">
window.ARTDON_CARD_SETTINGS_V7092=<?=$settingsJson?>;
window.ARTDON_CARD_FLAGS_V7092=<?=$flagsJson?>;
(function(){
'use strict';
var root=document.documentElement;root.setAttribute('data-artdon-card-version','7.0.9.2');
function num(v,d,min,max){var n=parseFloat(v);if(!isFinite(n))n=d;return Math.max(min,Math.min(max,n));}
function applySettings(s){s=s||{};var scale=num(s.font_scale_percent,100,55,150)/100;
 function px(key,def,min,max){return (num(s[key],def,min,max)*scale).toFixed(2).replace(/\.00$/,'')+'px';}
 var vars={
 '--artdon-v7092-series-title':px('series_title_font_size',18,8,80),'--artdon-v7092-series-subtitle':px('series_subtitle_font_size',13,8,60),
 '--artdon-v7092-series-label':px('series_spec_label_font_size',13,8,60),'--artdon-v7092-series-value':px('series_spec_value_font_size',14,8,60),'--artdon-v7092-series-tag':px('series_tag_font_size',11,8,40),
 '--artdon-v7092-product-title':px('product_title_font_size',16,8,80),'--artdon-v7092-product-subtitle':px('product_subtitle_font_size',12,8,60),
 '--artdon-v7092-product-label':px('product_spec_label_font_size',12,8,60),'--artdon-v7092-product-value':px('product_spec_value_font_size',13,8,60),'--artdon-v7092-product-tag':px('product_tag_font_size',11,8,40),
 '--artdon-v7092-description':px('description_font_size',12,8,60),'--artdon-v7092-meta':px('meta_font_size',11,8,40),
 '--artdon-v7092-family-title':px('family_heading_font_size',28,12,72),'--artdon-v7092-badge-size':px('badge_font_size',11,8,36),
 '--artdon-v7092-badge-top':num(s.badge_top,14,0,300)+'px','--artdon-v7092-badge-left':num(s.badge_left,14,0,300)+'px','--artdon-v7092-badge-radius':num(s.badge_radius,999,0,999)+'px'};
 Object.keys(vars).forEach(function(k){root.style.setProperty(k,vars[k]);});
 var width=parseFloat(s.card_width||'');root.classList.toggle('artdon-v7092-has-card-width',isFinite(width)&&width>0);if(isFinite(width)&&width>0)root.style.setProperty('--artdon-v7092-card-width',width+'px');
 var height=parseFloat(s.card_min_height||'');root.classList.toggle('artdon-v7092-has-card-height',isFinite(height)&&height>0);if(isFinite(height)&&height>0)root.style.setProperty('--artdon-v7092-card-min-height',height+'px');
 var imageScale=parseFloat(s.image_subject_scale||'');root.classList.toggle('artdon-v7092-has-image-scale',isFinite(imageScale)&&imageScale>0);if(isFinite(imageScale)&&imageScale>0)root.style.setProperty('--artdon-v7092-image-scale',imageScale+'%');
}
function norm(v){return String(v||'').trim().replace(/\s+/g,' ').toLowerCase();}
function all(base,selector){try{return Array.prototype.slice.call((base||document).querySelectorAll(selector));}catch(e){return [];}}
function removeOld(base){
 all(base,'.catalog-card-info,.product-card-info,.series-card-info,.view-details,.view-detail,.details-btn,.btn-details,.catalog-card-cta,.catalog-card-action,.catalog-card-button,[data-view-details]').forEach(function(el){el.remove();});
 all(base,'.catalog-card a,.catalog-card button,.catalog-card [role="button"],.series-card a,.series-card button,.product-card a,.product-card button,.variant-card a,.variant-card button,.family-variant-grid a,.family-related-grid a,.variant-siblings a').forEach(function(el){
   var text=norm(el.textContent);if(text==='view details'||text==='view detail'){el.setAttribute('data-artdon-v7092-hidden','view-details');el.remove();}
 });
 all(base,'.catalog-card-image span,.catalog-card-image i,.product-card figure span,.series-card figure span').forEach(function(el){
   var text=norm(el.textContent),cs='';try{cs=getComputedStyle(el);}catch(e){};
   if((text==='i'||text==='!'||text==='ⓘ')&&(el.className||cs.borderRadius==='50%')){el.setAttribute('data-artdon-v7092-hidden','info-icon');el.remove();}
 });
}
function hrefData(card){var a=card.matches&&card.matches('a[href]')?card:card.querySelector&&card.querySelector('a[href]');var out={slug:'',id:'',href:''};if(!a)return out;out.href=a.getAttribute('href')||'';try{var u=new URL(a.href,location.href);out.slug=u.searchParams.get('slug')||'';out.id=u.searchParams.get('id')||'';}catch(e){}return out;}
function cardType(card){if(card.dataset&&card.dataset.artdonCardType)return card.dataset.artdonCardType==='product'?'product':'series';var d=hrefData(card);if(/product\.php/i.test(d.href)||card.closest('.variant-siblings,.family-variant-grid,.series-products,.series-variants')||card.matches('.series-product-card,.series-variant-card,.product-variant-card,.variant-card'))return 'product';return 'series';}
function matchFlag(card,flags){var type=cardType(card),title=card.querySelector&&card.querySelector('h3,h2,h4'),name=norm((card.dataset&&card.dataset.artdonCardName)||(title?title.textContent:'')),d=hrefData(card),ids=[];
 ['artdonCardId','artdonCardSlug'].forEach(function(k){if(card.dataset&&card.dataset[k])ids.push(String(card.dataset[k]));});if(d.slug)ids.push(d.slug);if(d.id)ids.push(d.id);var expanded=ids.slice();ids.forEach(function(v){expanded.push('id:'+v,'slug:'+v);});ids=expanded;
 for(var i=0;i<flags.length;i++){var f=flags[i]||{};if(f.item_type!==type)continue;if(f.item_id&&ids.indexOf(String(f.item_id))!==-1)return f;}
 for(var j=0;j<flags.length;j++){var x=flags[j]||{};if(x.item_type===type&&name&&norm(x.item_name)===name)return x;}return null;}
function addBadges(base,flags){var selector='[data-artdon-card-type],.catalog-card,.series-card,.product-card,.series-product-card,.series-variant-card,.product-variant-card,.variant-card,.family-variant-grid>a,.family-related-grid>a,.variant-siblings>div>a,.series-products>a,.series-products article';
 all(base,selector).forEach(function(card){if(card.querySelector&&card.querySelector('.artdon-card-badge-v7092'))return;var f=matchFlag(card,flags);if(!f)return;var host=card.querySelector&&card.querySelector('figure,.catalog-card-image,.image,.media');if(!host)return;var b=document.createElement('span');var st=String(f.badge_style||'capsule').replace(/[^a-z0-9\-]/g,'')||'capsule',ps=String(f.badge_position||'top-left').replace(/[^a-z0-9\-]/g,'')||'top-left',an=String(f.badge_animation||'none').replace(/[^a-z0-9\-]/g,'')||'none';b.className='artdon-card-badge-v7092 style-'+st+' pos-'+ps+' anim-'+an;b.textContent=String(f.badge_text||'').trim()||(f.badge_type==='star'?'★':'NEW');host.appendChild(b);});}
function start(){var settings=window.ARTDON_CARD_SETTINGS_V7092||{},flags=window.ARTDON_CARD_FLAGS_V7092||[];applySettings(settings);removeOld(document);addBadges(document,flags);
 var observer=new MutationObserver(function(items){items.forEach(function(item){Array.prototype.forEach.call(item.addedNodes||[],function(node){if(node&&node.nodeType===1){removeOld(node);addBadges(node,flags);}});});});if(document.body)observer.observe(document.body,{childList:true,subtree:true});
}
if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',start,{once:true});else start();
})();
</script>
<!-- ARTDON_V7092_RUNTIME_END -->
<?php
    }
}
