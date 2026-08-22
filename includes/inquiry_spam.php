<?php

declare(strict_types=1);

function web_inquiry_spam_clip(mixed $value, int $length): string
{
    $text = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
}

function web_inquiry_spam_migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS web_inquiry_spam_rules (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        rule_key CHAR(64) NOT NULL,
        rule_kind VARCHAR(20) NOT NULL DEFAULT 'keyword',
        rule_action VARCHAR(20) NOT NULL DEFAULT 'block',
        field_scope VARCHAR(40) NOT NULL DEFAULT 'all',
        pattern VARCHAR(500) NOT NULL DEFAULT '',
        score SMALLINT UNSIGNED NOT NULL DEFAULT 100,
        label VARCHAR(255) NOT NULL DEFAULT '',
        is_active TINYINT(1) NOT NULL DEFAULT 1,
        hit_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
        last_hit_at DATETIME NULL,
        created_by BIGINT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uk_inquiry_spam_rule_key (rule_key),
        INDEX idx_inquiry_spam_rules_active (is_active, rule_action, rule_kind),
        INDEX idx_inquiry_spam_rules_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS web_inquiry_spam_events (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
        score INT UNSIGNED NOT NULL DEFAULT 0,
        matched_rule_ids VARCHAR(1000) NOT NULL DEFAULT '',
        matched_patterns TEXT NULL,
        reason VARCHAR(500) NOT NULL DEFAULT '',
        source VARCHAR(80) NOT NULL DEFAULT '',
        name VARCHAR(160) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '',
        company VARCHAR(190) NOT NULL DEFAULT '',
        message_excerpt TEXT NULL,
        ip_address VARCHAR(64) NOT NULL DEFAULT '',
        page_url VARCHAR(600) NOT NULL DEFAULT '',
        user_agent VARCHAR(500) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_inquiry_spam_events_created (created_at),
        INDEX idx_inquiry_spam_events_ip (ip_address, created_at),
        INDEX idx_inquiry_spam_events_email (email, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function web_inquiry_spam_default_rules(): array
{
    return [
        ['keyword','block','name','robertwisee',100,'批量代理垃圾询盘姓名'],
        ['regex','block','all','~(?:^|[^a-z0-9])seo(?:[^a-z0-9]|$)~iu',100,'SEO 推广'],
        ['keyword','block','all','search engine optimization',100,'SEO 推广'],
        ['keyword','block','all','google ranking',100,'Google 排名推广'],
        ['keyword','block','all','first page of google',100,'Google 排名推广'],
        ['keyword','block','all','backlink',100,'外链推广'],
        ['keyword','block','all','guest post service',100,'Guest Post 推广'],
        ['keyword','block','all','digital marketing service',100,'数字营销服务'],
        ['keyword','block','all','social media marketing service',100,'社交媒体推广'],
        ['keyword','block','all','lead generation service',100,'获客推广服务'],
        ['keyword','block','all','website development service',100,'建站推广服务'],
        ['keyword','block','all','web design service',100,'建站推广服务'],
        ['keyword','block','all','tiktok promotion',100,'TikTok 推广'],
        ['keyword','block','all','tiktok marketing',100,'TikTok 推广'],
        ['keyword','block','all','抖音',100,'抖音推广'],
        ['keyword','block','all','小红书代运营',100,'小红书推广'],
        ['keyword','block','all','小红书推广',100,'小红书推广'],
        ['keyword','block','all','seo优化',100,'SEO 推广'],
        ['keyword','block','all','谷歌推广',100,'Google 推广'],
        ['keyword','block','all','网站推广',100,'网站推广'],
        ['keyword','block','all','网络推广',100,'网络推广'],
        ['keyword','block','all','外链服务',100,'外链推广'],
        ['keyword','block','all','关键词排名',100,'关键词排名推广'],
    ];
}

function web_inquiry_spam_seed(PDO $pdo): void
{
    web_inquiry_spam_migrate($pdo);
    $seeded = false;
    $seedVersion = '20260822_proxy_guard';
    $installedVersion = '';
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='inquiry_spam_rules_seeded' LIMIT 1");
        $stmt->execute();
        $seeded = trim((string)$stmt->fetchColumn()) === '1';
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='inquiry_spam_rules_seeded_version' LIMIT 1");
        $stmt->execute();
        $installedVersion = trim((string)$stmt->fetchColumn());
    } catch (Throwable $ignored) {}
    if ($seeded && $installedVersion === $seedVersion) return;
    $stmt = $pdo->prepare("INSERT INTO web_inquiry_spam_rules
        (rule_key,rule_kind,rule_action,field_scope,pattern,score,label,is_active)
        VALUES (?,?,?,?,?,?,?,1)
        ON DUPLICATE KEY UPDATE label=VALUES(label), updated_at=CURRENT_TIMESTAMP");
    foreach (web_inquiry_spam_default_rules() as $rule) {
        [$kind,$action,$scope,$pattern,$score,$label] = $rule;
        $key = hash('sha256', implode('|', [$kind,$action,$scope,$pattern]));
        $stmt->execute([$key,$kind,$action,$scope,$pattern,$score,$label]);
    }
    // A version marker makes newly released defaults run once on an installed
    // site. Existing rules disabled by an administrator stay disabled because
    // the duplicate update intentionally does not change is_active.
    try {
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret)
            VALUES ('inquiry_spam_rules_seeded','1',0)
            ON DUPLICATE KEY UPDATE setting_value='1', updated_at=CURRENT_TIMESTAMP")->execute();
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret)
            VALUES ('inquiry_spam_threshold','100',0)
            ON DUPLICATE KEY UPDATE setting_value=IF(setting_value='', '100', setting_value), updated_at=CURRENT_TIMESTAMP")->execute();
        $pdo->prepare("INSERT INTO web_system_settings (setting_key,setting_value,is_secret)
            VALUES ('inquiry_spam_rules_seeded_version',?,0)
            ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value), updated_at=CURRENT_TIMESTAMP")->execute([$seedVersion]);
    } catch (Throwable $ignored) {}
}

function web_inquiry_spam_lower(string $value): string
{
    $value = preg_replace('/\s+/u', ' ', trim($value)) ?: trim($value);
    return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
}

function web_inquiry_spam_email_domain(string $email): string
{
    $at = strrpos($email, '@');
    return $at === false ? '' : strtolower(trim(substr($email, $at + 1), " .\t\n\r\0\x0B"));
}

function web_inquiry_spam_scope_text(array $record, string $scope): string
{
    $allowed = ['name','email','company','country','support_type','product','page_title','message','page_url'];
    if (in_array($scope, $allowed, true)) return (string)($record[$scope] ?? '');
    $parts = [];
    foreach ($allowed as $field) $parts[] = (string)($record[$field] ?? '');
    return implode("\n", $parts);
}

function web_inquiry_spam_rule_matches(array $rule, array $record): bool
{
    $kind = (string)($rule['rule_kind'] ?? 'keyword');
    $pattern = trim((string)($rule['pattern'] ?? ''));
    if ($pattern === '') return false;
    $email = strtolower(trim((string)($record['email'] ?? '')));
    if ($kind === 'email') return $email !== '' && $email === strtolower($pattern);
    if ($kind === 'domain') return web_inquiry_spam_email_domain($email) === strtolower(ltrim($pattern, '@'));

    $text = web_inquiry_spam_scope_text($record, (string)($rule['field_scope'] ?? 'all'));
    if ($kind === 'regex') return @preg_match($pattern, $text) === 1;
    return str_contains(web_inquiry_spam_lower($text), web_inquiry_spam_lower($pattern));
}

function web_inquiry_spam_evaluate_rules(array $record, array $rules, int $threshold = 100): array
{
    $threshold = max(1, min(1000, $threshold));
    $allowMatches = [];
    $blockMatches = [];
    $score = 0;
    foreach ($rules as $rule) {
        if (isset($rule['is_active']) && (int)$rule['is_active'] !== 1) continue;
        if (!web_inquiry_spam_rule_matches($rule, $record)) continue;
        if ((string)($rule['rule_action'] ?? 'block') === 'allow') {
            $allowMatches[] = $rule;
            continue;
        }
        $blockMatches[] = $rule;
        $score += max(0, (int)($rule['score'] ?? 100));
    }
    if ($allowMatches) {
        return ['blocked'=>false,'allowed'=>true,'score'=>0,'matched_rules'=>$allowMatches,'reason'=>'命中白名单'];
    }
    return [
        'blocked'=>$score >= $threshold,
        'allowed'=>false,
        'score'=>$score,
        'matched_rules'=>$blockMatches,
        'reason'=>$score >= $threshold ? '命中广告拦截规则' : '',
    ];
}

function web_inquiry_spam_threshold(PDO $pdo): int
{
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM web_system_settings WHERE setting_key='inquiry_spam_threshold' LIMIT 1");
        $stmt->execute();
        return max(1, min(1000, (int)$stmt->fetchColumn()));
    } catch (Throwable $ignored) {
        return 100;
    }
}

