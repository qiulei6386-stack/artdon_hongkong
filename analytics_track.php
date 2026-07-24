<?php
/** Public endpoint for Artdon website visitor analytics V7.1.8.138. */
declare(strict_types=1);
require_once __DIR__ . '/includes/visitor_analytics.php';

try {
    $dbError = null;
    $pdo = web_db($dbError);
    if (!$pdo) web_va_json_out(['ok'=>false,'message'=>'database unavailable'], 503);
    web_va_migrate($pdo);

    $raw = file_get_contents('php://input') ?: '';
    $payload = json_decode($raw, true);
    if (!is_array($payload)) $payload = $_POST ?: $_GET;

    $action = web_va_s($payload['action'] ?? 'pageview', 40);
    $visitor = web_va_token($payload['visitor_id'] ?? '', 80);
    $session = web_va_token($payload['session_id'] ?? '', 80);
    $pageview = web_va_token($payload['pageview_id'] ?? '', 90);

    $ua = web_va_s($_SERVER['HTTP_USER_AGENT'] ?? '', 2000);
    $ip = web_va_client_ip();
    $geo = web_va_geo_for_ip($pdo, $ip, false);
    $url = web_va_s($payload['url'] ?? '', 2000);
    $path = web_va_s($payload['path'] ?? (parse_url($url, PHP_URL_PATH) ?: '/'), 600);
    $title = web_va_s($payload['title'] ?? '', 255);
    $referrer = web_va_s($payload['referrer'] ?? ($_SERVER['HTTP_REFERER'] ?? ''), 2000);
    $utmSource = web_va_s($payload['utm_source'] ?? '', 160);
    $utmMedium = web_va_s($payload['utm_medium'] ?? '', 160);
    $utmCampaign = web_va_s($payload['utm_campaign'] ?? '', 190);
    $pageType = web_va_s($payload['page_type'] ?? web_va_page_type($path, $title), 60);
    $product = web_va_s($payload['product_name'] ?? '', 255);
    $productSlug = web_va_s($payload['product_slug'] ?? '', 190);
    $series = web_va_s($payload['series_name'] ?? '', 255);
    $category = web_va_s($payload['category_name'] ?? '', 255);
    $section = web_va_s($payload['section'] ?? '', 255);
    $duration = max(0, min(86400, (int)($payload['duration_seconds'] ?? 0)));
    $scroll = max(0, min(100, (int)($payload['scroll_depth'] ?? 0)));
    $screen = web_va_s($payload['screen'] ?? '', 40);
    $timezone = web_va_s($payload['timezone'] ?? '', 80);
    $language = web_va_s($payload['browser_language'] ?? ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''), 120);
    $fingerprint = web_va_s($payload['visitor_fingerprint_hash'] ?? '', 96);
    if ($fingerprint === '') {
        $fingerprint = hash('sha256', $ip . '|' . $ua . '|' . $language . '|' . $timezone . '|' . $screen . '|' . web_va_device_type($ua));
    }
    $ipGroupKey = web_va_ip_group_key($ip, $geo);
    $isBot = web_va_bot_score($ua) ? 1 : 0;
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare('INSERT INTO web_visit_sessions(visitor_token,session_token,first_seen_at,last_seen_at,ip_address,ip_country_code,ip_country,ip_region,ip_city,ip_isp,ip_org,ip_geo_source,ip_geo_updated_at,user_agent,browser_language,device_type,browser,os,screen_size,timezone,landing_url,referrer,referrer_host,utm_source,utm_medium,utm_campaign,page_count,product_page_count,duration_seconds,visitor_fingerprint_hash,ip_group_key,is_bot,created_at,updated_at)
        VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,?,?,?,?,?,?,?)
        ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at),ip_address=VALUES(ip_address),ip_country_code=IF(VALUES(ip_country_code)<>\'\',VALUES(ip_country_code),ip_country_code),ip_country=IF(VALUES(ip_country)<>\'\',VALUES(ip_country),ip_country),ip_region=IF(VALUES(ip_region)<>\'\',VALUES(ip_region),ip_region),ip_city=IF(VALUES(ip_city)<>\'\',VALUES(ip_city),ip_city),ip_isp=IF(VALUES(ip_isp)<>\'\',VALUES(ip_isp),ip_isp),ip_org=IF(VALUES(ip_org)<>\'\',VALUES(ip_org),ip_org),ip_geo_source=IF(VALUES(ip_geo_source)<>\'\',VALUES(ip_geo_source),ip_geo_source),ip_geo_updated_at=IF(VALUES(ip_geo_source)<>\'\',VALUES(ip_geo_updated_at),ip_geo_updated_at),user_agent=VALUES(user_agent),browser_language=VALUES(browser_language),device_type=VALUES(device_type),browser=VALUES(browser),os=VALUES(os),screen_size=VALUES(screen_size),timezone=VALUES(timezone),visitor_fingerprint_hash=VALUES(visitor_fingerprint_hash),ip_group_key=VALUES(ip_group_key),is_bot=VALUES(is_bot),duration_seconds=GREATEST(duration_seconds,VALUES(duration_seconds)),updated_at=VALUES(updated_at)');
    $stmt->execute([
        $visitor,$session,$now,$now,$ip,
        $geo['country_code'] ?? '',$geo['country_name'] ?? '',$geo['region_name'] ?? '',$geo['city_name'] ?? '',$geo['isp'] ?? '',$geo['org'] ?? '',$geo['source'] ?? '',($geo['source'] ?? '') !== '' ? $now : null,
        $ua,$language,web_va_device_type($ua),web_va_browser($ua),web_va_os($ua),$screen,$timezone,$url,$referrer,web_va_ref_host($referrer),$utmSource,$utmMedium,$utmCampaign,($pageType === 'product' ? 1 : 0),$duration,$fingerprint,$ipGroupKey,$isBot,$now,$now
    ]);

    $base = ['pageview'=>$pageview,'session'=>$session,'visitor'=>$visitor,'pageType'=>$pageType,'product'=>$product];

    if ($action === 'pageview') {
        $stmt = $pdo->prepare('INSERT INTO web_visit_pageviews(pageview_token,session_token,visitor_token,page_type,page_url,path,title,product_name,product_slug,series_name,category_name,referrer,referrer_host,utm_source,utm_medium,utm_campaign,started_at,last_seen_at,duration_seconds,scroll_depth,current_section,max_section,created_at,updated_at)
            VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at),title=VALUES(title),product_name=VALUES(product_name),product_slug=VALUES(product_slug),series_name=VALUES(series_name),category_name=VALUES(category_name),duration_seconds=GREATEST(duration_seconds,VALUES(duration_seconds)),scroll_depth=GREATEST(scroll_depth,VALUES(scroll_depth)),current_section=VALUES(current_section),updated_at=VALUES(updated_at)');
        $stmt->execute([$pageview,$session,$visitor,$pageType,$url,$path,$title,$product,$productSlug,$series,$category,$referrer,web_va_ref_host($referrer),$utmSource,$utmMedium,$utmCampaign,$now,$now,$duration,$scroll,$section,$section,$now,$now]);
        $pdo->prepare('UPDATE web_visit_sessions SET page_count=(SELECT COUNT(*) FROM web_visit_pageviews WHERE session_token=?), product_page_count=(SELECT COUNT(*) FROM web_visit_pageviews WHERE session_token=? AND page_type=\'product\'), updated_at=? WHERE session_token=?')->execute([$session,$session,$now,$session]);
        web_va_section_stats($pdo, $payload, $base);
        web_va_update_profile($pdo, $visitor, $session);
        web_va_json_out(['ok'=>true,'visitor_id'=>$visitor,'session_id'=>$session,'pageview_id'=>$pageview]);
    }

    if ($action === 'heartbeat') {
        $stmt = $pdo->prepare('UPDATE web_visit_pageviews SET last_seen_at=?,duration_seconds=GREATEST(duration_seconds,?),scroll_depth=GREATEST(scroll_depth,?),current_section=?,max_section=IF(max_section="",?,max_section),updated_at=? WHERE pageview_token=?');
        $stmt->execute([$now,$duration,$scroll,$section,$section,$now,$pageview]);
        web_va_section_stats($pdo, $payload, $base);
        $pdo->prepare('UPDATE web_visit_sessions SET last_seen_at=?,page_count=(SELECT COUNT(*) FROM web_visit_pageviews WHERE session_token=?),product_page_count=(SELECT COUNT(*) FROM web_visit_pageviews WHERE session_token=? AND page_type=\'product\'),duration_seconds=GREATEST(duration_seconds,(SELECT COALESCE(SUM(duration_seconds),0) FROM web_visit_pageviews WHERE session_token=?)),updated_at=? WHERE session_token=?')->execute([$now,$session,$session,$session,$now,$session]);
        web_va_update_profile($pdo, $visitor, $session);
        web_va_json_out(['ok'=>true]);
    }

    if (in_array($action, ['section','click','quote','download','share'], true)) {
        $eventName = web_va_s($payload['event_name'] ?? $action, 255);
        $targetText = web_va_s($payload['target_text'] ?? '', 255);
        $targetUrl = web_va_s($payload['target_url'] ?? '', 2000);
        $value = $payload['value'] ?? [];
        $valueJson = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $stmt = $pdo->prepare('INSERT INTO web_visit_events(pageview_token,session_token,visitor_token,event_type,event_name,page_type,page_url,path,section_name,product_name,target_text,target_url,value_json,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$pageview,$session,$visitor,$action,$eventName,$pageType,$url,$path,$section,$product,$targetText,$targetUrl,$valueJson,$now]);
        $pdo->prepare('UPDATE web_visit_sessions SET event_count=(SELECT COUNT(*) FROM web_visit_events WHERE session_token=?), download_count=(SELECT COUNT(*) FROM web_visit_events WHERE session_token=? AND event_type=\'download\'), updated_at=? WHERE session_token=?')
            ->execute([$session,$session,$now,$session]);
        if ($section !== '') {
            $pdo->prepare('INSERT INTO web_visit_section_stats(pageview_token,session_token,visitor_token,section_name,page_type,product_name,duration_seconds,view_count,first_seen_at,last_seen_at) VALUES(?,?,?,?,?,?,0,1,?,?) ON DUPLICATE KEY UPDATE view_count=view_count+1,last_seen_at=VALUES(last_seen_at)')
                ->execute([$pageview,$session,$visitor,$section,$pageType,$product,$now,$now]);
            $pdo->prepare('UPDATE web_visit_pageviews SET current_section=?,max_section=IF(max_section="",?,max_section),last_seen_at=?,updated_at=? WHERE pageview_token=?')->execute([$section,$section,$now,$now,$pageview]);
        }
        web_va_section_stats($pdo, $payload, $base);
        web_va_update_profile($pdo, $visitor, $session);
        web_va_json_out(['ok'=>true]);
    }

    web_va_json_out(['ok'=>true]);
} catch (Throwable $e) {
    web_va_json_out(['ok'=>false,'message'=>$e->getMessage()], 500);
}
