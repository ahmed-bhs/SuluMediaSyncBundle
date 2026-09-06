<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Subscriber;

use Ahmed\SuluMediaSyncBundle\Service\DimensionContentLoader;
use Ahmed\SuluMediaSyncBundle\Service\MediaPropagator;
use Ahmed\SuluMediaSyncBundle\Service\MediaSyncSettings;
use Ahmed\SuluMediaSyncBundle\Service\PagePublisher;
use Psr\Log\LoggerInterface;
use Sulu\Page\Domain\Event\PageModifiedEvent;
use Sulu\Page\Domain\Model\PageDimensionContent;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Event\PageWorkflowTransitionAppliedEvent;
use Sulu\Content\Domain\Model\WorkflowInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Propage les medias de la locale de reference des qu'une page y est modifiee.
 *
 * La propagation n'ecrit que le brouillon des autres locales. Une langue deja
 * en ligne est ensuite republiee, sinon le site continuerait d'afficher
 * l'ancienne image ; une langue encore en brouillon le reste, pour ne pas
 * mettre en ligne un texte que personne n'a valide.
 */
final class PropagateMediaSubscriber implements EventSubscriberInterface
{
    private bool $propagating = false;

    /** @var array<string, list<string>> */
    private array $pendingPublications = [];

    public function __construct(
        private readonly MediaSyncSettings $settings,
        private readonly MediaPropagator $propagator,
        private readonly PagePublisher $publisher,
        private readonly DimensionContentLoader $dimensionContentLoader,
        private readonly LoggerInterface $logger,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PageModifiedEvent::class => 'onPageModified',
            PageWorkflowTransitionAppliedEvent::class => 'onWorkflowTransition',
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }

    public function onPageModified(PageModifiedEvent $event): void
    {
        $this->handle($event->getPage(), $event->getResourceLocale());
    }

    /**
     * Publier la locale de reference doit propager aussi : le redacteur qui
     * change une image et publie dans la foulee ne repasse pas par un
     * enregistrement distinct.
     */
    public function onWorkflowTransition(PageWorkflowTransitionAppliedEvent $event): void
    {
        if (WorkflowInterface::WORKFLOW_TRANSITION_PUBLISH !== $event->getWorkflowTransitionName()) {
            return;
        }

        $this->handle($event->getPage(), $event->getResourceLocale());
    }

    private function handle(object $page, ?string $locale): void
    {
        // La propagation republie les autres locales, ce qui redeclenche cet
        // abonne : sans ce verrou, chaque enregistrement se rappellerait.
        if ($this->propagating) {
            return;
        }

        if (null === $locale || !$this->settings->isEnabled()) {
            return;
        }

        $sourceLocale = $this->settings->getSourceLocale();
        if ($locale !== $sourceLocale) {
            return;
        }

        $dimensionContentClass = $this->resolveDimensionContentClass($page);
        if (null === $dimensionContentClass) {
            return;
        }

        $this->propagating = true;

        try {
            // La page en cours d'enregistrement ne porte que la locale editee :
            // les autres sont relues pour pouvoir etre mises a jour.
            $dimensionContents = $this->dimensionContentLoader->loadAll($page, $dimensionContentClass);

            $result = $this->propagator->propagate($dimensionContents, $sourceLocale);

            if (!$result->hasChanges() && [] === $result->getPublishedLocales()) {
                return;
            }

            // La publication rouvre le bus, ce qui est interdit tant que
            // l'enregistrement courant n'est pas termine : elle est remise a
            // la fin de la requete.
            $uuid = method_exists($page, 'getUuid') ? $page->getUuid() : null;
            if (\is_string($uuid) && [] !== $result->getPublishedLocales()) {
                $this->pendingPublications[$uuid] = $result->getPublishedLocales();
            }

            $this->logger->info('Medias propages depuis la locale de reference.', [
                'source_locale' => $sourceLocale,
                'locales' => array_keys($result->getLocales()),
                'republished' => $result->getPublishedLocales(),
            ]);
        } catch (\Throwable $exception) {
            // Un echec de propagation ne doit jamais empecher l'enregistrement
            // de la page que le redacteur vient de faire.
            $this->logger->error('Echec de la propagation des medias.', [
                'source_locale' => $sourceLocale,
                'exception' => $exception,
            ]);
        } finally {
            $this->propagating = false;
        }
    }

    /**
     * Le bundle ne connait que les pages : les autres ressources de contenu
     * (articles, snippets) suivent leurs propres entites et sont ignorees.
     *
     * @return class-string|null
     */
    private function resolveDimensionContentClass(object $page): ?string
    {
        if (!$page instanceof PageInterface) {
            return null;
        }

        return PageDimensionContent::class;
    }

    /**
     * Remet en ligne les locales mises a jour, une fois la reponse envoyee.
     */
    public function onTerminate(TerminateEvent $event): void
    {
        $this->flushPendingPublications();
    }

    public function flushPendingPublications(): void
    {
        $pending = $this->pendingPublications;
        $this->pendingPublications = [];

        if ([] === $pending) {
            return;
        }

        $this->propagating = true;

        try {
            foreach ($pending as $uuid => $locales) {
                $this->publisher->publish($uuid, $locales);
            }
        } catch (\Throwable $exception) {
            $this->logger->error('Echec de la republication des locales.', ['exception' => $exception]);
        } finally {
            $this->propagating = false;
        }
    }
}
