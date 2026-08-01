# Artdon Security Header Standards

## Target

- SecurityHeaders.com target grade: A or higher.
- Apply headers to public PHP pages, cached public pages and admin pages.
- Keep the CSP compatible with existing forms, images, maps and embedded video.

## Required Headers

- `Content-Security-Policy`
- `X-Frame-Options`
- `X-Content-Type-Options`
- `Referrer-Policy`
- `Permissions-Policy`
- `Strict-Transport-Security`
- `X-Permitted-Cross-Domain-Policies`

## CSP Baseline

- Default content source is the same site.
- Object/embed plugins are blocked.
- Forms submit only to the same site.
- Frames are restricted to the same site plus current video/map providers.
- Mixed HTTP subresources are upgraded to HTTPS and blocked if unsafe.
- Inline style/script remains allowed for compatibility with current templates.

## Verification

- Check the homepage, a product/list page and the admin login page.
- Confirm cached homepage responses still include the same security headers.
- Scan public HTML for `http://` image/script/style/frame resources.
- Re-run SecurityHeaders.com manually when Cloudflare allows browser verification.
