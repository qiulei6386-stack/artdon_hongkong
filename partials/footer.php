<?php
if (!function_exists('web_get_block')) {
    require_once dirname(__DIR__) . '/includes/bootstrap.php';
}
require_once dirname(__DIR__) . '/includes/footer.php';

$__footerSite = isset($site) && is_array($site) ? $site : web_get_block('site');
$__footerRaw = isset($footerBlock) && is_array($footerBlock) ? $footerBlock : web_get_block('footer');
$__footer = web_footer_normalize($__footerRaw, $__footerSite);
$__theme = web_footer_theme_tokens((string)($__footer['theme']['background'] ?? '#101010'));
$__themeStyle = implode(';', [
  '--footer-bg:'.$__theme['background'],
  '--footer-text:'.$__theme['text'],
  '--footer-muted:'.$__theme['muted'],
  '--footer-soft:'.$__theme['soft'],
  '--footer-line:'.$__theme['line'],
  '--footer-field:'.$__theme['field'],
  '--footer-field-line:'.$__theme['field_line'],
]).';';
$__brand = $__footer['brand'];
$__brandLogoDark = trim((string)($__brand['logo'] ?? 'assets/img/logo-artdon-footer.png'));
$__brandLogoLight = trim((string)($__brand['light_logo'] ?? 'assets/img/logo-artdon.png'));
$__brandLogo = !empty($__theme['is_light']) ? ($__brandLogoLight !== '' ? $__brandLogoLight : 'assets/img/logo-artdon.png') : ($__brandLogoDark !== '' ? $__brandLogoDark : 'assets/img/logo-artdon-footer.png');
$__brandLogoSafe = web_footer_safe_url($__brandLogo, !empty($__theme['is_light']) ? 'assets/img/logo-artdon.png' : 'assets/img/logo-artdon-footer.png');
$__brandLogoSrc = function_exists('web_asset_versioned_url') ? web_asset_versioned_url($__brandLogoSafe) : $__brandLogoSafe;
$__contact = $__footer['contact'];
$__newsletter = $__footer['newsletter'];
$__bottom = $__footer['bottom'];
$__columns = array_values(array_filter($__footer['columns'], static fn(array $column): bool => web_footer_bool($column['active'] ?? true)));
$__columnCount = max(1, min(6, count($__columns)));
$__company = trim((string)($__contact['company'] ?? $__footerSite['company'] ?? 'Artdon Lighting Limited'));
$__email = trim((string)($__contact['email_value'] ?? ''));
$__telephone = trim((string)($__contact['telephone_value'] ?? ''));
$__mobile = trim((string)($__contact['mobile_value'] ?? ''));
$__whatsapp = preg_replace('/\D+/', '', (string)($__contact['whatsapp_number'] ?? ''));
$__mobilePrimary = trim(preg_split('/\s*\/\s*/', $__mobile)[0] ?? $__mobile);
$__mobileDisplay = $__mobilePrimary !== '' ? $__mobilePrimary : $__mobile;
$__socials = array_values(array_filter($__bottom['social'] ?? [], static fn($item): bool => is_array($item)));
$__brandActive = web_footer_bool($__brand['active'] ?? true);
$__contactActive = web_footer_bool($__contact['active'] ?? true);
$__primaryClasses = ['footer-v611__primary'];
$__primaryClasses[] = $__brandActive ? 'has-brand' : 'without-brand';
$__primaryClasses[] = $__contactActive ? 'has-contact' : 'without-contact';
?>
<link rel="stylesheet" href="assets/css/artdon_footer_v6129.css?v=6.12.9">
<link rel="stylesheet" href="<?= web_e(web_asset_versioned_url('assets/css/artdon_inquiry_captcha.css')) ?>">
<footer class="site-footer footer-v611 footer-v6122 footer-v6128 footer-v6129<?= !empty($__theme['is_light']) ? ' is-light-theme' : ' is-dark-theme' ?>" id="footer" aria-label="Website footer" style="<?= web_e($__themeStyle) ?>">
  <div class="footer-v611__shell">
    <div class="<?= web_e(implode(' ', $__primaryClasses)) ?>">
      <?php if ($__brandActive): ?>
      <section class="footer-v611__brand">
        <?php if ($__brandLogo !== ''): ?>
          <a class="footer-v611__logo" href="<?= web_e(web_footer_safe_url((string)($__brand['home_url'] ?? 'index.php'), 'index.php')) ?>" aria-label="<?= web_e($__company) ?> home">
            <img src="<?= web_e($__brandLogoSrc) ?>" alt="<?= web_e($__brand['logo_alt'] ?? $__company) ?>" width="248" height="70">
          </a>
        <?php else: ?>
          <a class="footer-v611__wordmark" href="<?= web_e(web_footer_safe_url((string)($__brand['home_url'] ?? 'index.php'), 'index.php')) ?>"><?= web_e($__company) ?></a>
        <?php endif; ?>
        <?php if (trim((string)($__brand['tagline'] ?? '')) !== ''): ?>
          <p><?= nl2br(web_e($__brand['tagline'])) ?></p>
        <?php endif; ?>
        <?php if (web_footer_bool($__bottom['social_active'] ?? true) && $__socials): ?>
          <div class="footer-v611__brand-social" aria-label="Social media">
            <?php foreach ($__socials as $__social):
              $__socialHref = web_footer_safe_url((string)($__social['href'] ?? '#'));
              $__socialLabel = trim((string)($__social['label'] ?? 'Social link'));
              $__socialNewTab = web_footer_bool($__social['new_tab'] ?? true);
              $__socialDisabled = $__socialHref === '#' || str_starts_with($__socialHref, '#social-');
            ?>
              <?php if ($__socialDisabled): ?>
                <span class="footer-v611__social-icon is-disabled" aria-label="<?= web_e($__socialLabel) ?> link not configured" title="<?= web_e($__socialLabel) ?> — set the link in Footer Manager"><?= web_footer_social_icon_svg($__social) ?></span>
              <?php else: ?>
                <a class="footer-v611__social-icon" href="<?= web_e($__socialHref) ?>" aria-label="<?= web_e($__socialLabel) ?>" title="<?= web_e($__socialLabel) ?>"<?= $__socialNewTab ? ' target="_blank" rel="noopener"' : '' ?>><?= web_footer_social_icon_svg($__social) ?></a>
              <?php endif; ?>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </section>
      <?php endif; ?>

      <div class="footer-v611__menus" style="--footer-menu-count:<?= $__columnCount ?>">
        <?php foreach ($__columns as $__column): ?>
          <nav class="footer-v611__menu" aria-label="<?= web_e($__column['title'] ?? 'Footer links') ?>">
            <h2><?= web_e($__column['title'] ?? '') ?></h2>
            <div>
              <?php foreach (($__column['links'] ?? []) as $__link):
                $__href = web_footer_safe_url((string)($__link['href'] ?? '#'));
                $__newTab = web_footer_bool($__link['new_tab'] ?? false, false);
              ?>
                <a href="<?= web_e($__href) ?>"<?= $__newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= web_e($__link['label'] ?? '') ?></a>
              <?php endforeach; ?>
            </div>
          </nav>
        <?php endforeach; ?>
      </div>

      <?php if ($__contactActive): ?>
      <section class="footer-v611__contact">
        <h2><?= web_e($__contact['heading'] ?? 'Contact') ?></h2>
        <?php if ($__company !== ''): ?><strong><?= web_e($__company) ?></strong><?php endif; ?>
        <?php if (trim((string)($__contact['address'] ?? '')) !== ''): ?><p class="footer-v611__address"><?= nl2br(web_e($__contact['address'])) ?></p><?php endif; ?>
        <dl class="footer-v611__contact-list">
          <?php if (trim((string)($__contact['contact_value'] ?? '')) !== ''): ?>
            <div class="footer-v611__contact-row is-person"><dt><?= web_e($__contact['contact_label'] ?? 'Contact') ?></dt><dd><span class="footer-v611__contact-person"><?= web_e($__contact['contact_value']) ?></span></dd></div>
          <?php endif; ?>
          <?php if ($__email !== ''): ?>
            <div class="footer-v611__contact-row is-email"><dt><?= web_e($__contact['email_label'] ?? 'Email') ?></dt><dd><a href="mailto:<?= web_e($__email) ?>"><?= web_e($__email) ?></a></dd></div>
          <?php endif; ?>
          <?php if ($__telephone !== ''): ?>
            <div class="footer-v611__contact-row is-telephone"><dt><?= web_e($__contact['telephone_label'] ?? 'Tel') ?></dt><dd><a href="tel:<?= web_e(preg_replace('/[^0-9+]/', '', $__telephone)) ?>"><?= web_e($__telephone) ?></a></dd></div>
          <?php endif; ?>
          <?php if ($__mobileDisplay !== ''): ?>
            <div class="footer-v611__contact-row is-mobile"><dt><?= web_e($__contact['mobile_label'] ?? 'WhatsApp') ?></dt><dd><a href="<?= web_e($__whatsapp !== '' ? 'https://wa.me/'.$__whatsapp : 'tel:'.preg_replace('/[^0-9+]/', '', $__mobileDisplay)) ?>"<?= $__whatsapp !== '' ? ' target="_blank" rel="noopener"' : '' ?>><?= web_e($__mobileDisplay) ?></a></dd></div>
          <?php endif; ?>
        </dl>
        <?php if (trim((string)($__contact['button_label'] ?? '')) !== '' && trim((string)($__contact['button_url'] ?? '')) !== ''): ?>
          <a class="footer-v611__contact-link" href="<?= web_e(web_footer_safe_url((string)$__contact['button_url'])) ?>"><?= web_e($__contact['button_label']) ?><span aria-hidden="true">→</span></a>
        <?php endif; ?>
      </section>
      <?php endif; ?>
    </div>

    <?php if (web_footer_bool($__newsletter['active'] ?? true)): ?>
    <section class="footer-v611__newsletter">
      <div class="footer-v611__newsletter-copy">
        <span class="footer-v611__mail" aria-hidden="true"></span>
        <div>
          <h2><?= web_e($__newsletter['title'] ?? 'Stay inspired') ?></h2>
          <p><?= web_e($__newsletter['text'] ?? '') ?></p>
        </div>
      </div>
      <form class="footer-v611__newsletter-form" action="submit_newsletter.php" method="post">
        <input type="text" name="website" value="" tabindex="-1" autocomplete="off" class="footer-v611__honeypot" aria-hidden="true">
        <label class="sr-only" for="footerNewsletterEmail">Email address</label>
        <input id="footerNewsletterEmail" name="email" type="email" placeholder="<?= web_e($__newsletter['placeholder'] ?? 'Enter your email address') ?>" required>
        <button type="submit"><?= web_e($__newsletter['button'] ?? 'Subscribe') ?><span aria-hidden="true">→</span></button>
      </form>
    </section>
    <?php endif; ?>

    <div class="footer-v611__bottom">
      <p><?= web_e($__bottom['copyright'] ?? ('© '.date('Y').' '.$__company)) ?></p>
      <?php if (web_footer_bool($__bottom['legal_active'] ?? true) && !empty($__bottom['legal'])): ?>
        <nav class="footer-v611__legal" aria-label="Legal links">
          <?php foreach ($__bottom['legal'] as $__link):
            $__newTab = web_footer_bool($__link['new_tab'] ?? false, false);
          ?><a href="<?= web_e(web_footer_safe_url((string)($__link['href'] ?? '#'))) ?>"<?= $__newTab ? ' target="_blank" rel="noopener"' : '' ?>><?= web_e($__link['label'] ?? '') ?></a><?php endforeach; ?>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</footer>

<?php
$__artdonFloatingActions = dirname(__DIR__) . '/includes/floating_actions.php';
if (is_file($__artdonFloatingActions)) include $__artdonFloatingActions;
?>
<?php
// V7.1.8.137: lightweight public visitor analytics tracker.
// Only public front-end pages include this file; admin pages do not use partials/footer.php.
?>
<script src="assets/js/artdon_visitor_analytics.js?v=718138" defer></script>
<script src="<?= web_e(web_asset_versioned_url('assets/js/artdon_inquiry_captcha.js')) ?>" defer></script>
