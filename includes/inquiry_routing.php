<?php

declare(strict_types=1);

/**
 * 官网询盘 → 广州暂存池 / 任务中心路由配置。
 * 公开表单不会接收负责人或派工参数，所有路由参数都由香港后台生成。
 * 客户信息只进入暂存池，不直接新增广州正式客户。
 */

function web_inquiry_route_types(): array
{
    return [
        'product' => '产品咨询',
        'quotation' => '报价需求',
        'technical-files' => '技术资料',
        'sample' => '样品需求',
        'custom-project' => '定制项目',
        'other' => '其他询盘',
    ];
}

function web_inquiry_routing_defaults(): array
{
    return [
        'enabled' => 1,
        'owner' => 'boss',
        'assignees' => 'boss',
        'due_days' => 1,
        'priority' => 'normal',
        'create_customer' => 0,
        'create_contact' => 0,
        'create_crm_inquiry' => 1,
        'create_crm_task' => 1,
        'create_dispatch' => 1,
        'task_title_template' => '官网询价｜{project}｜{name}',
        'project_template' => '{project}',
        'dispatch_content_template' => "客户：{name}\n邮箱：{email}\n公司：{company}\n国家／地区：{country}\n需求类型：{support_label}\n项目：{project}\n页面标题：{page_title}\n派工内容：{message}\n关联页面：{page_url}\n提交时间：{submitted_at}\n官网询盘编号：{inquiry_id}",
        'rules' => [
            'product' => ['enabled'=>1,'assignees'=>'','due_days'=>1,'priority'=>'normal','create_dispatch'=>1],
            'quotation' => ['enabled'=>1,'assignees'=>'','due_days'=>1,'priority'=>'high','create_dispatch'=>1],
            'technical-files' => ['enabled'=>1,'assignees'=>'','due_days'=>1,'priority'=>'normal','create_dispatch'=>1],
            'sample' => ['enabled'=>1,'assignees'=>'','due_days'=>2,'priority'=>'high','create_dispatch'=>1],
            'custom-project' => ['enabled'=>1,'assignees'=>'','due_days'=>2,'priority'=>'high','create_dispatch'=>1],
            'other' => ['enabled'=>1,'assignees'=>'','due_days'=>2,'priority'=>'normal','create_dispatch'=>1],
        ],
    ];
}

function web_inquiry_route_clip(mixed $value, int $length): string
{
    $text = trim((string)$value);
    return function_exists('mb_substr') ? mb_substr($text, 0, $length, 'UTF-8') : substr($text, 0, $length);
}

function web_inquiry_route_bool(mixed $value, bool $default = false): bool
{
    if (is_bool($value)) return $value;
    if ($value === null || $value === '') return $default;
    return in_array(strtolower(trim((string)$value)), ['1','true','yes','on'], true);
}

function web_inquiry_routing_normalize(array $config): array
{
    $defaults = web_inquiry_routing_defaults();
    $out = $defaults;
    foreach (['enabled','create_customer','create_contact','create_crm_inquiry','create_crm_task','create_dispatch'] as $key) {
        $out[$key] = web_inquiry_route_bool($config[$key] ?? $defaults[$key], (bool)$defaults[$key]) ? 1 : 0;
    }
    $out['owner'] = web_inquiry_route_clip($config['owner'] ?? $defaults['owner'], 120);
    $out['assignees'] = web_inquiry_route_clip($config['assignees'] ?? $defaults['assignees'], 500);
    $out['due_days'] = max(0, min(60, (int)($config['due_days'] ?? $defaults['due_days'])));
    $out['task_title_template'] = web_inquiry_route_clip($config['task_title_template'] ?? $defaults['task_title_template'], 500);
    $out['project_template'] = web_inquiry_route_clip($config['project_template'] ?? $defaults['project_template'], 500);
    $out['dispatch_content_template'] = web_inquiry_route_clip($config['dispatch_content_template'] ?? $defaults['dispatch_content_template'], 5000);
    $priority = strtolower(trim((string)($config['priority'] ?? $defaults['priority'])));
    $out['priority'] = in_array($priority, ['low','normal','high','urgent'], true) ? $priority : 'normal';

    $incomingRules = is_array($config['rules'] ?? null) ? $config['rules'] : [];
    foreach (web_inquiry_route_types() as $type => $label) {
        $base = $defaults['rules'][$type] ?? $defaults['rules']['other'];
        $row = is_array($incomingRules[$type] ?? null) ? $incomingRules[$type] : [];
        $p = strtolower(trim((string)($row['priority'] ?? $base['priority'])));
        $out['rules'][$type] = [
            'enabled' => web_inquiry_route_bool($row['enabled'] ?? $base['enabled'], true) ? 1 : 0,
            'assignees' => web_inquiry_route_clip($row['assignees'] ?? $base['assignees'], 500),
            'due_days' => max(0, min(60, (int)($row['due_days'] ?? $base['due_days']))),
            'priority' => in_array($p, ['low','normal','high','urgent'], true) ? $p : 'normal',
            'create_dispatch' => web_inquiry_route_bool($row['create_dispatch'] ?? $base['create_dispatch'], true) ? 1 : 0,
        ];
    }
    if ($out['owner'] === '') $out['owner'] = 'boss';
    if ($out['assignees'] === '') $out['assignees'] = $out['owner'];
    return $out;
}

