<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;

/**
 * Charge toutes les dimensions d'une page, locales et etapes confondues.
 *
 * Pendant un enregistrement, Sulu ne charge que la locale editee : la
 * collection portee par la page ne contient donc pas les langues a mettre a
 * jour. Elles sont relues ici depuis leur entite.
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
