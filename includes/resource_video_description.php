<?php
declare(strict_types=1);

require_once __DIR__ . '/resource_video_links.php';

/** Read only a single href; every other attribute is discarded. */
function artdon_resource_video_description_href(string $attributes): string
{
    $offset = 0;
    $length = strlen($attributes);
    $href = null;
    while ($offset < $length) {
        if (preg_match('/\G\s+/A', $attributes, $match, 0, $offset)) $offset += strlen($match[0]);
        if ($offset >= $length) break;
        if (!preg_match('/\G([^\s=\/\'"<>]+)/A', $attributes, $match, 0, $offset)) return '';
        $name = strtolower($match[1]);
        $offset += strlen($match[0]);
        if (preg_match('/\G\s+/A', $attributes, $match, 0, $offset)) $offset += strlen($match[0]);
        $value = null;
        if (($attributes[$offset] ?? '') === '=') {
            $offset++;
            if (preg_match('/\G\s+/A', $attributes, $match, 0, $offset)) $offset += strlen($match[0]);
            $quote = $attributes[$offset] ?? '';
            if ($quote === '"' || $quote === "'") {
                $end = strpos($attributes, $quote, $offset + 1);
                if ($end === false) return '';
                $value = substr($attributes, $offset + 1, $end - $offset - 1);
                $offset = $end + 1;
            } elseif (preg_match('/\G([^\s\'"=<>`]+)/A', $attributes, $match, 0, $offset)) {
                $value = $match[1];
                $offset += strlen($match[0]);
            } else {
                return '';
            }
        }
        if ($name === 'href') {
            if ($href !== null || $value === null) return '';
            $href = $value;
        }
    }
    return $href === null ? '' : artdon_resource_video_link_url(html_entity_decode($href, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** Anchor tokens only: non-anchor markup remains historical plain text. */
function artdon_resource_video_description_anchor_pattern(): string
{
    return '~<(/?)a(?=[\s/>])((?:"[^"]*"|\'[^\']*\'|[^\'"<>])*)>~is';
}

function artdon_resource_video_description_without_anchors(string $value): string
{
    return preg_replace(artdon_resource_video_description_anchor_pattern(), '', $value) ?? $value;
}

/**
 * Split the tiny supported format into plain text and safe links. A nested or
 * unclosed anchor never becomes a link. No DOM or production services are used.
 */
function artdon_resource_video_description_parts(mixed $value): array
{
    if (!is_string($value) || $value === '') return [];
    $matched = preg_match_all(artdon_resource_video_description_anchor_pattern(), $value, $tokens, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    if (!$matched) return [['text'=>$value, 'url'=>'']];
    $parts = [];
    $cursor = 0;
    $depth = 0;
    $contentStart = 0;
    $attributes = '';
    $nested = false;
    foreach ($tokens as $token) {
        $start = $token[0][1];
        $end = $start + strlen($token[0][0]);
        $closing = $token[1][0] === '/';
        if (!$closing) {
            if ($depth === 0) {
                if ($start > $cursor) $parts[] = ['text'=>substr($value, $cursor, $start - $cursor), 'url'=>''];
                $contentStart = $end;
                $attributes = $token[2][0];
                $nested = false;
            } else {
                $nested = true;
            }
            $depth++;
        } elseif ($depth === 0) {
            if ($start > $cursor) $parts[] = ['text'=>substr($value, $cursor, $start - $cursor), 'url'=>''];
            $cursor = $end; // Discard an orphan closing anchor, retaining its surrounding text.
        } else {
            $depth--;
            if ($depth === 0) {
                $body = substr($value, $contentStart, $start - $contentStart);
                $text = html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $url = $nested ? '' : artdon_resource_video_description_href($attributes);
                // Do not promote an entity-encoded anchor inside an invalid link
                // into a new link on the next normalization of saved plain text.
                if ($url === '') $text = artdon_resource_video_description_without_anchors($text);
                $parts[] = ['text'=>$text, 'url'=>$url];
                $cursor = $end;
            }
        }
    }
    if ($depth > 0) {
        // An unfinished link has no reliable label boundary. Preserve the entire
        // remaining plain body, including newlines and non-anchor literal tags.
        $parts[] = ['text'=>artdon_resource_video_description_without_anchors(substr($value, $contentStart)), 'url'=>''];
    } elseif ($cursor < strlen($value)) {
        $parts[] = ['text'=>substr($value, $cursor), 'url'=>''];
    }
    return $parts;
}

function artdon_resource_video_description_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Storage format: historical plain text plus canonical, escaped anchor labels. */
function artdon_resource_video_description_source(mixed $value): string
{
    $source = '';
    foreach (artdon_resource_video_description_parts($value) as $part) {
        $source .= $part['url'] === '' ? $part['text']
            : '<a href="' . artdon_resource_video_description_escape($part['url']) . '">' . artdon_resource_video_description_escape($part['text']) . '</a>';
    }
    return $source;
}

/** Plain text for search and SEO; encoded anchor labels become actual text. */
function artdon_resource_video_description_text(mixed $value): string
{
    return implode('', array_column(artdon_resource_video_description_parts($value), 'text'));
}

/** Only generated links are HTML; editors can retain raw newlines without BRs. */
function artdon_resource_video_description_html(mixed $value, bool $lineBreaks = true): string
{
    $html = '';
    foreach (artdon_resource_video_description_parts($value) as $part) {
        $text = artdon_resource_video_description_escape($part['text']);
        if ($lineBreaks) $text = nl2br($text);
        $html .= $part['url'] === '' ? $text
            : '<a href="' . artdon_resource_video_description_escape($part['url']) . '" target="_blank" rel="noopener noreferrer">' . $text . '</a>';
    }
    return $html;
}
