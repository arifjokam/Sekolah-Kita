<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerAction;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Server side sanitization for the rich text article body.
 *
 * The editor is an untrusted HTML producer, so the body is never stored as it
 * was submitted. Symfony's HTML Sanitizer builds the document from a
 * restrictive allowlist: only the elements the news editor needs survive, only
 * the attributes they need survive, and no attribute can introduce a script, an
 * inline event handler, an inline style or an unapproved URI scheme.
 *
 * Two rules are enforced beyond the component defaults:
 *
 *  - the default action is Block, so a disallowed but harmless element such as
 *    <b> or <div> keeps its text instead of silently deleting content;
 *  - every element that could execute, embed or load something is dropped
 *    together with its content.
 */
final class RichTextSanitizer
{
    /**
     * news_articles.body is a TEXT column. A body that is longer than this is
     * reported as a validation error on the write path; the cap inside
     * sanitize() only protects the read path from a row that was written
     * outside the application.
     */
    public const MAX_BODY_BYTES = 65_535;

    /**
     * Elements dropped together with their content. Anything that can execute,
     * embed, load or request a sub resource belongs here.
     */
    private const DROPPED_ELEMENTS = [
        'applet', 'audio', 'base', 'basefont', 'body', 'button', 'canvas', 'embed', 'fieldset',
        'form', 'frame', 'frameset', 'head', 'html', 'iframe', 'input', 'keygen', 'link',
        'listing', 'map', 'marquee', 'math', 'menu', 'meta', 'noembed', 'noframes', 'noscript',
        'object', 'optgroup', 'option', 'output', 'param', 'plaintext', 'portal', 'script',
        'select', 'slot', 'source', 'style', 'svg', 'template', 'textarea', 'track', 'video',
        'xmp',
    ];

    /**
     * Alignments the editor may store, mirrored by the data-align rules below.
     */
    private const ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    private const IMAGE_WIDTHS = ['25', '50', '75', '100'];

    /**
     * Block level elements that mean a body is rich text. Inline markup alone is
     * deliberately not enough: bodies written before the editor existed are
     * plain text, and they must keep rendering exactly as they did.
     */
    private const RICH_TEXT_PATTERN = '/<\s*\/?(?:p|h2|h3|h4|ul|ol|li|blockquote|hr|img)\b/i';

    /**
     * Sanitize untrusted markup for storage or display.
     *
     * @param  RichTextImageSourcePolicy|null  $images  null denies every image source
     */
    public function sanitize(string $html, ?RichTextImageSourcePolicy $images = null): string
    {
        if (strlen($html) > self::MAX_BODY_BYTES) {
            $html = substr($html, 0, self::MAX_BODY_BYTES);
        }

        if (trim($html) === '') {
            return '';
        }

        $sanitized = (new HtmlSanitizer($this->config($images ?? RichTextImageSourcePolicy::denyAll())))
            ->sanitize($html);

        return trim($this->discardSourceLessImages($sanitized));
    }

    /**
     * Whether a stored body is rich text, and therefore has to be rendered as
     * sanitized markup instead of as escaped plain text.
     */
    public static function looksLikeRichText(?string $value): bool
    {
        return $value !== null && preg_match(self::RICH_TEXT_PATTERN, $value) === 1;
    }

    /**
     * Remove images whose source was refused.
     *
     * The sanitizer drops the offending attribute, which would otherwise leave
     * an empty <img> behind. The value at this point is already sanitized
     * markup with a known element shape, so removing a source-less image is a
     * safe rewrite.
     */
    private function discardSourceLessImages(string $sanitized): string
    {
        return (string) preg_replace('#<img\b(?:(?!src=)[^>])*>#i', '', $sanitized);
    }

    private function config(RichTextImageSourcePolicy $images): HtmlSanitizerConfig
    {
        $config = (new HtmlSanitizerConfig)
            ->defaultAction(HtmlSanitizerAction::Block)
            ->allowElement('p', ['data-align'])
            ->allowElement('h2', ['data-align'])
            ->allowElement('h3', ['data-align'])
            ->allowElement('h4', ['data-align'])
            ->allowElement('blockquote', ['data-align'])
            ->allowElement('strong')
            ->allowElement('em')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('hr')
            ->allowElement('br')
            ->allowElement('img', ['src', 'alt', 'title', 'data-align', 'data-width'])
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->allowLinkSchemes(['http', 'https', 'mailto', 'tel'])
            ->allowRelativeLinks(false)
            ->allowMediaSchemes(['http', 'https'])
            // Relative media sources are accepted because they are never taken
            // from the input: RichTextImageSourceSanitizer runs first for
            // img[src] and rewrites the value to the canonical URL of a stored
            // asset, which the built-in media check must not reject again when
            // the public disk is configured without an absolute URL.
            ->allowRelativeMedias(true)
            ->withMaxInputLength(-1)
            ->withAttributeSanitizer(new RichTextImageSourceSanitizer($images))
            ->withAttributeSanitizer(new RichTextAttributeSanitizer([
                'p' => ['data-align' => self::ALIGNMENTS],
                'h2' => ['data-align' => self::ALIGNMENTS],
                'h3' => ['data-align' => self::ALIGNMENTS],
                'h4' => ['data-align' => self::ALIGNMENTS],
                'blockquote' => ['data-align' => self::ALIGNMENTS],
                'img' => [
                    'data-align' => self::ALIGNMENTS,
                    'data-width' => self::IMAGE_WIDTHS,
                ],
            ]));

        foreach (self::DROPPED_ELEMENTS as $element) {
            // Every configuration method returns a clone, so the result has to
            // be reassigned; a discarded return value would silently do nothing.
            $config = $config->dropElement($element);
        }

        return $config;
    }
}
