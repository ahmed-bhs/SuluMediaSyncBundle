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
 * Puts back online the locales that already were.
 *
 * A propagated image only reaches the public once published: without this
 * step the draft would carry the new image and the site the old one.
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
            // The preceding save leaves in memory a page limited to the
            // edited locale: without clearing, the publish handler would not
            // see the locale to put back online.
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
