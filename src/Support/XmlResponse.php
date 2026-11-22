<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

use Daika7ana\Bookurier\Exceptions\UnexpectedResponseException;
use DOMDocument;
use DOMElement;

/**
 * Thin read-only wrapper over Bookurier's XML envelopes.
 */
final class XmlResponse
{
    private function __construct(
        private DOMDocument $document,
        private string $body,
    ) {}

    /**
     * @throws UnexpectedResponseException
     */
    public static function from(string $body): self
    {
        $body = str_starts_with($body, "\xEF\xBB\xBF") ? substr($body, 3) : $body;

        if (trim($body) === '') {
            throw new UnexpectedResponseException('Bookurier returned an empty response.');
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($body, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            throw UnexpectedResponseException::withBody('Expected an XML response.', $body);
        }

        return new self($document, $body);
    }

    /**
     * The `<err>` text, or null when the envelope carries no error.
     */
    public function error(): ?string
    {
        $element = $this->first('err');

        if ($element === null) {
            return null;
        }

        $text = trim($element->textContent);

        return $text === '' ? null : $text;
    }

    /**
     * The first `<cmd>` element as tag => text.
     *
     * @return array<string, string>
     *
     * @throws UnexpectedResponseException
     */
    public function cmd(): array
    {
        $element = $this->first('cmd');

        if ($element === null) {
            throw UnexpectedResponseException::withBody('Response XML has no <cmd> element.', $this->body);
        }

        return $this->flatten($element);
    }

    /**
     * @return list<array<string, string>>
     *
     * @throws UnexpectedResponseException
     */
    public function commands(): array
    {
        $commands = [];

        foreach ($this->document->getElementsByTagName('cmd') as $element) {
            if ($element instanceof DOMElement) {
                $commands[] = $this->flatten($element);
            }
        }

        if ($commands === []) {
            throw UnexpectedResponseException::withBody('Response XML has no <cmd> elements.', $this->body);
        }

        return $commands;
    }

    /**
     * The trimmed text of the first `<tagName>` element, or null when absent or empty.
     */
    public function text(string $tagName): ?string
    {
        $element = $this->first($tagName);

        if ($element === null) {
            return null;
        }

        $text = trim($element->textContent);

        return $text === '' ? null : $text;
    }

    private function first(string $tagName): ?DOMElement
    {
        $element = $this->document->getElementsByTagName($tagName)->item(0);

        return $element instanceof DOMElement ? $element : null;
    }

    /**
     * @return array<string, string>
     */
    private function flatten(DOMElement $element): array
    {
        $fields = [];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $fields[$child->tagName] = trim($child->textContent);
            }
        }

        return $fields;
    }
}