function web_inquiry_spam_evaluate(PDO $pdo, array $record): array
{
    web_inquiry_spam_seed($pdo);
    $rules = $pdo->query("SELECT * FROM web_inquiry_spam_rules WHERE is_active=1 ORDER BY rule_action='allow' DESC, id ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
    $ruleResult = web_inquiry_spam_evaluate_rules($record, $rules, web_inquiry_spam_threshold($pdo));
    if (!empty($ruleResult['blocked']) || !empty($ruleResult['allowed'])) return $ruleResult;
    return web_inquiry_spam_behavior_evaluate($pdo, $record);
}

function web_inquiry_spam_behavior_key(mixed $value, int $maxLength = 500): string
{
    $value = web_inquiry_spam_lower(web_inquiry_spam_clip($value, $maxLength));
    return trim((string)preg_replace('/[\p{Z}\s]+/u', ' ', $value));
}

function web_inquiry_spam_behavior_evaluate(PDO $pdo, array $record): array
{
    $ip = trim((string)($record['ip_address'] ?? ''));
    $name = web_inquiry_spam_behavior_key($record['name'] ?? '', 160);
    $email = strtolower(trim((string)($record['email'] ?? '')));
    $message = web_inquiry_spam_behavior_key($record['message'] ?? '', 1500);
    $clean = ['blocked'=>false,'allowed'=>false,'score'=>0,'matched_rules'=>[],'reason'=>''];
    if ($ip === '') return $clean;

    $recent = [];
    try {
        $recent = $pdo->query("SELECT name,email,message,ip_address
            FROM web_inquiries WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)
            ORDER BY id DESC LIMIT 1500")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $events = $pdo->query("SELECT name,email,message_excerpt AS message,ip_address
            FROM web_inquiry_spam_events WHERE created_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR)
            ORDER BY id DESC LIMIT 1500")->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $recent = array_merge($recent, $events);
    } catch (Throwable $ignored) {
        return $clean;
    }

    $nameIps = [$ip=>true];
    $emailIps = [$ip=>true];
    $messageIps = [$ip=>true];
    foreach ($recent as $old) {
        $oldIp = trim((string)($old['ip_address'] ?? ''));
        if ($oldIp === '') continue;
        if (mb_strlen($name, 'UTF-8') >= 6 && web_inquiry_spam_behavior_key($old['name'] ?? '', 160) === $name) $nameIps[$oldIp] = true;
        if ($email !== '' && strtolower(trim((string)($old['email'] ?? ''))) === $email) $emailIps[$oldIp] = true;
        if (mb_strlen($message, 'UTF-8') >= 16 && web_inquiry_spam_behavior_key($old['message'] ?? '', 1500) === $message) $messageIps[$oldIp] = true;
    }

    $label = '';
    $pattern = '';
    if (mb_strlen($name, 'UTF-8') >= 6 && count($nameIps) >= 5) {
        $label = '同一姓名跨多个 IP 重复提交'; $pattern = $name;
    } elseif ($email !== '' && count($emailIps) >= 4) {
        $label = '同一邮箱跨多个 IP 重复提交'; $pattern = $email;
    } elseif (mb_strlen($message, 'UTF-8') >= 16 && count($messageIps) >= 5) {
        $label = '相同留言跨多个 IP 重复提交'; $pattern = $message;
    }
    if ($label === '') return $clean;

    return [
        'blocked'=>true,
        'allowed'=>false,
        'score'=>100,
        'matched_rules'=>[['id'=>0,'label'=>$label,'pattern'=>web_inquiry_spam_clip($pattern, 500)]],
        'reason'=>'命中跨 IP 重复询盘规则',
    ];
}

