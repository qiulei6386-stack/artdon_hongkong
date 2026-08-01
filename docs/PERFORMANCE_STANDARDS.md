# Artdon Website Performance Standards

## Targets

- Google PageSpeed Desktop: 95+
- Google PageSpeed Mobile: 90+
- Public pages should keep one high-priority LCP image only.
- Below-the-fold images should use lazy loading, async decoding and low fetch priority.

## CDN

- Serve production traffic through the approved CDN/DNS provider when available.
- CDN must preserve the canonical host `https://artdonlighting.com`.
- CDN cache rules should follow the same cache policy as the origin server.

## Cache

- HTML: public micro-cache, short TTL, safe for public GET pages only.
- CSS/JS: long browser cache with version query strings.
- Images/fonts/video: long immutable cache.
- Clear `storage/page_cache/*.html` after content/template/image reference changes.

## Images

- Hero/Banner: WebP, width up to 1920px, target <= 250KB.
- Product: WebP, width up to 1600px, target <= 150KB.
- Project: WebP, width up to 1920px, target <= 200KB.
- Use descriptive English filenames, ALT text and title/label copy.
- Do not overwrite original media during corrective edits; create a new optimized asset and update references.

## HTML/CSS/JS

- Keep critical homepage CSS available immediately.
- Load secondary CSS without blocking first paint where safe.
- Defer non-critical JavaScript.
- Do not load homepage video on mobile, data-saver or slow networks.
- Use `content-visibility:auto` for below-the-fold sections when layout supports it.

## Verification

- Run PHP lint for changed public templates.
- Check live page HTTP 200.
- Check key CSS/JS/image URLs HTTP 200.
- Confirm no broken image URLs after image/reference changes.
- Run Google PageSpeed after each speed pass when the API or browser report is available.
