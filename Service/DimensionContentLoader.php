<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Loads every dimension of a page, across all locales and stages.
 *
 * During a save, Sulu only loads the locale under edit, so the collection
 * carried by the page does not hold the locales that need updating. They are
 * read back here from their entity.
 */
final class DimensionContentLoader
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param class-string<DimensionContentInterface> $dimensionContentClass
     *
     * @return list<DimensionContentInterface>
     */
    public function loadAll(object $resource, string $dimensionContentClass): array
    {
        /** @var list<DimensionContentInterface> $contents */
        $contents = $this->entityManager->createQueryBuilder()
            ->select('dimensionContent')
            ->from($dimensionContentClass, 'dimensionContent')
            ->where('dimensionContent.page = :resource')
            ->setParameter('resource', $resource)
            ->getQuery()
            ->getResult();

        return $contents;
    }
}
