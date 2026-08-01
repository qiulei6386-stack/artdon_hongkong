<?php
/** Artdon Lighting Contact */
declare(strict_types=1);
require_once __DIR__ . '/includes/public_cache.php';
web_public_cache_start('contact', 300);

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/artdon_pages_v710.php';
require_once __DIR__ . '/includes/contact_page_data.php';

$content = artdon_v710_content();
$site = is_array($content['site'] ?? null) ? $content['site'] : (function_exists('web_get_block') ? (array)web_get_block('site') : []);
$inquiry = is_array($content['inquiry'] ?? null) ? $content['inquiry'] : [];
$contactPage = ['content'=>artdon_contact_default_content(),'seo_title'=>'Contact Artdon Lighting | Project Support & Quotations','seo_description'=>'Contact Artdon Lighting for commercial lighting quotations, custom luminaires, samples, IES files and technical project support.','seo_keywords'=>''];
try {
    $contactError = null;
    $contactPdo = web_db($contactError);
    if ($contactPdo) {
        web_migrate($contactPdo);
        $contactPage = artdon_contact_page($contactPdo);
    }
} catch (Throwable $ignored) {}
$contactContent = is_array($contactPage['content'] ?? null) ? $contactPage['content'] : artdon_contact_default_content();
$hero = is_array($contactContent['hero'] ?? null) ? $contactContent['hero'] : artdon_contact_default_content()['hero'];
$formSettings = is_array($contactContent['form'] ?? null) ? $contactContent['form'] : artdon_contact_default_content()['form'];
$contactInfo = is_array($contactContent['contact_info'] ?? null) ? $contactContent['contact_info'] : artdon_contact_default_content()['contact_info'];
$benefits = is_array($contactContent['benefits'] ?? null) ? $contactContent['benefits'] : artdon_contact_default_content()['benefits'];
$cta = is_array($contactContent['cta'] ?? null) ? $contactContent['cta'] : artdon_contact_default_content()['cta'];
$siteUrl = artdon_v710_site_url($site);
$company = trim((string)($site['company'] ?? 'Artdon Lighting Limited')) ?: 'Artdon Lighting Limited';
$email = trim((string)($site['email'] ?? 'sales@artdon.cn')) ?: 'sales@artdon.cn';
$telephone = trim((string)($site['telephone'] ?? '+86-760-22211886')) ?: '+86-760-22211886';
$mobile = trim((string)($site['mobile'] ?? '+86-13925332972 / +86-13751710245')) ?: '+86-13925332972 / +86-13751710245';
$heroImage = trim((string)($hero['image'] ?? 'assets/img/hero/hero-track-systems.webp')) ?: 'assets/img/hero/hero-track-systems.webp';
$product = artdon_v710_limit($_GET['product'] ?? '', 160);
$model = artdon_v710_limit($_GET['model'] ?? '', 100);
$subject = artdon_v710_limit($_GET['subject'] ?? '', 80);
$selectedProduct = trim($product . ($model !== '' ? ' - ' . $model : ''));
$pageTitle = trim((string)($contactPage['seo_title'] ?? '')) ?: 'Contact Artdon Lighting | Project Support & Quotations';
$pageDescription = trim((string)($contactPage['seo_description'] ?? '')) ?: 'Contact Artdon Lighting for commercial lighting quotations, custom luminaires, samples, IES files and technical project support.';
$canonical = $siteUrl . '/contact.php';
$ogImage = artdon_v710_absolute_url($siteUrl, $heroImage);
$supportValue = match($subject) {
    'technical-files','downloads','technical','request-documents' => 'technical',
    'sample','samples' => 'sample',
    'custom','custom-lighting' => 'custom',
    'product-selection' => 'product_selection',
    default => 'quotation',
};
$feedback = (string)($_GET['inquiry'] ?? '');
$feedbackText = match($feedback) {
    'ok' => (string)($formSettings['success_message'] ?? 'Thank you. Your inquiry has been received.'),
    'limit' => 'Submission limit reached. Please email us directly if your request is urgent.',
    'slow' => 'Please wait a moment before submitting another inquiry.',
    'invalid' => 'Please check the required fields, privacy consent and email address.',
    'file' => 'The uploaded file could not be accepted. Please upload PDF, DWG, JPG or PNG files up to 10MB.',
    'db' => 'The inquiry service is temporarily unavailable. Please contact us by email.',
    'error' => (string)($formSettings['error_message'] ?? 'The inquiry could not be submitted. Please try again or contact us by email.'),
    default => '',
};
$schema = [
    '@context'=>'https://schema.org','@graph'=>[
        ['@type'=>'ContactPage','@id'=>$canonical.'#page','url'=>$canonical,'name'=>$pageTitle,'description'=>$pageDescription,'inLanguage'=>'en'],
        ['@type'=>'Organization','@id'=>$siteUrl.'/#organization','name'=>$company,'url'=>$siteUrl.'/','email'=>$email,'telephone'=>$telephone,'contactPoint'=>[
            ['@type'=>'ContactPoint','telephone'=>'+86-13925332972','contactType'=>'sales','availableLanguage'=>['English','Chinese']],
            ['@type'=>'ContactPoint','telephone'=>'+86-13751710245','contactType'=>'sales','availableLanguage'=>['English','Chinese']],
        ],'address'=>['@type'=>'PostalAddress','streetAddress'=>'No. 15 Zhihe 3rd Street, Yumin Dongsheng, Xiaolan Town','addressLocality'=>'Zhongshan City','addressRegion'=>'Guangdong','postalCode'=>'528414','addressCountry'=>'CN']],
        artdon_v710_breadcrumb_schema($siteUrl,[['name'=>'Home','url'=>''],['name'=>'Contact','url'=>'contact.php']]),
    ],
];
function contact_clean_lines(string $text): string
{
    return nl2br(artdon_v710_e($text), false);
}
function contact_is_field_enabled(array $formSettings, string $key): bool
{
    foreach ((array)($formSettings['fields'] ?? []) as $field) {
        if (is_array($field) && (string)($field['key'] ?? '') === $key) return !empty($field['show']);
    }
    return true;
}
function contact_is_field_required(array $formSettings, string $key): bool
{
    foreach ((array)($formSettings['fields'] ?? []) as $field) {
        if (is_array($field) && (string)($field['key'] ?? '') === $key) return !empty($field['required']);
    }
    return in_array($key, ['name','email','country','subject','message','privacy'], true);
}
function contact_field_label(array $formSettings, string $key, string $fallback): string
{
    foreach ((array)($formSettings['fields'] ?? []) as $field) {
        if (is_array($field) && (string)($field['key'] ?? '') === $key) return trim((string)($field['label'] ?? '')) ?: $fallback;
    }
    return $fallback;
}
function contact_icon(string $type): string
{
    $base = 'width="28" height="28" viewBox="0 0 28 28" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true"';
    return match($type) {
        'mail' => '<svg '.$base.'><rect x="4" y="7" width="20" height="15" rx="1.5" stroke="currentColor" stroke-width="1.6"/><path d="m5 8 9 7 9-7" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'phone' => '<svg '.$base.'><path d="M9 5h3l1.4 5-2 1.2c1.2 2.5 3 4.4 5.4 5.5l1.3-2.1 4.9 1.5v3c0 1.1-.8 2-1.9 2.1C12.8 21.7 6.3 15.2 6.8 6.9 6.9 5.8 7.9 5 9 5Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/></svg>',
        'pin' => '<svg '.$base.'><path d="M22 12c0 6-8 12-8 12S6 18 6 12a8 8 0 1 1 16 0Z" stroke="currentColor" stroke-width="1.6"/><circle cx="14" cy="12" r="2.7" stroke="currentColor" stroke-width="1.6"/></svg>',
        'clock' => '<svg '.$base.'><circle cx="14" cy="14" r="9.5" stroke="currentColor" stroke-width="1.6"/><path d="M14 8.5V14l4 2.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'quality' => '<svg '.$base.'><path d="M14 4 23 8v6c0 5.4-3.7 8.7-9 10-5.3-1.3-9-4.6-9-10V8l9-4Z" stroke="currentColor" stroke-width="1.6"/><path d="m10 14 2.5 2.5L18.5 10" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        'team' => '<svg '.$base.'><circle cx="10" cy="10" r="3.5" stroke="currentColor" stroke-width="1.6"/><circle cx="19" cy="11" r="2.8" stroke="currentColor" stroke-width="1.6"/><path d="M4.5 22c.8-4 2.8-6 5.5-6s4.7 2 5.5 6M16 21c.6-2.5 1.8-3.8 3.6-3.8 1.7 0 3 1.3 3.6 3.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        'response' => '<svg '.$base.'><path d="M5 7h18v12H10l-5 4V7Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/><path d="M10 12h8M10 16h5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
        default => '<svg '.$base.'><path d="M14 3v22M4 14h20M7 7c4.3 3.2 9.7 3.2 14 0M7 21c4.3-3.2 9.7-3.2 14 0" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= artdon_v710_e($pageTitle) ?></title><meta name="description" content="<?= artdon_v710_e($pageDescription) ?>"><?php if (trim((string)($contactPage['seo_keywords'] ?? '')) !== ''): ?><meta name="keywords" content="<?= artdon_v710_e((string)$contactPage['seo_keywords']) ?>"><?php endif; ?><meta name="robots" content="index,follow,max-image-preview:large"><link rel="canonical" href="<?= artdon_v710_e($canonical) ?>">
<meta property="og:site_name" content="<?= artdon_v710_e($company) ?>"><meta property="og:type" content="website"><meta property="og:url" content="<?= artdon_v710_e($canonical) ?>"><meta property="og:title" content="<?= artdon_v710_e($pageTitle) ?>"><meta property="og:description" content="<?= artdon_v710_e($pageDescription) ?>"><meta property="og:image" content="<?= artdon_v710_e($ogImage) ?>">
<link rel="stylesheet" href="assets/css/artdon_home.css?v=6.12.10"><link rel="stylesheet" href="assets/css/artdon_component_safety.css?v=6.8.4"><link rel="stylesheet" href="assets/css/artdon_pages_v710.css?v=7.1.0">
<style>
.contact-page{background:#fff;color:#111;overflow:hidden}.contact-container{width:min(calc(100% - 56px),1280px);margin:0 auto}.contact-hero{width:100vw;margin-left:calc(50% - 50vw);margin-right:calc(50% - 50vw);background:linear-gradient(90deg,#fff 0%,#fff 52%,#f7f7f7 52%,#f7f7f7 100%);border-bottom:1px solid #e5e5e5}.contact-hero-inner{width:min(calc(100% - 56px),1280px);min-height:480px;margin:0 auto;display:grid;grid-template-columns:minmax(0,.92fr) minmax(440px,1.08fr);gap:54px;align-items:center;padding:42px 0}.contact-crumb{display:flex;gap:9px;margin:0 0 30px;color:#777;font-size:12px}.contact-crumb a{color:inherit;text-decoration:none}.contact-hero h1{margin:0;color:#111;font-size:clamp(54px,5vw,64px);line-height:1.08;font-weight:800;white-space:pre-line}.contact-dot{color:#d71920}.contact-hero p{max-width:520px;margin:22px 0 0;color:#555;font-size:16px;line-height:1.7}.contact-hero-media{height:360px;margin:0;border-radius:8px;overflow:hidden;background:#f7f7f7}.contact-hero-media img{width:100%;height:100%;display:block;object-fit:cover}.contact-main{padding:58px 0 42px}.contact-grid{display:grid;grid-template-columns:minmax(0,65fr) minmax(330px,35fr);gap:40px;align-items:start}.contact-form-card,.contact-info{border:1px solid #e5e5e5;border-radius:8px;background:#fff}.contact-form-card{padding:40px}.contact-form-card h2,.contact-info h2{margin:0;color:#111;font-size:30px;line-height:1.16;font-weight:760}.contact-form-card>p{margin:10px 0 28px;color:#555;font-size:15px;line-height:1.6}.contact-feedback{margin:0 0 20px;padding:12px 14px;border:1px solid #cce4d3;background:#f4fbf6;color:#21633a;font-size:13px}.contact-feedback.is-error{border-color:#edc9cf;background:#fff6f7;color:#a3152b}.contact-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.contact-field{display:flex;flex-direction:column;gap:7px}.contact-field-wide{grid-column:1/-1}.contact-field label{color:#222;font-size:12px;font-weight:800}.contact-field input,.contact-field select,.contact-field textarea{width:100%;border:1px solid #ddd;border-radius:2px;background:#fff;color:#111;font:inherit;font-size:14px;outline:0}.contact-field input,.contact-field select{height:46px;padding:0 14px}.contact-field textarea{height:110px;padding:14px;resize:vertical;line-height:1.6}.contact-field input:focus,.contact-field select:focus,.contact-field textarea:focus{border-color:#111}.contact-upload{position:relative;height:92px;border:1px dashed #cfcfcf;border-radius:4px;display:grid;place-items:center;text-align:center;color:#555;background:#fafafa;cursor:pointer}.contact-upload input{position:absolute;inset:0;opacity:0;cursor:pointer}.contact-upload strong{display:block;margin-bottom:4px;color:#111;font-size:13px}.contact-upload span{font-size:12px}.contact-consent{grid-column:1/-1;display:flex;gap:10px;align-items:flex-start;color:#555;font-size:13px;line-height:1.55}.contact-consent input{margin-top:3px}.contact-actions{grid-column:1/-1;display:flex;align-items:center;gap:18px}.contact-actions button{height:52px;border:0;background:#111;color:#fff;padding:0 28px;font:inherit;font-size:13px;font-weight:800;letter-spacing:1px;cursor:pointer}.contact-actions button:hover{background:#d71920}.contact-actions button[disabled]{opacity:.65;cursor:wait}.contact-actions small{color:#777;font-size:12px}.contact-info{padding:42px}.contact-info h2{font-size:26px;margin-bottom:22px}.contact-info-item{display:grid;grid-template-columns:34px minmax(0,1fr);gap:15px;padding:22px 0;border-top:1px solid #e5e5e5}.contact-info-item:first-of-type{border-top:0}.contact-info-icon{color:#111}.contact-info-item span{display:block;margin-bottom:6px;color:#777;font-size:11px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}.contact-info-item a,.contact-info-item strong{color:#111;text-decoration:none;font-size:14px;line-height:1.55;font-weight:650}.contact-info-item p{margin:0;color:#111;font-size:14px;line-height:1.55;font-weight:650}.contact-benefits{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));margin:0 0 72px;border:1px solid #e5e5e5;border-radius:8px;background:#fff}.contact-benefit{padding:32px;border-left:1px solid #e5e5e5}.contact-benefit:first-child{border-left:0}.contact-benefit-icon{color:#111;margin-bottom:18px}.contact-benefit h3{margin:0 0 9px;color:#111;font-size:17px;font-weight:760}.contact-benefit p{margin:0;color:#555;font-size:13px;line-height:1.55}.contact-bottom-cta{width:100vw;margin:0 calc(50% - 50vw);background:#111;background-size:cover;background-position:center}.contact-bottom-cta-inner{width:min(calc(100% - 56px),1280px);min-height:190px;margin:0 auto;display:flex;align-items:center;justify-content:space-between;gap:32px;color:#fff}.contact-bottom-cta h2{margin:0;font-size:34px;line-height:1.16}.contact-bottom-cta p{margin:10px 0 0;color:rgba(255,255,255,.84);font-size:15px;line-height:1.6}.contact-bottom-cta a{display:inline-flex;align-items:center;justify-content:center;height:52px;padding:0 32px;border-radius:4px;background:#d71920;color:#fff;text-decoration:none;font-size:13px;font-weight:800;letter-spacing:1px}.contact-bottom-cta a:hover{background:#b9141b}@media(max-width:980px){.contact-hero{background:#fff}.contact-hero-inner{grid-template-columns:1fr;gap:28px}.contact-hero-media{height:300px}.contact-grid{grid-template-columns:1fr}.contact-benefits{grid-template-columns:repeat(2,minmax(0,1fr))}.contact-benefit:nth-child(odd){border-left:0}.contact-benefit:nth-child(n+3){border-top:1px solid #e5e5e5}.contact-bottom-cta-inner{display:grid;align-content:center;padding:34px 0}}@media(max-width:640px){.contact-container,.contact-hero-inner,.contact-bottom-cta-inner{width:calc(100% - 32px)}.contact-hero-inner{min-height:auto;padding:34px 0}.contact-hero h1{font-size:42px}.contact-hero-media{height:240px}.contact-main{padding:42px 0 30px}.contact-form-card,.contact-info{padding:24px}.contact-form{grid-template-columns:1fr}.contact-field-wide,.contact-consent,.contact-actions{grid-column:auto}.contact-actions{display:block}.contact-actions button{width:100%}.contact-actions small{display:block;margin-top:12px}.contact-benefits{grid-template-columns:1fr}.contact-benefit{border-left:0!important;border-top:1px solid #e5e5e5}.contact-benefit:first-child{border-top:0}.contact-bottom-cta a{width:100%}}
</style>
<script type="application/ld+json"><?= artdon_v710_json($schema) ?></script>
</head>
<body>
<?php include __DIR__ . '/partials/header.php'; ?>
<main class="contact-page">
  <section class="contact-hero" aria-labelledby="contact-title">
    <div class="contact-hero-inner">
      <div>
        <?php $crumbLabel = trim((string)($hero['breadcrumb'] ?? 'Contact')); $crumbLabel = preg_replace('/^Home\s*>\s*/i', '', $crumbLabel) ?: 'Contact'; ?>
        <nav class="contact-crumb" aria-label="Breadcrumb"><a href="index.php">Home</a><span>&gt;</span><strong><?= artdon_v710_e($crumbLabel) ?></strong></nav>
        <h1 id="contact-title"><?= artdon_v710_e((string)($hero['title'] ?? "Talk to Our\nLighting Team.")) ?></h1>
        <p><?= artdon_v710_e((string)($hero['description'] ?? 'Tell us about your project and our team will get back to you within one working day.')) ?></p>
      </div>
      <figure class="contact-hero-media"><img src="<?= artdon_v710_e($heroImage) ?>" alt="<?= artdon_v710_e((string)($hero['image_alt'] ?? 'Contact Artdon Lighting')) ?>"></figure>
    </div>
  </section>

  <section class="contact-container contact-main">
    <div class="contact-grid">
      <article class="contact-form-card">
        <h2><?= artdon_v710_e((string)($formSettings['title'] ?? 'Send Us a Message')) ?></h2>
        <p><?= artdon_v710_e((string)($formSettings['description'] ?? "Please provide the details below and we'll get back to you as soon as possible.")) ?></p>
        <?php if($feedbackText !== ''): ?><p class="contact-feedback <?= $feedback === 'ok' ? '' : 'is-error' ?>"><?= artdon_v710_e($feedbackText) ?></p><?php endif; ?>
        <p class="contact-feedback" data-contact-feedback hidden></p>
        <form class="contact-form" action="submit_inquiry.php" method="post" enctype="multipart/form-data" data-contact-form data-max-mb="<?= (int)($formSettings['upload_max_mb'] ?? 10) ?>" data-types="<?= artdon_v710_e((string)($formSettings['allowed_file_types'] ?? 'PDF,DWG,JPG,PNG')) ?>">
          <input type="hidden" name="source" value="contact_page">
          <input type="hidden" name="return_url" value="contact.php">
          <input type="hidden" name="product_link" value="<?= artdon_v710_e($_SERVER['REQUEST_URI'] ?? 'contact.php') ?>">
          <input type="hidden" name="page_type" value="contact">
          <input type="hidden" name="page_title" value="Contact">
          <input type="hidden" name="visitor_id" value="<?= artdon_v710_e((string)($_COOKIE['visitor_id'] ?? ($_COOKIE['_ga'] ?? ''))) ?>">
          <input type="text" name="website" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
          <?php if(contact_is_field_enabled($formSettings, 'name')): ?><div class="contact-field"><label for="contactName"><?= artdon_v710_e(contact_field_label($formSettings, 'name', 'Name')) ?><?= contact_is_field_required($formSettings, 'name') ? ' *' : '' ?></label><input id="contactName" name="name" autocomplete="name" <?= contact_is_field_required($formSettings, 'name') ? 'required' : '' ?>></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'email')): ?><div class="contact-field"><label for="contactEmail"><?= artdon_v710_e(contact_field_label($formSettings, 'email', 'Work Email')) ?><?= contact_is_field_required($formSettings, 'email') ? ' *' : '' ?></label><input id="contactEmail" name="email" type="email" autocomplete="email" <?= contact_is_field_required($formSettings, 'email') ? 'required' : '' ?>></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'company')): ?><div class="contact-field"><label for="contactCompany"><?= artdon_v710_e(contact_field_label($formSettings, 'company', 'Company')) ?><?= contact_is_field_required($formSettings, 'company') ? ' *' : '' ?></label><input id="contactCompany" name="company" autocomplete="organization" <?= contact_is_field_required($formSettings, 'company') ? 'required' : '' ?>></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'country')): ?><div class="contact-field"><label for="contactCountry"><?= artdon_v710_e(contact_field_label($formSettings, 'country', 'Country / Region')) ?><?= contact_is_field_required($formSettings, 'country') ? ' *' : '' ?></label><input id="contactCountry" name="country" autocomplete="country-name" <?= contact_is_field_required($formSettings, 'country') ? 'required' : '' ?>></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'subject')): ?><div class="contact-field contact-field-wide"><label for="contactSubject"><?= artdon_v710_e(contact_field_label($formSettings, 'subject', 'Subject')) ?><?= contact_is_field_required($formSettings, 'subject') ? ' *' : '' ?></label><select id="contactSubject" name="support_type" <?= contact_is_field_required($formSettings, 'subject') ? 'required' : '' ?>><?php foreach(['quotation'=>'Quotation','product_selection'=>'Product selection','technical'=>'Technical files / support','sample'=>'Samples','custom'=>'Custom lighting development','other'=>'Other inquiry'] as $value=>$label): ?><option value="<?= artdon_v710_e($value) ?>" <?= $supportValue===$value?'selected':'' ?>><?= artdon_v710_e($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <?php if($selectedProduct !== ''): ?><input type="hidden" name="product" value="<?= artdon_v710_e($selectedProduct) ?>"><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'message')): ?><div class="contact-field contact-field-wide"><label for="contactMessage"><?= artdon_v710_e(contact_field_label($formSettings, 'message', 'Project Requirements')) ?><?= contact_is_field_required($formSettings, 'message') ? ' *' : '' ?></label><textarea id="contactMessage" name="message" <?= contact_is_field_required($formSettings, 'message') ? 'required' : '' ?> placeholder="Application, dimensions, quantity, power, CCT, CRI, beam angle, finish, dimming and delivery destination"></textarea></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'upload')): ?><div class="contact-field contact-field-wide"><label><?= artdon_v710_e(contact_field_label($formSettings, 'upload', 'Upload Files Optional')) ?></label><label class="contact-upload"><input type="file" name="attachments[]" accept=".pdf,.dwg,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" multiple><span><strong>Click to upload or drag and drop</strong><?= artdon_v710_e(str_replace(',', ', ', (string)($formSettings['allowed_file_types'] ?? 'PDF,DWG,JPG,PNG'))) ?> (Max. <?= (int)($formSettings['upload_max_mb'] ?? 10) ?>MB)</span></label></div><?php endif; ?>
          <?php if(contact_is_field_enabled($formSettings, 'privacy')): ?><label class="contact-consent"><input type="checkbox" name="privacy_consent" value="1" <?= contact_is_field_required($formSettings, 'privacy') ? 'required' : '' ?>><span>I agree to the Privacy Policy and allow Artdon Lighting to use this information to respond to my inquiry.</span></label><?php endif; ?>
          <div class="contact-actions"><button type="submit"><?= artdon_v710_e((string)($formSettings['button_text'] ?? 'SEND MESSAGE →')) ?></button><small><?= artdon_v710_e($inquiry['response_note'] ?? 'Our team will reply within one working day.') ?></small></div>
        </form>
      </article>

      <aside class="contact-info">
        <h2><?= artdon_v710_e((string)($contactInfo['title'] ?? 'Contact Information')) ?></h2>
        <?php foreach (artdon_contact_sort_items((array)($contactInfo['items'] ?? [])) as $item): $itemUrl = trim((string)($item['url'] ?? '')); ?>
        <div class="contact-info-item"><div class="contact-info-icon"><?= contact_icon((string)($item['icon'] ?? 'mail')) ?></div><div><span><?= artdon_v710_e((string)($item['title'] ?? '')) ?></span><?php if($itemUrl !== ''): ?><a href="<?= artdon_v710_e($itemUrl) ?>" <?= preg_match('#^https?://#', $itemUrl) ? 'target="_blank" rel="noopener"' : '' ?>><?= contact_clean_lines((string)($item['text'] ?? '')) ?></a><?php else: ?><p><?= contact_clean_lines((string)($item['text'] ?? '')) ?></p><?php endif; ?></div></div>
        <?php endforeach; ?>
      </aside>
    </div>
  </section>

  <section class="contact-container">
    <?php $benefitItems = artdon_contact_sort_items((array)($benefits['items'] ?? [])); if (!empty($benefits['is_active']) && $benefitItems): ?>
    <div class="contact-benefits">
      <?php foreach ($benefitItems as $item): ?>
      <article class="contact-benefit"><div class="contact-benefit-icon"><?= contact_icon((string)($item['icon'] ?? 'quality')) ?></div><h3><?= artdon_v710_e((string)($item['title'] ?? '')) ?></h3><p><?= artdon_v710_e((string)($item['text'] ?? '')) ?></p></article>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </section>
  <?php if (!empty($cta['is_active']) && trim((string)($cta['title'] ?? '')) !== ''): $ctaImage = trim((string)($cta['image'] ?? '')); $ctaUrl = trim((string)($cta['button_url'] ?? 'contact.php')) ?: 'contact.php'; ?>
  <section class="contact-bottom-cta" style="<?= $ctaImage !== '' ? "background-image:linear-gradient(90deg,rgba(0,0,0,.82),rgba(0,0,0,.50)),url('".artdon_v710_e($ctaImage)."')" : '' ?>">
    <div class="contact-bottom-cta-inner"><div><h2><?= artdon_v710_e((string)$cta['title']) ?></h2><p><?= contact_clean_lines((string)($cta['description'] ?? '')) ?></p></div><?php if(trim((string)($cta['button_text'] ?? '')) !== ''): ?><a href="<?= artdon_v710_e($ctaUrl) ?>"><?= artdon_v710_e((string)$cta['button_text']) ?></a><?php endif; ?></div>
  </section>
  <?php endif; ?>
