<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Command;

use Ahmed\SuluMediaSyncBundle\Service\MediaPropagator;
use Ahmed\SuluMediaSyncBundle\Service\MediaSyncSettings;
use Ahmed\SuluMediaSyncBundle\Service\PagePublisher;
use Sulu\Page\Domain\Model\PageInterface;
use Sulu\Page\Domain\Repository\PageRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Catches up pages created before propagation was switched on.
 *
 * The subscriber only handles pages saved afterwards: without this command an
 * existing site would keep its diverging selections until an editor reopened
 * every page.
 */
#[AsCommand(
    name: 'media-sync:propagate',
    description: 'Copies media from the reference locale to the other locales.',
)]
final class SyncMediaCommand extends Command
{
    public function __construct(
        private readonly PageRepositoryInterface $pageRepository,
        private readonly MediaPropagator $propagator,
        private readonly MediaSyncSettings $settings,
        private readonly PagePublisher $publisher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show the changes without writing them.');
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Reference locale (defaults to the stored setting).');
        $this->addOption('publish', null, InputOption::VALUE_NONE, 'Republish the locales that were already online.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $ui = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $publish = (bool) $input->getOption('publish');
        $sourceLocale = (string) ($input->getOption('source') ?? '') ?: $this->settings->getSourceLocale();

        $pages = $this->pageRepository->findBy([]);
        $rows = [];
        $touched = 0;

        foreach ($pages as $page) {
            if (!$page instanceof PageInterface) {
                continue;
            }

            $result = $this->propagator->propagate($page->getDimensionContents(), $sourceLocale, $dryRun);

            $toPublish = $publish ? $result->getPublishedLocales() : [];
            if (!$result->hasChanges() && [] === $toPublish) {
                continue;
            }

            ++$touched;

            foreach ($result->getLocales() as $locale => $properties) {
                $rows[] = [$page->getUuid(), $locale, implode(', ', $properties)];
            }

            // A locale whose draft was already right but whose live stage
            // predates the propagation: nothing to write, everything to publish.
            foreach ($toPublish as $locale) {
                if (!\array_key_exists($locale, $result->getLocales())) {
                    $rows[] = [$page->getUuid(), $locale, 'republish'];
                }
            }

            if (!$dryRun && [] !== $toPublish) {
                $this->publisher->publish($page->getUuid(), $toPublish);
            }
        }

        if ([] === $rows) {
            $ui->success(\sprintf('Nothing to propagate from "%s".', $sourceLocale));

            return Command::SUCCESS;
        }

        $ui->table(['Page', 'Locale', 'Properties'], $rows);

        if ($dryRun) {
            $ui->note(\sprintf('%d page(s) would change. Run again without --dry-run to apply.', $touched));

            return Command::SUCCESS;
        }

        $ui->success(\sprintf('%d page(s) updated from "%s".', $touched, $sourceLocale));

        if (!$publish) {
            $ui->note('Drafts are up to date. Add --publish to put the already published locales back online.');
        }

        return Command::SUCCESS;
    }
}
