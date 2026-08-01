# Artdon Website Image Standards

This is the long-term image rule for artdonlighting.com. New uploads should follow these standards.

## Required image standards

| Usage | Format | Size / dimension | Target file size | Notes |
| --- | --- | --- | --- | --- |
| Hero Banner | WebP | width up to 1920px | ≤ 250KB | For page hero/banner backgrounds. |
| Product | WebP | width up to 1600px | ≤ 150KB | For product family, product detail and accessory images. |
| Project | WebP | width up to 1920px | ≤ 200KB | For project listing/detail and application case images. |
| Article / General | WebP | width up to 1600px | ≤ 150KB | For blog covers, resource cards and general content images. |

## Naming

- Use English lowercase words.
- Use hyphens between words.
- Include the product/project/context when useful.
- Avoid spaces, Chinese punctuation, random camera names and version names such as `IMG_1234`.

Examples:

- `armi-recessed-track-light-white.webp`
- `luxury-fashion-store-lighting-project-hong-kong.webp`
- `hospitality-lighting-hero-banner.webp`

## ALT text

ALT should describe what the image shows, not just repeat the filename.

Good examples:

- `ARMI recessed articulated track light in white finish`
- `Luxury fashion store lighting project with track lights`
- `Hospitality lobby lighting with low-glare downlights`

Avoid:

- `image`
- `banner`
- `1`
- keyword stuffing

## Title

The backend media title is the internal management name. Keep it clear and searchable, for example:

- `ARMI recessed track light white finish`
- `Projects page hero banner`
- `Luxury boutique lighting project image`

## Current implementation

The admin upload pipeline automatically converts uploaded images to WebP, resizes them by usage and compresses them toward the target file size. Existing old image URLs are not renamed automatically, because changing live file paths can break links.
