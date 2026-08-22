<?php
/**
 * Artdon Website V7.1.8.109
 * Floating action and inquiry modal conversion polish.
 * Uses backend company name from site settings.
 * Adds CRM + dispatch connection notice and fastest one-hour response message.
 */
if (!function_exists('web_e')) {
    function web_e(mixed $value): string { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
}

$__faSite = [];
if (function_exists('web_get_block')) {
    $tmp = web_get_block('site');
    if (is_array($tmp)) $__faSite = $tmp;
}
$__faDefaults = function_exists('web_default_content') ? (array)(web_default_content()['site'] ?? []) : [];
$__faText = static function (string $key, string $fallback) use ($__faSite, $__faDefaults): string {
    $value = trim((string)($__faSite[$key] ?? $__faDefaults[$key] ?? $fallback));
    return $value !== '' ? $value : $fallback;
};
$__faCompany = trim((string)($__company ?? ($__faSite['company'] ?? '')));
if ($__faCompany === '') $__faCompany = 'Artdon Lighting Limited';
$__faEmail = trim((string)($__email ?? ($__faSite['email'] ?? ''))) ?: 'sales@artdon.cn';
$__faWhatsApp = preg_replace('/\D+/', '', (string)($__faSite['whatsapp'] ?? $__faDefaults['whatsapp'] ?? '8613925332972'));
$__faWhatsAppHref = $__faWhatsApp !== '' ? ('https://wa.me/' . $__faWhatsApp) : '';
$__faContext = [
    'page_type' => 'page',
    'title' => trim((string)($pageTitle ?? '')),
    'product' => '',
    'series' => '',
    'model' => '',
    'size' => '',
];

if (isset($variant) && is_array($variant)) {
    $__faContext['page_type'] = 'product';
    $__faContext['title'] = trim((string)($variant['name'] ?? ''));
    $__faContext['product'] = trim((string)($variant['name'] ?? ''));
    $__faContext['model'] = trim((string)($variant['model_code'] ?? ''));
    $__faContext['size'] = trim((string)(($variant['size_name'] ?? '') ?: ($variant['dimensions'] ?? '')));
}

if (isset($series) && is_array($series)) {
    if ($__faContext['page_type'] === 'page') {
        $__faContext['page_type'] = 'series';
    }
    $__faContext['series'] = trim((string)(($series['series_name'] ?? '') ?: ($series['name'] ?? '')));
    if ($__faContext['title'] === '') $__faContext['title'] = $__faContext['series'];
    if ($__faContext['product'] === '') $__faContext['product'] = $__faContext['series'];
}
?>
<style>
/* V7.1.8.108: floating action visual refinement.
   Default state is smaller + semi-transparent. Hover/focus returns to normal size and full opacity. */
.artdon-float-v71871{
  position:fixed;
  right:0;
  top:50%;
  transform:translateY(-50%) scale(.82);
  transform-origin:right center;
  z-index:9990;
  display:grid;
  background:rgba(17,17,17,.56);
  -webkit-backdrop-filter:blur(14px);
  backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.10);
  border-right:0;
  border-radius:24px 0 0 24px;
  overflow:hidden;
  opacity:.62;
  box-shadow:0 20px 54px rgba(0,0,0,.18);
  transition:transform .22s ease, opacity .22s ease, background .22s ease, box-shadow .22s ease;
}
.artdon-float-v71871:hover,
.artdon-float-v71871:focus-within{
  transform:translateY(-50%) scale(1);
  opacity:1;
  background:rgba(17,17,17,.92);
  box-shadow:0 28px 70px rgba(0,0,0,.26);
}
.artdon-float-v71871 button,
.artdon-float-v71871 a{
  width:62px;
  height:62px;
  border:0;
  border-bottom:1px solid rgba(255,255,255,.14);
  background:transparent;
  color:#fff;
  display:grid;
  place-items:center;
  cursor:pointer;
  text-decoration:none;
  position:relative;
  padding:0;
  transition:background .18s ease, color .18s ease, transform .18s ease;
}
.artdon-float-v71871 button:last-child,
.artdon-float-v71871 a:last-child{border-bottom:0}
.artdon-float-v71871 button:hover,
.artdon-float-v71871 a:hover,
.artdon-float-v71871 button:focus{
  background:#fff;
  color:#111;
  outline:0;
}
.artdon-float-v71871 svg{width:28px;height:28px;display:block;stroke-width:1.9}
.artdon-float-v71871 .label{
  position:absolute;
  right:70px;
  top:50%;
  transform:translateY(-50%);
  background:rgba(17,17,17,.94);
  color:#fff;
  border:1px solid rgba(255,255,255,.16);
  border-radius:999px;
  padding:8px 12px;
  font-size:12px;
  font-weight:800;
  white-space:nowrap;
  box-shadow:0 12px 32px rgba(0,0,0,.20);
  opacity:0;
  pointer-events:none;
  transition:.16s;
}
.artdon-float-v71871 button:hover .label,
.artdon-float-v71871 a:hover .label{opacity:1;right:76px}

