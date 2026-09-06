<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

/**
 * Lists the properties of a template that point at media.
 *
 * Templates are read from their XML rather than from structure metadata: that
 * metadata is only exposed through private services, and its shape has changed
 * between major Sulu versions. The XML is the documented format integrators
 * write by hand.
 *
 * The type decides, never the name: a field called "photo" may hold text, and
 * a media selection may be called anything.
 */
final class MediaPropertyResolver
{
    public const MEDIA_TYPES = [
        'media_selection',
        'single_media_selection',
        'image_map',
    ];

    /** @var array<string, list<string>> */
    private array $cache = [];

    /**
     * @param list<string> $templateDirectories
     */
    public function __construct(
        private readonly array $templateDirectories,
    ) {
    }

    /**
     * Names of the top-level media properties.
     *
     * Media nested inside a block belong to that block: copying the whole
     * block would mix translated text with the image, so they are left alone
     * and only root properties are propagated.
     *
     * @return list<string>
     */
    public function resolve(string $templateKey): array
    {
        if (isset($this->cache[$templateKey])) {
            return $this->cache[$templateKey];
        }

        $file = $this->findTemplateFile($templateKey);
        if (null === $file) {
            return $this->cache[$templateKey] = [];
        }

        return $this->cache[$templateKey] = $this->readMediaProperties($file);
    }

    private function findTemplateFile(string $templateKey): ?string
    {
        foreach ($this->templateDirectories as $directory) {
            $file = rtrim($directory, '/') . '/' . $templateKey . '.xml';
            if (is_file($file)) {
                return $file;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function readMediaProperties(string $file): array
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            if (!$document->load($file)) {
                return [];
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $root = $document->documentElement;
        if (null === $root) {
            return [];
        }

        $properties = [];
        foreach ($root->getElementsByTagName('*') as $node) {
            if ('property' !== $node->localName) {
                continue;
            }

            if (!\in_array($node->getAttribute('type'), self::MEDIA_TYPES, true)) {
                continue;
            }

            if (!$this->isTopLevel($node)) {
                continue;
            }

            $name = $node->getAttribute('name');
            if ('' !== $name) {
                $properties[] = $name;
            }
        }

        return array_values(array_unique($properties));
    }

    /**
     * A property is top-level when no block contains it.
     */
    private function isTopLevel(\DOMNode $node): bool
    {
        for ($parent = $node->parentNode; null !== $parent; $parent = $parent->parentNode) {
            if ($parent instanceof \DOMElement && 'block' === $parent->localName) {
                return false;
            }
        }

        return true;
    }
}
