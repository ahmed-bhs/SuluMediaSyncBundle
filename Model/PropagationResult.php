<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Model;

/**
 * Ce qu'une propagation a change, locale par locale.
 */
final class PropagationResult
{
    /** @var array<string, list<string>> */
    private array $locales = [];

    /** @var list<string> */
    private array $publishedLocales = [];

    /**
     * @param list<string> $properties
     */
    public function addLocale(string $locale, array $properties): void
    {
        $this->locales[$locale] = $properties;
    }

    public function addPublishedLocale(string $locale): void
    {
        if (!\in_array($locale, $this->publishedLocales, true)) {
            $this->publishedLocales[] = $locale;
        }
    }

    /**
     * @return array<string, list<string>>
     */
    public function getLocales(): array
    {
        return $this->locales;
    }

    /**
     * Locales deja en ligne : ce sont les seules qu'il faut republier pour que
     * le changement soit visible du public.
     *
     * @return list<string>
     */
    public function getPublishedLocales(): array
    {
        return $this->publishedLocales;
    }

    public function hasChanges(): bool
    {
        return [] !== $this->locales;
    }

    public function countLocales(): int
    {
        return \count($this->locales);
    }
}