.artdon-copy-toast-v71871{
  position:fixed;
  right:18px;
  bottom:20px;
  z-index:10030;
  background:#111;
  color:#fff;
  border-radius:999px;
  padding:10px 14px;
  font-size:13px;
  font-weight:900;
  box-shadow:0 15px 44px rgba(0,0,0,.22);
  opacity:0;
  transform:translateY(10px);
  pointer-events:none;
  transition:.18s;
}
.artdon-copy-toast-v71871.show{opacity:1;transform:translateY(0)}

/* V7.1.8.108: premium enquiry modal, cleaner for overseas customers. */
.artdon-fi-modal-v71871[hidden]{display:none!important}
.artdon-fi-modal-v71871{
  position:fixed;
  inset:0;
  z-index:10020;
  display:grid;
  place-items:center;
  padding:28px;
}
.artdon-fi-backdrop-v71871{
  position:absolute;
  inset:0;
  background:rgba(8,10,13,.54);
  -webkit-backdrop-filter:blur(14px) saturate(1.05);
  backdrop-filter:blur(14px) saturate(1.05);
}
.artdon-fi-panel-v71871{
  position:relative;
  width:min(760px,100%);
  max-height:min(780px,92vh);
  overflow:auto;
  background:rgba(255,255,255,.98);
  border:1px solid rgba(17,24,39,.12);
  border-radius:30px;
  box-shadow:0 34px 110px rgba(0,0,0,.34);
  padding:34px 36px 32px;
}
.artdon-fi-panel-v71871:before{
  content:"";
  position:absolute;
  left:0;
  top:0;
  right:0;
  height:5px;
  background:linear-gradient(90deg,#e31b23 0%,#111 34%,rgba(17,17,17,.08) 100%);
  border-radius:30px 30px 0 0;
}
.artdon-fi-head-v71871{
  display:flex;
  align-items:flex-start;
  justify-content:space-between;
  gap:22px;
  margin-bottom:24px;
}
.artdon-fi-head-v71871 h2{
  margin:0;
  font-size:38px;
  line-height:1.02;
  font-weight:950;
  letter-spacing:-.045em;
  color:#111;
}
.artdon-fi-head-v71871 p{
  display:inline-flex;
  align-items:center;
  margin:12px 0 0;
  color:#5f6673;
  line-height:1.45;
  font-size:16px;
  border:1px solid rgba(17,24,39,.10);
  background:#f7f8fb;
  border-radius:999px;
  padding:8px 13px;
}
.artdon-fi-close-v71871{
  flex:0 0 auto;
  width:48px;
  height:48px;
  border:1px solid #d9dee7;
  background:#fff;
  color:#111;
  border-radius:999px;
  display:grid;
  place-items:center;
  font-size:28px;
  line-height:1;
  cursor:pointer;
  box-shadow:0 8px 24px rgba(0,0,0,.06);
  transition:background .16s ease, transform .16s ease, border-color .16s ease;
}
.artdon-fi-close-v71871:hover{background:#111;color:#fff;border-color:#111;transform:rotate(90deg)}
.artdon-fi-grid-v71871{display:grid;grid-template-columns:1fr 1fr;gap:18px 18px}
.artdon-fi-field-v71871{display:grid;gap:8px}
.artdon-fi-field-v71871.full{grid-column:1/-1}
.artdon-fi-field-v71871 label{
  font-size:12px;
  font-weight:900;
  color:#4b5563;
  text-transform:uppercase;
  letter-spacing:.13em;
}
.artdon-fi-field-v71871 input,
.artdon-fi-field-v71871 textarea{
  width:100%;
  box-sizing:border-box;
  border:1px solid #d9dfeb;
  border-radius:16px;
  padding:15px 16px;
  font:inherit;
  font-size:16px;
  outline:none;
  background:#fff;
  box-shadow:0 1px 0 rgba(17,24,39,.02);
  transition:border-color .16s ease, box-shadow .16s ease, background .16s ease;
}
.artdon-fi-field-v71871 input{height:56px}
.artdon-fi-field-v71871 textarea{min-height:150px;resize:vertical;line-height:1.55}
.artdon-fi-field-v71871 input:focus,
.artdon-fi-field-v71871 textarea:focus{
  border-color:#111;
  background:#fff;
  box-shadow:0 0 0 4px rgba(17,17,17,.08);
}
.artdon-fi-actions-v71871{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:16px;
  margin-top:22px;
}
.artdon-fi-submit-v71871{
  border:0;
  background:#111;
  color:#fff;
  border-radius:999px;
  padding:16px 28px;
  min-width:230px;
  font-weight:950;
  letter-spacing:.10em;
  text-transform:uppercase;
  cursor:pointer;
  box-shadow:0 18px 42px rgba(0,0,0,.20);
  transition:transform .16s ease, background .16s ease, box-shadow .16s ease;
}
.artdon-fi-submit-v71871:hover{background:#e31b23;transform:translateY(-1px);box-shadow:0 20px 48px rgba(227,27,35,.24)}
.artdon-fi-submit-v71871:disabled{opacity:.55;cursor:not-allowed;transform:none;background:#111}
.artdon-fi-cancel-v71871{
  border:0;
  background:transparent;
  color:#5f6673;
  font-weight:900;
  cursor:pointer;
  padding:12px 0;
}
.artdon-fi-cancel-v71871:hover{color:#111}
.artdon-fi-status-v71871{margin-top:14px;font-size:14px;line-height:1.5;color:#444}.artdon-fi-status-v71871.ok{color:#0f7a3c}.artdon-fi-status-v71871.err{color:#b42318}.artdon-fi-hp-v71871{position:absolute!important;left:-9999px!important;opacity:0!important}
@media(max-width:760px){
  .artdon-float-v71871{left:14px;right:14px;top:auto;bottom:14px;transform:none;grid-template-columns:repeat(3,1fr);border-radius:20px;background:rgba(17,17,17,.78);opacity:.72;border:1px solid rgba(255,255,255,.10)}
  .artdon-float-v71871:hover,.artdon-float-v71871:focus-within{transform:none;opacity:1;background:rgba(17,17,17,.94)}
  .artdon-float-v71871 button,.artdon-float-v71871 a{width:100%;height:54px;border-bottom:0;border-right:1px solid rgba(255,255,255,.12)}
  .artdon-float-v71871 button:last-child,.artdon-float-v71871 a:last-child{border-right:0}.artdon-float-v71871 svg{width:24px;height:24px}.artdon-float-v71871 .label{display:none}body{padding-bottom:78px}
  .artdon-fi-modal-v71871{padding:14px}.artdon-fi-panel-v71871{padding:26px 18px 20px;border-radius:22px}.artdon-fi-head-v71871 h2{font-size:30px}.artdon-fi-head-v71871 p{font-size:14px;border-radius:14px}.artdon-fi-close-v71871{width:42px;height:42px}.artdon-fi-grid-v71871{grid-template-columns:1fr;gap:14px}.artdon-fi-actions-v71871{align-items:stretch;flex-direction:column-reverse}.artdon-fi-submit-v71871{width:100%;min-width:0}.artdon-fi-cancel-v71871{text-align:center}
}

/* V7.1.8.109: cleaner overseas enquiry card. Company name comes from backend Site Settings. */
.artdon-fi-panel-v71871{
  width:min(840px,100%)!important;
  max-height:min(720px,92vh)!important;
  padding:0!important;
  overflow:hidden!important;
  border-radius:26px!important;
  background:#fff!important;
  box-shadow:0 34px 100px rgba(0,0,0,.34)!important;
}
.artdon-fi-panel-v71871:before{display:none!important}
.artdon-fi-shell-v718109{display:grid;grid-template-columns:280px minmax(0,1fr);min-height:560px}
.artdon-fi-aside-v718109{
  background:linear-gradient(180deg,#111 0%,#1a1a1a 62%,#2b0d0f 100%);
  color:#fff;
  padding:34px 28px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  position:relative;
  overflow:hidden;
}
.artdon-fi-aside-v718109:after{
  content:"";
  position:absolute;
  width:180px;height:180px;right:-78px;bottom:-58px;
  border-radius:50%;background:rgba(227,27,35,.24);filter:blur(2px)
}
.artdon-fi-brand-v718109{position:relative;z-index:1}
.artdon-fi-company-v718109{
  font-size:22px;line-height:1.08;font-weight:950;letter-spacing:-.04em;margin:0 0 10px;color:#fff
}
.artdon-fi-kicker-v718109{font-size:11px;font-weight:900;letter-spacing:.22em;text-transform:uppercase;color:#ff4b52;margin-bottom:28px}
.artdon-fi-aside-v718109 h3{font-size:28px;line-height:1.02;letter-spacing:-.045em;margin:0 0 16px;font-weight:950;color:#fff}
.artdon-fi-aside-v718109 p{margin:0;color:rgba(255,255,255,.76);font-size:14px;line-height:1.58}
.artdon-fi-trust-v718109{position:relative;z-index:1;display:grid;gap:12px;margin-top:26px}
.artdon-fi-trust-item-v718109{display:flex;gap:10px;align-items:flex-start;font-size:13px;line-height:1.35;color:rgba(255,255,255,.88)}
.artdon-fi-trust-dot-v718109{width:8px;height:8px;border-radius:50%;background:#e31b23;flex:0 0 auto;margin-top:5px;box-shadow:0 0 0 5px rgba(227,27,35,.16)}
.artdon-fi-main-v718109{padding:34px 36px 30px;position:relative;overflow:auto;max-height:min(720px,92vh)}
.artdon-fi-head-v71871{margin-bottom:20px!important;align-items:flex-start!important}
.artdon-fi-head-v71871 h2{font-size:32px!important;letter-spacing:-.045em!important;margin:0!important}
.artdon-fi-head-v71871 p{display:block!important;border:0!important;background:transparent!important;border-radius:0!important;padding:0!important;margin:10px 0 0!important;color:#626a76!important;font-size:15px!important}
.artdon-fi-close-v71871{width:44px!important;height:44px!important;font-size:26px!important;box-shadow:none!important}
.artdon-fi-mini-notice-v718109{
  display:flex;align-items:center;gap:10px;
  padding:11px 13px;margin:0 0 20px;
  border:1px solid rgba(227,27,35,.16);
  background:#fff7f7;border-radius:14px;color:#222;font-size:13px;line-height:1.45
}
.artdon-fi-mini-notice-v718109 strong{font-weight:900;color:#111}.artdon-fi-mini-notice-v718109 span{color:#666}
.artdon-float-v71871 [data-af-whatsapp]{display:none}
.artdon-fi-mobile-label-v718192{display:none}
.artdon-fi-grid-v71871{gap:14px 16px!important}
.artdon-fi-field-v71871{gap:7px!important}
.artdon-fi-field-v71871 label{font-size:11px!important;letter-spacing:.16em!important;color:#3f4652!important}
.artdon-fi-field-v71871 input,.artdon-fi-field-v71871 textarea{
  border-radius:13px!important;padding:13px 14px!important;border-color:#d7dde7!important;background:#fff!important;font-size:15px!important
}
.artdon-fi-field-v71871 input{height:50px!important}.artdon-fi-field-v71871 textarea{min-height:126px!important}
.artdon-fi-actions-v71871{margin-top:18px!important}.artdon-fi-submit-v71871{min-width:210px!important;padding:15px 24px!important}.artdon-fi-cancel-v71871{font-size:15px!important}
@media(max-width:820px){
  .artdon-float-v71871 [data-af-copy]{display:none!important}
  .artdon-float-v71871 [data-af-whatsapp]{display:grid!important}
  .artdon-fi-shell-v718109{grid-template-columns:1fr;min-height:0}.artdon-fi-aside-v718109{padding:22px 20px}.artdon-fi-aside-v718109 h3{font-size:24px}.artdon-fi-trust-v718109{grid-template-columns:1fr;gap:8px}.artdon-fi-main-v718109{padding:24px 18px 20px}.artdon-fi-panel-v71871{overflow:auto!important}.artdon-fi-grid-v71871{grid-template-columns:1fr!important}.artdon-fi-head-v71871 h2{font-size:28px!important}
  .artdon-fi-field-v71871.is-mobile-optional-v718192{display:none!important}
  .artdon-fi-desktop-label-v718192{display:none!important}
  .artdon-fi-mobile-label-v718192{display:inline!important}
  .artdon-fi-field-v71871 input{height:48px!important}
  .artdon-fi-field-v71871 textarea{min-height:112px!important}
  .artdon-fi-mini-notice-v718109{margin-bottom:14px!important}
}

</style>
<nav class="artdon-float-v71871" data-artdon-float-actions-v71871 data-context='<?= web_e(json_encode($__faContext, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>' data-email="<?= web_e($__faEmail) ?>" data-company="<?= web_e($__faCompany) ?>" aria-label="Quick actions">
  <button type="button" data-af-inquiry aria-label="Send inquiry"><span class="label">Inquiry</span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 7h10M7 11h7M6.5 18.5 3 21V5.8C3 4.25 4.25 3 5.8 3h12.4C19.75 3 21 4.25 21 5.8v8.4c0 1.55-1.25 2.8-2.8 2.8H9.2l-2.7 1.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></button>
  <a data-af-email href="mailto:<?= web_e($__faEmail) ?>" aria-label="Email Artdon"><span class="label">Email</span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6.5h16v11H4v-11Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="m5 7 7 6 7-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg></a>
  <?php if ($__faWhatsAppHref !== ''): ?><a data-af-whatsapp href="<?= web_e($__faWhatsAppHref) ?>" target="_blank" rel="noopener" aria-label="Chat with Artdon on WhatsApp"><span class="label">Chat with Artdon</span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20.2 11.75a8.18 8.18 0 0 1-12.03 7.18L4 20l1.1-4a8.2 8.2 0 1 1 15.1-4.25Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><path d="M8.2 7.7c.22-.5.45-.52.82-.53h.34c.14 0 .34.06.45.34l.9 2.1c.08.2.06.38-.04.55l-.7 1c-.11.15-.09.33 0 .48.44.8 1.14 1.52 1.93 2 .17.1.36.12.51-.02l1.15-1.08c.16-.15.35-.18.55-.08l2.02.96c.24.12.34.27.32.48-.05.73-.42 1.5-1.03 1.92-.55.38-1.27.55-2.16.36-1.38-.3-3.12-1.28-4.5-2.68-1.4-1.41-2.3-3.1-2.48-4.3-.13-.8.05-1.14.36-1.52.2-.25.4-.36.56-.44Z" fill="currentColor"/></svg></a><?php endif; ?>
  <button type="button" data-af-copy aria-label="Copy link"><span class="label">Copy current link</span><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.07 0l2.12-2.12a5 5 0 1 0-7.07-7.07L10.9 5" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M14 11a5 5 0 0 0-7.07 0L4.8 13.12a5 5 0 1 0 7.07 7.07L13.1 19" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg></button>
</nav>
<div class="artdon-copy-toast-v71871" data-af-toast>Link copied</div>
<div class="artdon-fi-modal-v71871" data-af-modal hidden aria-hidden="true">
  <div class="artdon-fi-backdrop-v71871" data-af-close></div>
  <section class="artdon-fi-panel-v71871" role="dialog" aria-modal="true" aria-labelledby="artdon-fi-title-v71871">
    <div class="artdon-fi-shell-v718109">
      <aside class="artdon-fi-aside-v718109">
        <div class="artdon-fi-brand-v718109">
          <div class="artdon-fi-kicker-v718109"><?= web_e($__faText('quote_modal_kicker', 'Project support')) ?></div>
          <h3 class="artdon-fi-company-v718109"><?= web_e($__faCompany) ?></h3>
          <p><?= nl2br(web_e($__faText('quote_modal_side_text', "Share your project requirement.\nOur team will route it to the right sales and technical owner."))) ?></p>
        </div>
        <div class="artdon-fi-trust-v718109" aria-label="Inquiry workflow notice">
          <div class="artdon-fi-trust-item-v718109"><span class="artdon-fi-trust-dot-v718109"></span><span><?= web_e($__faText('quote_modal_trust_1', 'Connected to CRM and dispatch system.')) ?></span></div>
          <div class="artdon-fi-trust-item-v718109"><span class="artdon-fi-trust-dot-v718109"></span><span><?= web_e($__faText('quote_modal_trust_2', 'Fastest response within 1 hour.')) ?></span></div>
          <div class="artdon-fi-trust-item-v718109"><span class="artdon-fi-trust-dot-v718109"></span><span><?= web_e($__faText('quote_modal_trust_3', 'Product, sample or project details are tracked clearly.')) ?></span></div>
        </div>
      </aside>
      <div class="artdon-fi-main-v718109">
        <div class="artdon-fi-head-v71871">
          <div>
            <h2 id="artdon-fi-title-v71871"><?= web_e($__faText('quote_modal_title', 'Project inquiry')) ?></h2>
            <p data-af-modal-subtitle data-template="<?= web_e($__faText('quote_modal_subtitle_template', 'Inquiry about: {project}')) ?>" data-default="<?= web_e($__faText('quote_modal_subtitle_default', 'Send us your product or project requirement.')) ?>"><?= web_e($__faText('quote_modal_subtitle_default', 'Send us your product or project requirement.')) ?></p>
          </div>
          <button type="button" class="artdon-fi-close-v71871" data-af-close aria-label="Close">×</button>
        </div>
        <div class="artdon-fi-mini-notice-v718109"><strong><?= web_e($__faText('quote_modal_notice_title', 'CRM + dispatch linked')) ?></strong><span><?= web_e($__faText('quote_modal_notice_text', 'Your inquiry is saved and assigned automatically. Fastest reply within 1 hour.')) ?></span></div>
    <form action="submit_inquiry.php" method="post" data-af-form>
      <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="artdon-fi-hp-v71871" aria-hidden="true">
      <input type="hidden" name="source" value="global_quote">
      <input type="hidden" name="support_type" value="quotation">
      <input type="hidden" name="product" data-af-field-product value="">
      <input type="hidden" name="product_link" data-af-field-link value="">
      <input type="hidden" name="page_type" data-af-field-page-type value="">
      <input type="hidden" name="page_title" data-af-field-page-title value="">
      <input type="hidden" name="return_url" data-af-field-return value="index.php">
      <div class="artdon-fi-grid-v71871">
        <div class="artdon-fi-field-v71871"><label><?= web_e($__faText('quote_modal_name_label', 'Name *')) ?></label><input name="name" required autocomplete="name"></div>
        <div class="artdon-fi-field-v71871"><label><span class="artdon-fi-desktop-label-v718192" aria-hidden="true"><?= web_e($__faText('quote_modal_email_label', 'Email *')) ?></span><span class="artdon-fi-mobile-label-v718192" aria-hidden="true">Email or WhatsApp *</span></label><input name="email" type="email" required autocomplete="email"></div>
        <div class="artdon-fi-field-v71871"><label>WhatsApp</label><input name="whatsapp" autocomplete="tel" inputmode="tel" placeholder="+86 139 2533 2972"></div>
        <div class="artdon-fi-field-v71871 is-mobile-optional-v718192"><label><?= web_e($__faText('quote_modal_company_label', 'Company')) ?></label><input name="company" autocomplete="organization"></div>
        <div class="artdon-fi-field-v71871"><label><?= web_e($__faText('quote_modal_country_label', 'Country')) ?></label><input name="country" autocomplete="country-name"></div>
        <div class="artdon-fi-field-v71871 full"><label><?= web_e($__faText('quote_modal_requirement_label', 'Project requirement')) ?></label><textarea name="message" data-af-message placeholder="<?= web_e($__faText('quote_modal_requirement_placeholder', 'Tell us quantity, project type, beam angle, CCT or other requirements.')) ?>"></textarea></div>
      </div>
      <div class="artdon-fi-actions-v71871">
        <button type="button" class="artdon-fi-cancel-v71871" data-af-close><?= web_e($__faText('quote_modal_cancel_label', 'Cancel')) ?></button>
        <button type="submit" class="artdon-fi-submit-v71871" data-af-submit data-label="<?= web_e($__faText('quote_modal_submit_label', 'Submit inquiry')) ?>"><?= web_e($__faText('quote_modal_submit_label', 'Submit inquiry')) ?></button>
      </div>
      <div class="artdon-fi-status-v71871" data-af-status aria-live="polite"></div>
    </form>
      </div>
    </div>
  </section>
</div>
<script>
(function(){
  var root=document.querySelector('[data-artdon-float-actions-v71871]');
  if(!root||root.__afV71871)return;root.__afV71871=true;
  var ctx={};try{ctx=JSON.parse(root.getAttribute('data-context')||'{}')||{};}catch(e){}
  function q(sel,scope){return(scope||document).querySelector(sel)}
  function clean(s){return String(s||'').replace(/\s+/g,' ').trim()}
  function clip(s,n){s=clean(s);return s.length>n?s.slice(0,n):s}
  function pageTitle(){return clean((ctx.title||q('h1')?.textContent||document.title||'Artdon Lighting'))}
  function productText(){
    var parts=[];
    if(ctx.product)parts.push(ctx.product);
    if(ctx.model)parts.push(ctx.model);
    if(ctx.size)parts.push(ctx.size);
    if(!parts.length&&ctx.series)parts.push(ctx.series);
    if(!parts.length&&(ctx.page_type==='product'||ctx.page_type==='series'))parts.push(pageTitle());
    return clip(parts.join(' / '),80);
  }
  function triggerProduct(trigger){
    if(!trigger)return '';
    var explicit=clean(trigger.getAttribute('data-quote-product')||'');
    if(/^(general project quotation|project inquiry)$/i.test(explicit))explicit='';
    if(explicit)return clip(explicit,80);
    var href=trigger.getAttribute('href')||'';
    if(href){
      try{
        var targetUrl=new URL(href,location.href);
        var product=clean(targetUrl.searchParams.get('product')||'');
        var model=clean(targetUrl.searchParams.get('model')||'');
        if(product)return clip([product,model].filter(Boolean).join(' / '),80);
      }catch(e){}
    }
    return '';
  }
  function triggerLink(trigger){
    if(!trigger)return currentUrl();
    var explicit=clean(trigger.getAttribute('data-quote-link')||'');
    if(!explicit)return currentUrl();
    try{return new URL(explicit,location.href).href}catch(e){return currentUrl()}
  }
  function formatProjectText(template,project,fallback){
    if(!project)return fallback;
    return clean(String(template||'Inquiry about: {project}').replace(/\{project\}/gi,project));
  }
  function isMobileView(){
    return !window.matchMedia || window.matchMedia('(max-width: 820px)').matches;
  }
  function triggerLabel(trigger){
    return clean(trigger?.textContent||'').replace(/[→›»]+/g,'').trim();
  }
  function isInquiryCtaTrigger(trigger){
    if(!trigger||trigger.closest('[data-af-modal]'))return false;
    var label=triggerLabel(trigger);
    if(/^(get|request)\s+(a\s+)?quote\b/i.test(label))return true;
    if(!isMobileView())return false;
    return /^(discuss\b.*\bproject|project\s+inquiry|talk\s+to\s+our\s+engineers|ask\s+our\s+team)\b/i.test(label);
  }
  function inquiryKind(trigger,prod){
    var label=triggerLabel(trigger);
    if(/^(discuss\b.*\bproject|project\s+inquiry|talk\s+to\s+our\s+engineers|ask\s+our\s+team)\b/i.test(label))return 'project';
    if(prod || /quote/i.test(label))return 'quotation';
    if(/technical|file|download/i.test(label))return 'technical';
    return 'project';
  }
  function setMobileInquiryLabels(){
    var emailField=q('[data-af-form] input[name="email"]');
    if(emailField)emailField.setAttribute('aria-label',isMobileView()?'Email or WhatsApp':'Email');
    if(!isMobileView())return;
    document.querySelectorAll('a,button').forEach(function(el){
      if(el.closest('[data-af-modal]')||el.closest('[data-artdon-float-actions-v71871]'))return;
      if(el.getAttribute('data-mobile-inquiry-label-applied')==='1')return;
      var label=triggerLabel(el);
      var next='';
      if(/^(get|request)\s+(a\s+)?quote\b/i.test(label))next='Request a Quote';
      else if(/^(discuss\b.*\bproject|project\s+inquiry|talk\s+to\s+our\s+engineers)\b/i.test(label))next='Discuss a Project';
      if(!next)return;
      var arrow=/[→›»]/.test(el.textContent||'')?' →':'';
      el.setAttribute('data-mobile-inquiry-label-applied','1');
      el.setAttribute('data-mobile-inquiry-original',el.textContent||'');
      el.textContent=next+arrow;
    });
  }
  function currentUrl(){return location.href.split('#')[0]}
  function legacyReturnUrl(){
    var file=(location.pathname.split('/').pop()||'index.php');
    if(!/^(index|contact|product|products|series)\.php$/i.test(file)) file='index.php';
    return file+(location.search||'');
  }
  function setVal(sel,val){var el=q(sel);if(el)el.value=val||''}
  function showToast(t){var toast=q('[data-af-toast]');if(!toast)return;toast.textContent=t||'Link copied';toast.classList.add('show');clearTimeout(toast.__t);toast.__t=setTimeout(function(){toast.classList.remove('show')},1800)}
  function copyLink(){var url=currentUrl();if(navigator.clipboard&&navigator.clipboard.writeText){navigator.clipboard.writeText(url).then(function(){showToast('Link copied')}).catch(fallback)}else fallback();function fallback(){var i=document.createElement('input');i.value=url;document.body.appendChild(i);i.select();document.execCommand('copy');i.remove();showToast('Link copied')}}
  function mailto(){var email=root.getAttribute('data-email')||'sales@artdon.cn';var title=pageTitle();var body=['Hello Artdon,','','I am interested in:',productText(),'','Link:',currentUrl(),''].join('\n');return 'mailto:'+encodeURIComponent(email)+'?subject='+encodeURIComponent('Inquiry about '+title)+'&body='+encodeURIComponent(body)}
  function openInquiry(trigger){
    var modal=q('[data-af-modal]'); if(!modal)return;
    var prod=triggerProduct(trigger)||productText();
    setVal('[data-af-field-product]', prod);
    setVal('[data-af-field-link]', triggerLink(trigger));
    setVal('[data-af-field-page-type]', ctx.page_type||'page');
    setVal('[data-af-field-page-title]', pageTitle());
    setVal('[data-af-field-return]', legacyReturnUrl());
    setVal('[data-af-form] input[name="support_type"]', inquiryKind(trigger,prod));
    var subtitle=q('[data-af-modal-subtitle]'); if(subtitle) subtitle.textContent=formatProjectText(subtitle.getAttribute('data-template'),prod,subtitle.getAttribute('data-default')||'Send us your product or project requirement.');
    var msg=q('[data-af-message]');
    if(msg){
      var previousAuto=clean(msg.getAttribute('data-auto-value')||'');
      if(!clean(msg.value)||clean(msg.value)===previousAuto){
        var nextAuto=prod ? ('I am interested in '+prod) : '';
        msg.value=nextAuto;
        msg.setAttribute('data-auto-value',nextAuto);
      }
    }
    var status=q('[data-af-status]'); if(status){status.textContent='';status.className='artdon-fi-status-v71871'}
    modal.hidden=false; modal.setAttribute('aria-hidden','false'); document.documentElement.style.overflow='hidden';
    setTimeout(function(){var name=q('[data-af-form] input[name="name"]'); if(name) name.focus();},60);
  }
  function closeInquiry(){var modal=q('[data-af-modal]'); if(!modal)return; modal.hidden=true; modal.setAttribute('aria-hidden','true'); document.documentElement.style.overflow='';}
  function bindForm(){
    var form=q('[data-af-form]'); if(!form||form.__bound)return; form.__bound=true;
    form.addEventListener('submit',function(ev){
      if(!window.fetch || !window.FormData) return;
      ev.preventDefault();
      var status=q('[data-af-status]'); var btn=q('[data-af-submit]');
      if(status){status.textContent='Submitting...';status.className='artdon-fi-status-v71871'}
      if(btn){btn.disabled=true;btn.textContent='Submitting...'}
      fetch(form.getAttribute('action')||'submit_inquiry.php',{method:'POST',body:new FormData(form),headers:{'X-Requested-With':'XMLHttpRequest'}})
        .then(function(r){return r.json().catch(function(){return {ok:false,message:'Submit failed. Please try again.'}})})
        .then(function(data){
          if(data&&data.ok){
            if(status){status.textContent=data.message||'Thank you. Your inquiry has been received and assigned through our CRM + dispatch workflow.';status.className='artdon-fi-status-v71871 ok'}
            showToast('Inquiry submitted');
            form.reset();
            setTimeout(closeInquiry,1300);
          }else{
            if(status){status.textContent=(data&&data.message)||'Submit failed. Please try again.';status.className='artdon-fi-status-v71871 err'}
            if(data&&data.status==='captcha'&&window.ArtdonInquiryCaptcha)window.ArtdonInquiryCaptcha.refresh(form,true);
          }
        })
        .catch(function(){if(status){status.textContent='Submit failed. Please try again or email us.';status.className='artdon-fi-status-v71871 err'}})
        .finally(function(){if(btn){btn.disabled=false;btn.textContent=btn.getAttribute('data-label')||'Submit inquiry'}});
    });
  }
  var inquiryBtn=q('[data-af-inquiry]',root); if(inquiryBtn) inquiryBtn.addEventListener('click',function(){openInquiry(inquiryBtn)});
  document.addEventListener('click',function(e){
    var target=e.target&&e.target.closest?e.target.closest('a,button'):null;
    if(!isInquiryCtaTrigger(target))return;
    e.preventDefault();
    e.stopImmediatePropagation();
    openInquiry(target);
  },true);
  var copyBtn=q('[data-af-copy]',root); if(copyBtn) copyBtn.addEventListener('click',copyLink);
  var emailLink=q('[data-af-email]',root); if(emailLink) emailLink.addEventListener('click',function(){emailLink.href=mailto()});
  document.addEventListener('click',function(e){if(e.target&&e.target.matches('[data-af-close]'))closeInquiry();});
  document.addEventListener('keydown',function(e){if(e.key==='Escape')closeInquiry();});
  bindForm();
  setMobileInquiryLabels();
  window.addEventListener('resize',setMobileInquiryLabels,{passive:true});
})();
</script>
