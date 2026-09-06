<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Tests\Double;

use Sulu\Page\Domain\Model\Page;
use Sulu\Page\Domain\Model\PageDimensionContent;

/**
 * Une dimension de page reelle, construite sans base de donnees.
 *
 * Etendre l'entite plutot que de reimplementer ses interfaces garde le test
 * aligne sur le contrat que la propagation rencontre en production.
 */
class FakeDimensionContent extends PageDimensionContent
{
    /**
     * @param array<string, mixed> $templateData
     */
    public function __construct(
        string $locale,
        string $stage,
        string $templateKey,
        array $templateData,
        ?string $workflowPlace = null,
    ) {
        parent::__construct(new Page());

        $this->setLocale($locale);
        $this->setStage($stage);
        $this->setTemplateKey($templateKey);
        $this->setTemplateData($templateData);

        if (null !== $workflowPlace) {
            $this->setWorkflowPlace($workflowPlace);
        }
    }
}
