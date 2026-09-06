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
 * Propagates media from the reference locale whenever a page is saved there.
 *
 * Propagation only writes the draft of the other locales. A locale already
 * online is then republished, otherwise the site would keep showing the old
 * image; a locale still in draft stays that way, so that text nobody approved
 * is never put online.
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
     * Publishing the reference locale must propagate too: an editor who
     * changes an image and publishes straight away never goes through a
     * separate save.
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
        // Propagation republishes the other locales, which fires this
        // subscriber again: without this lock, every save would recurse.
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
            // The page being saved only carries the locale under edit, so
            // the others are loaded again to be updated.
            $dimensionContents = $this->dimensionContentLoader->loadAll($page, $dimensionContentClass);

            $result = $this->propagator->propagate($dimensionContents, $sourceLocale);

            if (!$result->hasChanges() && [] === $result->getPublishedLocales()) {
                return;
            }

            // Publishing reopens the bus, which is not allowed while the
            // current save is still running, so it is deferred to the end of
            // the request.
            $uuid = method_exists($page, 'getUuid') ? $page->getUuid() : null;
            if (\is_string($uuid) && [] !== $result->getPublishedLocales()) {
                $this->pendingPublications[$uuid] = $result->getPublishedLocales();
            }

            $this->logger->info('Media propagated from the reference locale.', [
                'source_locale' => $sourceLocale,
                'locales' => array_keys($result->getLocales()),
                'republished' => $result->getPublishedLocales(),
            ]);
        } catch (\Throwable $exception) {
            // A propagation failure must never break the save the editor
            // just performed.
            $this->logger->error('Media propagation failed.', [
                'source_locale' => $sourceLocale,
                'exception' => $exception,
            ]);
        } finally {
            $this->propagating = false;
        }
    }

    /**
     * The bundle only knows about pages: other content resources (articles,
     * snippets) have their own entities and are ignored.
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
     * Puts the updated locales back online once the response has been sent.
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
            $this->logger->error('Republishing the locales failed.', ['exception' => $exception]);
        } finally {
            $this->propagating = false;
        }
    }
}
