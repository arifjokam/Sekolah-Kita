<?php

namespace App\Services;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * Constrains the data attributes the rich text editor is allowed to emit.
 *
 * The editor renders alignment and image sizing as data attributes rather than
 * inline styles, so a stored body never carries a style attribute at all. The
 * values are still constrained here: an unknown value is dropped instead of
 * being written, which keeps the stored markup inside the documented contract.
 */
final class RichTextAttributeSanitizer implements AttributeSanitizerInterface
{
    /**
     * @param  array<string, array<string, list<string>>>  $constraints  element => attribute => allowed values
     */
    public function __construct(private readonly array $constraints) {}

    /**
     * @return list<string>|null
     */
    public function getSupportedElements(): ?array
    {
        return array_keys($this->constraints);
    }

    /**
     * @return list<string>|null
     */
    public function getSupportedAttributes(): ?array
    {
        $attributes = [];

        foreach ($this->constraints as $allowed) {
            $attributes = [...$attributes, ...array_keys($allowed)];
        }

        return array_values(array_unique($attributes));
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        $allowed = $this->constraints[$element][$attribute] ?? null;

        if ($allowed === null) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }
}
