<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Ahmed\SuluMediaSyncBundle\Model\PropagationResult;
use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\TemplateInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;

/**
 * Recopie les proprietes media d'une locale de reference vers les autres.
 *
 * Les entites sont ecrites directement plutot que par le bus de messages : la
 * propagation se declenche au milieu de l'enregistrement d'une page, et
 * repasser par le bus rouvrirait la meme transaction en la faisant se rappeler
 * elle-meme.
 */
final class MediaPropagator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MediaPropertyResolver $propertyResolver,
    ) {
    }

    /**
     * @param iterable<DimensionContentInterface> $dimensionContents toutes les
     *        dimensions de la page, locales et etapes confondues
     */
    public function propagate(
        iterable $dimensionContents,
        string $sourceLocale,
        bool $dryRun = false,
    ): PropagationResult {
        $result = new PropagationResult();

        $source = $this->findDraft($dimensionContents, $sourceLocale);
        if (!$source instanceof TemplateInterface) {
            return $result;
        }

        $templateKey = $source->getTemplateKey();
        if (null === $templateKey) {
            return $result;
        }

        $mediaProperties = $this->propertyResolver->resolve($templateKey);
        if ([] === $mediaProperties) {
            return $result;
        }

        $sourceData = $source->getTemplateData();

        foreach ($dimensionContents as $target) {
            if (!$this->isPropagationTarget($target, $sourceLocale)) {
                continue;
            }

            /** @var TemplateInterface&DimensionContentInterface $target */
            $locale = $target->getLocale();
            if (null === $locale) {
                continue;
            }

            $changed = $this->applyProperties($target, $sourceData, $mediaProperties, $dryRun);

            if ([] !== $changed) {
                $result->addLocale($locale, $changed);
            }

            // Le brouillon peut deja porter les bons medias alors que l'etape
            // publiee garde les anciens : c'est le cas apres une propagation
            // sans publication. La locale reste donc a republier.
            if ($this->isPublished($target)
                && ([] !== $changed || $this->liveDiffers($dimensionContents, $locale, $sourceData, $mediaProperties))
            ) {
                $result->addPublishedLocale($locale);
            }
        }

        if (!$dryRun && $result->hasChanges()) {
            $this->entityManager->flush();
        }

        return $result;
    }

    /**
     * @param iterable<DimensionContentInterface> $dimensionContents
     */
    private function findDraft(iterable $dimensionContents, string $locale): ?DimensionContentInterface
    {
        foreach ($dimensionContents as $content) {
            if ($content->getLocale() === $locale
                && DimensionContentInterface::STAGE_DRAFT === $content->getStage()
            ) {
                return $content;
            }
        }

        return null;
    }

    private function isPropagationTarget(DimensionContentInterface $content, string $sourceLocale): bool
    {
        if (!$content instanceof TemplateInterface) {
            return false;
        }

        if ($content->getLocale() === $sourceLocale || null === $content->getLocale()) {
            return false;
        }

        // Seul le brouillon est touche : ecrire l'etape publiee mettrait en
        // ligne une image sans passer par le flux de publication.
        return DimensionContentInterface::STAGE_DRAFT === $content->getStage();
    }

    /**
     * @param array<string, mixed> $sourceData
     * @param list<string>         $mediaProperties
     *
     * @return list<string> proprietes reellement modifiees
     */
    private function applyProperties(
        TemplateInterface $target,
        array $sourceData,
        array $mediaProperties,
        bool $dryRun,
    ): array {
        $targetData = $target->getTemplateData();
        $changed = [];

        foreach ($mediaProperties as $property) {
            if (!\array_key_exists($property, $sourceData)) {
                continue;
            }

            $value = $sourceData[$property];
            if (\array_key_exists($property, $targetData) && $targetData[$property] === $value) {
                continue;
            }

            $targetData[$property] = $value;
            $changed[] = $property;
        }

        if ([] !== $changed && !$dryRun) {
            $target->setTemplateData($targetData);
        }

        return $changed;
    }

    /**
     * L'etape publiee d'une locale porte-t-elle encore d'autres medias que la
     * reference ?
     *
     * @param iterable<DimensionContentInterface> $dimensionContents
     * @param array<string, mixed>                $sourceData
     * @param list<string>                        $mediaProperties
     */
    private function liveDiffers(
        iterable $dimensionContents,
        string $locale,
        array $sourceData,
        array $mediaProperties,
    ): bool {
        foreach ($dimensionContents as $content) {
            if ($content->getLocale() !== $locale
                || DimensionContentInterface::STAGE_LIVE !== $content->getStage()
                || !$content instanceof TemplateInterface
            ) {
                continue;
            }

            $liveData = $content->getTemplateData();
            foreach ($mediaProperties as $property) {
                if (!\array_key_exists($property, $sourceData)) {
                    continue;
                }

                if (($liveData[$property] ?? null) !== $sourceData[$property]) {
                    return true;
                }
            }

            return false;
        }

        return false;
    }

    private function isPublished(DimensionContentInterface $content): bool
    {
        if (!$content instanceof WorkflowInterface) {
            return false;
        }

        return \in_array($content->getWorkflowPlace(), [
            WorkflowInterface::WORKFLOW_PLACE_PUBLISHED,
            WorkflowInterface::WORKFLOW_PLACE_DRAFT,
        ], true);
    }
}