function web_inquiry_routing_get(PDO $pdo): array
{
    $raw = (string)web_setting_get($pdo, 'inquiry_routing_rules', '');
    $decoded = $raw !== '' ? json_decode($raw, true) : null;
    return web_inquiry_routing_normalize(is_array($decoded) ? $decoded : []);
}

function web_inquiry_routing_save(PDO $pdo, array $config): array
{
    $clean = web_inquiry_routing_normalize($config);
    web_setting_set($pdo, 'inquiry_routing_rules', json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    return $clean;
}

function web_seed_inquiry_routing_settings(PDO $pdo): void
{
    $stmt = $pdo->prepare('INSERT IGNORE INTO web_system_settings (setting_key, setting_value, is_secret) VALUES (?, ?, 0)');
    $stmt->execute([
        'inquiry_routing_rules',
        json_encode(web_inquiry_routing_defaults(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ]);
}


function web_inquiry_base_project(array $record): string
{
    $candidates = [
        $record['product'] ?? '',
        $record['page_title'] ?? '',
        $record['message'] ?? '',
        $record['support_type'] ?? '',
        '官网询盘',
    ];

    foreach ($candidates as $candidate) {
        $text = trim((string)$candidate);
        if ($text === '') continue;
        $text = preg_replace('~https?://\S+~i', '', $text) ?? $text;
        $text = preg_replace('~^I\s+am\s+interested\s+in\s+~i', '', $text) ?? $text;
        $text = preg_split('~\R|Link\s*:~i', $text)[0] ?? $text;
        $text = trim(preg_replace('~\s+~u', ' ', $text) ?? $text);
        if ($text !== '') return web_inquiry_route_clip($text, 80);
    }

    return '官网询盘';
}

function web_inquiry_template_values(
    array $record,
    array $route,
    string $project = '',
    int $inquiryId = 0,
    string $sourceUrl = '',
    string $submittedAt = ''
): array {
    $types = web_inquiry_route_types();
    $supportType = (string)($route['route_support_type'] ?? $record['support_type'] ?? 'other');
    $project = trim($project) !== '' ? trim($project) : web_inquiry_base_project($record);
    return [
        '{name}' => trim((string)($record['name'] ?? '')),
        '{email}' => trim((string)($record['email'] ?? '')),
        '{phone}' => trim((string)(($record['phone'] ?? '') ?: ($record['whatsapp'] ?? ''))),
        '{company}' => trim((string)($record['company'] ?? '')),
        '{country}' => trim((string)($record['country'] ?? '')),
        '{support_type}' => $supportType,
        '{support_label}' => (string)($types[$supportType] ?? $supportType ?: '其他询盘'),
        '{product}' => trim((string)($record['product'] ?? '')),
        '{project}' => $project,
        '{page_type}' => trim((string)($record['page_type'] ?? '')),
        '{page_title}' => trim((string)($record['page_title'] ?? '')),
        '{message}' => trim((string)($record['message'] ?? '')),
        '{source}' => trim((string)($record['source'] ?? 'website')),
        '{page_url}' => trim((string)(($record['page_url'] ?? '') ?: ($record['product_link'] ?? ''))),
        '{product_link}' => trim((string)($record['product_link'] ?? '')),
        '{submitted_at}' => $submittedAt,
        '{website_url}' => $sourceUrl,
        '{inquiry_id}' => $inquiryId > 0 ? (string)$inquiryId : '',
    ];
}

function web_inquiry_render_template(string $template, array $values, int $limit, bool $singleLine = false): string
{
    $rendered = strtr($template, $values);
    $rendered = preg_replace('/\{[a-z0-9_]+\}/i', '', $rendered) ?? $rendered;
    if ($singleLine) {
        $rendered = preg_replace('~https?://\S+~i', '', $rendered) ?? $rendered;
        $rendered = trim(preg_replace('~\s+~u', ' ', $rendered) ?? $rendered);
    } else {
        $lines = preg_split('~\R~u', $rendered) ?: [];
        $rendered = implode("\n", array_values(array_filter(array_map(
            static fn(string $line): string => trim($line),
            $lines
        ), static fn(string $line): bool => $line !== '' && !preg_match('/^[^：:]+[：:]\s*$/u', $line))));
    }
    return web_inquiry_route_clip($rendered, $limit);
}

/**
 * 生成写入广州旧 CRM / 派工 project 字段的短项目名。
 * 广州旧表的 project 字段长度有限，不能放整段需求、链接和换行内容。
 */
function web_inquiry_short_project_name(array $record, array $route): string
{
    $base = web_inquiry_base_project($record);
    $template = trim((string)($route['route_project_template'] ?? '')) ?: '{project}';
    $project = web_inquiry_render_template($template, web_inquiry_template_values($record, $route, $base), 80, true);
    return $project !== '' ? $project : $base;
}

/**
 * 生成 CRM／派工统一任务标题。
 */
function web_inquiry_task_title(array $record, array $route, string $project): string
{
    $template = trim((string)($route['route_task_title_template'] ?? '')) ?: '官网询价｜{project}｜{name}';
    $title = web_inquiry_render_template($template, web_inquiry_template_values($record, $route, $project), 250, true);
    return $title !== '' ? $title : '官网询盘';
}

/**
 * 将标题之外的官网询盘资料整理成详情。
 * 注意：详情不能直接写入广州旧表 project 字段，project 字段只放短项目名。
 */
function web_inquiry_project_content(
    array $record,
    array $route,
    int $inquiryId,
    string $sourceUrl,
    string $submittedAt,
    string $project
): string {
    $fallback = web_inquiry_routing_defaults()['dispatch_content_template'];
    $template = trim((string)($route['route_dispatch_content_template'] ?? '')) ?: $fallback;
    return web_inquiry_render_template(
        $template,
        web_inquiry_template_values($record, $route, $project, $inquiryId, $sourceUrl, $submittedAt),
        600,
        false
    );
}

/**
 * 为新旧广州接收端同时提供兼容字段。
 * 关键修复：project / project_name / crm_project / dispatch_project 全部只给短值，
 * 避免广州旧表 project 字段报 Data too long。详情放到 description / note / inquiry_detail。
 */
function web_inquiry_enrich_sync_payload(
    array $payload,
    array $record,
    array $route,
    int $inquiryId,
    string $sourceUrl,
    string $submittedAt
): array {
    $project = web_inquiry_short_project_name($record, $route);
    $title = web_inquiry_task_title($record, $route, $project);
    $details = web_inquiry_project_content($record, $route, $inquiryId, $sourceUrl, $submittedAt, $project);

    return $payload + [
        'payload_schema_version' => 4,
        'title' => $title,
        'task_title' => $title,
        'crm_task_title' => $title,
        'dispatch_title' => $title,
        'dispatch_task_title' => $title,
        'project' => $project,
        'project_name' => $project,
        'project_content' => $project,
        'project_details' => $project,
        'crm_project' => $project,
        'dispatch_project' => $project,
        'description' => $details,
        'content' => $details,
        'note' => $details,
        'inquiry_detail' => $details,
        'full_inquiry_detail' => $details,
        'crm' => [
            'task_title' => $title,
            'project' => $project,
            'description' => $details,
        ],
        'dispatch' => [
            'task_title' => $title,
            'project' => $project,
            'description' => $details,
        ],
    ];
}

function web_inquiry_resolve_route(PDO $pdo, string $supportType): array
{
    $config = web_inquiry_routing_get($pdo);
    $type = array_key_exists($supportType, web_inquiry_route_types()) ? $supportType : 'other';
    $rule = $config['rules'][$type] ?? $config['rules']['other'];
    $assignees = trim((string)($rule['assignees'] ?? ''));
    if ($assignees === '') $assignees = trim((string)$config['assignees']);
    if ($assignees === '') $assignees = trim((string)$config['owner']);

    return [
        'route_enabled' => ((int)$config['enabled'] === 1 && (int)$rule['enabled'] === 1) ? 1 : 0,
        'route_owner' => (string)$config['owner'],
        'route_assignees' => $assignees,
        'route_due_days' => (int)($rule['due_days'] ?? $config['due_days']),
        'route_priority' => (string)($rule['priority'] ?? $config['priority']),
        'route_create_customer' => (int)$config['create_customer'],
        'route_create_contact' => (int)$config['create_contact'],
        'route_create_crm_inquiry' => (int)$config['create_crm_inquiry'],
        'route_create_crm_task' => (int)$config['create_crm_task'],
        'route_create_dispatch' => ((int)$config['create_dispatch'] === 1 && (int)$rule['create_dispatch'] === 1) ? 1 : 0,
        'route_support_type' => $type,
        'route_task_title_template' => (string)$config['task_title_template'],
        'route_project_template' => (string)$config['project_template'],
        'route_dispatch_content_template' => (string)$config['dispatch_content_template'],
    ];
}
