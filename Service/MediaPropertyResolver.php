<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

/**
 * Liste les proprietes d'un gabarit qui designent des medias.
 *
 * Les gabarits sont lus depuis leur XML plutot que depuis les metadonnees de
 * structure : celles-ci ne sont exposees que par des services prives, et leur
 * forme a change entre les versions majeures de Sulu. Le XML, lui, est le
 * format documente que l'integrateur ecrit a la main.
 *
 * Le type fait foi, jamais le nom : un champ nomme "photo" peut etre un texte,
 * et une selection de medias peut s'appeler autrement.
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
     * Noms des proprietes media de premier niveau.
     *
     * Les medias imbriques dans un bloc suivent leur bloc : recopier le bloc
     * entier melangerait le texte traduit a l'image, on les laisse donc de
     * cote et seules les proprietes racines sont propagees.
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
     * Une propriete est de premier niveau si aucun bloc ne la contient.
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