function web_inquiry_spam_record_block(PDO $pdo, array $record, array $result): int
{
    $rules = is_array($result['matched_rules'] ?? null) ? $result['matched_rules'] : [];
    $ids = array_values(array_unique(array_filter(array_map(static fn($r): int => (int)($r['id'] ?? 0), $rules))));
    $patterns = [];
    foreach ($rules as $rule) {
        $patterns[] = [
            'id'=>(int)($rule['id'] ?? 0),
            'label'=>(string)($rule['label'] ?? ''),
            'pattern'=>(string)($rule['pattern'] ?? ''),
        ];
    }
    $stmt = $pdo->prepare("INSERT INTO web_inquiry_spam_events
        (score,matched_rule_ids,matched_patterns,reason,source,name,email,company,message_excerpt,ip_address,page_url,user_agent)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)");
    $stmt->execute([
        max(0, (int)($result['score'] ?? 0)),
        implode(',', $ids),
        json_encode($patterns, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]',
        web_inquiry_spam_clip($result['reason'] ?? '命中广告拦截规则', 500),
        web_inquiry_spam_clip($record['source'] ?? '', 80),
        web_inquiry_spam_clip($record['name'] ?? '', 160),
        web_inquiry_spam_clip($record['email'] ?? '', 190),
        web_inquiry_spam_clip($record['company'] ?? '', 190),
        web_inquiry_spam_clip($record['message'] ?? '', 1500),
        web_inquiry_spam_clip($record['ip_address'] ?? '', 64),
        web_inquiry_spam_clip($record['page_url'] ?? '', 600),
        web_inquiry_spam_clip($record['user_agent'] ?? '', 500),
    ]);
    if ($ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare("UPDATE web_inquiry_spam_rules SET hit_count=hit_count+1,last_hit_at=NOW() WHERE id IN ($ph)");
        $update->execute($ids);
    }
    return (int)$pdo->lastInsertId();
}
