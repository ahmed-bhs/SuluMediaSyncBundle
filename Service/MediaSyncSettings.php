<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Ahmed\SuluMediaSyncBundle\Entity\MediaSyncSetting;
use Doctrine\ORM\EntityManagerInterface;

/**
 * The synchronisation settings, stored in the database rather than in
 * configuration.
 *
 * The point of the bundle is that an editor can switch propagation off the day
 * a locale needs its own image: a container parameter would require a deploy,
 * the table makes it immediate.
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
            // Until the migration has run the site must still respond, so
            // fall back to the defaults rather than failing.
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
