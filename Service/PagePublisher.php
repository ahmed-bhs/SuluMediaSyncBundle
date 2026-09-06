<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Sulu\Messenger\Infrastructure\Symfony\Messenger\FlushMiddleware\EnableFlushStamp;
use Sulu\Page\Application\Message\ApplyWorkflowTransitionPageMessage;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\HandleTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Remet en ligne les locales qui y etaient deja.
 *
 * Une image propagee n'atteint le public qu'apres publication : sans cette
 * etape, le brouillon porterait la nouvelle image et le site l'ancienne.
 */
final class PagePublisher
{
    use HandleTrait;

    public function __construct(
        MessageBusInterface $messageBus,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->messageBus = $messageBus;
    }

    /**
     * @param list<string> $locales
     */
    public function publish(string $uuid, array $locales): void
    {
        foreach ($locales as $locale) {
            // L'enregistrement qui precede laisse en memoire une page limitee a
            // la locale editee : sans purge, le gestionnaire de publication ne
            // verrait pas la locale a remettre en ligne.
            $this->entityManager->clear();

            $this->handle(new Envelope(
                new ApplyWorkflowTransitionPageMessage(
                    ['uuid' => $uuid],
                    $locale,
                    WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH,
                ),
                [new EnableFlushStamp()],
            ));
        }
    }
}
