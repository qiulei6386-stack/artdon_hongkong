# Artdon Schema Standards

## Target

- Every important public page must output valid JSON-LD.
- New public templates should use `includes/schema.php` helper functions instead of hand-written JSON-LD.
- Rich Results Test should pass for supported types. Non-rich-result types, such as `Project`, should still be valid Schema.org JSON-LD.

## Required by page type

- All public pages: `Organization`, `WebSite`, `WebPage` or the closest subtype, and `BreadcrumbList` when a breadcrumb exists.
- Product category/list pages: `CollectionPage`, `BreadcrumbList`, `ItemList`.
- Product series pages: `ProductGroup`, variant `Product` nodes where relevant, `BreadcrumbList`.
- Product detail pages: `Product`, `BreadcrumbList`.
- FAQ pages: `FAQPage`, `Question`, `Answer`, `BreadcrumbList`.
- Blog/article pages: `Article`, `BreadcrumbList`.
- Video pages: `VideoObject` for visible videos, `CollectionPage`, `BreadcrumbList`.
- Project detail pages: `Project`, `Article`, `BreadcrumbList`.
- Project listing pages: `CollectionPage`, `ItemList`, `BreadcrumbList`.

## Helper functions

Use these helpers from `includes/schema.php`:

- `artdon_schema_organization()`
- `artdon_schema_website()`
- `artdon_schema_webpage()`
- `artdon_schema_breadcrumb()`
- `artdon_schema_product()`
- `artdon_schema_faq()`
- `artdon_schema_article()`
- `artdon_schema_video()`
- `artdon_schema_project()`
- `artdon_schema_graph()`
- `artdon_schema_script()`

## Verification

- Validate that JSON-LD parses on representative pages before deployment.
- Confirm required types appear on live pages.
- Run Google Rich Results Test manually for Product, FAQ, Article and Video pages when available.
