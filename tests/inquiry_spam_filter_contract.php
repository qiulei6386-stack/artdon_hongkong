<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/inquiry_spam.php';

$rules = [];
foreach (web_inquiry_spam_default_rules() as $i => $rule) {
    [$kind,$action,$scope,$pattern,$score,$label] = $rule;
    $rules[] = ['id'=>$i+1,'rule_kind'=>$kind,'rule_action'=>$action,'field_scope'=>$scope,'pattern'=>$pattern,'score'=>$score,'label'=>$label,'is_active'=>1];
}

$normal = web_inquiry_spam_evaluate_rules([
    'name'=>'John Smith','email'=>'john@example.com','company'=>'Lighting Studio','product'=>'ARMI Track Light',
    'message'=>'Please quote 50 track lights for our hotel project in Dubai.','page_title'=>'ARMI Track Light',
], $rules, 100);
if (!empty($normal['blocked'])) {
    fwrite(STDERR, "Normal lighting inquiry must not be blocked.\n");
    exit(1);
}

foreach ([
    'We offer professional SEO services and can improve your Google ranking.',
    '你好，我们提供抖音代运营服务。',
    'We can provide TikTok marketing for your website.',
] as $message) {
    $result = web_inquiry_spam_evaluate_rules(['email'=>'sales@example.com','message'=>$message], $rules, 100);
    if (empty($result['blocked'])) {
        fwrite(STDERR, "Expected advertising inquiry to be blocked: {$message}\n");
        exit(1);
    }
}

$custom = [
    ['id'=>100,'rule_kind'=>'domain','rule_action'=>'block','field_scope'=>'email','pattern'=>'agency.example','score'=>100,'is_active'=>1],
    ['id'=>101,'rule_kind'=>'email','rule_action'=>'allow','field_scope'=>'email','pattern'=>'buyer@agency.example','score'=>0,'is_active'=>1],
];
if (empty(web_inquiry_spam_evaluate_rules(['email'=>'spam@agency.example','message'=>'hello'], $custom, 100)['blocked'])) {
    fwrite(STDERR, "Blocked email domain must be rejected.\n");
    exit(1);
}
if (!empty(web_inquiry_spam_evaluate_rules(['email'=>'buyer@agency.example','message'=>'hello'], $custom, 100)['blocked'])) {
    fwrite(STDERR, "Exact email whitelist must override domain blacklist.\n");
    exit(1);
}

$submit = file_get_contents(dirname(__DIR__) . '/submit_inquiry.php');
$nav = file_get_contents(dirname(__DIR__) . '/admin/_layout.php');
if (!is_string($submit) || !is_string($nav)) exit(1);
$spamPos = strpos($submit, 'web_inquiry_spam_evaluate(');
$attachmentPos = strpos($submit, '$attachments = inquiry_save_attachments(');
if ($spamPos === false || $attachmentPos === false || $spamPos > $attachmentPos) {
    fwrite(STDERR, "Spam evaluation must happen before attachment saving.\n");
    exit(1);
}
foreach (["inquiry_respond('ok', \$returnUrl)", "web_inquiry_spam_record_block", "href'=>'inquiry_spam.php'"] as $needle) {
    if (!str_contains($submit . $nav, $needle)) {
        fwrite(STDERR, "Missing inquiry spam integration: {$needle}\n");
        exit(1);
    }
}

echo "inquiry spam filter contract passed\n";
