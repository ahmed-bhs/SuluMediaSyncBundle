<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Ahmed\SuluMediaSyncBundle\Entity\MediaSyncSetting;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Les reglages de la synchronisation, stockes en base et non en configuration.
 *
 * Le but du bundle est qu'un redacteur puisse couper la propagation le jour ou
 * une langue doit porter sa propre image : un parametre de conteneur imposerait
 * un deploiement, la table le rend immediat.
 */
final class MediaSyncSettings
{
    public const ENABLED = 'enabled';
    public const SOURCE_LOCALE = 'source_locale';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly bool $defaultEnabled,
        private readonly string $defaultSourceLocale,
    ) {
    }

    public function isEnabled(): bool
    {
        $value = $this->read(self::ENABLED);

        return null === $value ? $this->defaultEnabled : '1' === $value;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->write(self::ENABLED, $enabled ? '1' : '0');
    }

    public function getSourceLocale(): string
    {
        $value = $this->read(self::SOURCE_LOCALE);

        return null === $value || '' === $value ? $this->defaultSourceLocale : $value;
    }

    public function setSourceLocale(string $locale): void
    {
        $this->write(self::SOURCE_LOCALE, $locale);
    }

    private function read(string $name): ?string
    {
        try {
            $setting = $this->entityManager->find(MediaSyncSetting::class, $name);
        } catch (\Throwable) {
            // Tant que la migration n'a pas tourne, le site doit repondre :
            // on retombe sur les valeurs par defaut plutot que d'echouer.
            return null;
        }

        return $setting?->getValue();
    }

    private function write(string $name, string $value): void
    {
        $setting = $this->entityManager->find(MediaSyncSetting::class, $name);

        if ($setting instanceof MediaSyncSetting) {
            $setting->setValue($value);
        } else {
            $this->entityManager->persist(new MediaSyncSetting($name, $value));
        }

        $this->entityManager->flush();
    }
}