</main>
<?php include __DIR__ . '/partials/footer.php'; ?>
<script src="assets/js/artdon_home.js?v=6.12.13" defer></script>
<script>
(function(){
  var form=document.querySelector('[data-contact-form]');
  if(!form || !window.FormData || !window.fetch) return;
  var feedback=document.querySelector('[data-contact-feedback]');
  var btn=form.querySelector('button[type="submit"]');
  function show(message, ok){ if(!feedback)return; feedback.hidden=false; feedback.textContent=message; feedback.classList.toggle('is-error', !ok); }
  function allowedExts(){ return String(form.getAttribute('data-types')||'PDF,DWG,JPG,PNG').toLowerCase().split(/[,/ ]+/).filter(Boolean).map(function(v){return v.replace(/^\./,'');}); }
  function validateFiles(){
    var input=form.querySelector('input[type="file"]'); if(!input || !input.files || !input.files.length) return '';
    var maxMb=parseInt(form.getAttribute('data-max-mb')||'10',10), max=maxMb*1024*1024, allowed=allowedExts();
    for(var i=0;i<input.files.length;i++){ var f=input.files[i], ext=(f.name.split('.').pop()||'').toLowerCase(); if(f.size>max)return 'File size cannot exceed '+maxMb+'MB.'; if(allowed.indexOf(ext)<0)return 'This file type is not allowed.'; }
    return '';
  }
  form.addEventListener('submit',function(e){
    if(!form.checkValidity()) return;
    var fileError=validateFiles(); if(fileError){ e.preventDefault(); show(fileError,false); return; }
    e.preventDefault();
    var old=btn?btn.textContent:'';
    if(btn){btn.disabled=true;btn.textContent='Submitting...';}
    fetch(form.action,{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}}).then(function(r){return r.json().then(function(j){return {ok:r.ok,json:j};});}).then(function(res){
      show(res.json.message || (res.ok ? 'Thank you. Your inquiry has been received.' : 'Submit failed.'), !!res.ok);
      if(res.ok) form.reset();
    }).catch(function(){show('The inquiry could not be submitted. Please try again or contact us by email.',false);}).finally(function(){ if(btn){btn.disabled=false;btn.textContent=old;} });
  });
})();
</script>
</body>
</html>
