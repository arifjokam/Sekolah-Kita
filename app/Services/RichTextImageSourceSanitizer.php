<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Replaces the built-in media URL check for <img src> with the article scoped
 * allowlist.
 *
 * The sanitizer runs this for the element first, so returning null drops the
 * attribute before any other sanitizer can re-admit it.
 */
final class RichTextImageSourceSanitizer implements AttributeSanitizerInterface
{
    public function __construct(private readonly RichTextImageSourcePolicy $policy) {}

    /**
     * @return list<string>|null
     */
    public function getSupportedElements(): ?array
    {
        return ['img'];
    }

    /**
     * @return list<string>|null
     */
    public function getSupportedAttributes(): ?array
    {
        return ['src'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        return $this->policy->canonicalUrl($value);
    }
}
