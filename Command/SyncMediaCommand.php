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
 * Rattrape les pages creees avant l'activation de la propagation.
 *
 * L'abonne ne traite que les pages enregistrees apres coup : sans cette
 * commande, un site existant garderait ses selections divergentes jusqu'a ce
 * qu'un redacteur rouvre chaque page.
 */
#[AsCommand(
    name: 'media-sync:propagate',
    description: 'Recopie les medias de la locale de reference vers les autres locales.',
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
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Montre les changements sans les ecrire.');
        $this->addOption('source', null, InputOption::VALUE_REQUIRED, 'Locale de reference (defaut : celle du reglage).');
        $this->addOption('publish', null, InputOption::VALUE_NONE, 'Republie les locales qui etaient deja en ligne.');
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

            // Une locale dont le brouillon etait deja bon mais dont la mise en
            // ligne date d'avant la propagation : rien a ecrire, tout a publier.
            foreach ($toPublish as $locale) {
                if (!\array_key_exists($locale, $result->getLocales())) {
                    $rows[] = [$page->getUuid(), $locale, 'republication'];
                }
            }

            if (!$dryRun && [] !== $toPublish) {
                $this->publisher->publish($page->getUuid(), $toPublish);
            }
        }

        if ([] === $rows) {
            $ui->success(\sprintf('Rien a propager depuis "%s".', $sourceLocale));

            return Command::SUCCESS;
        }

        $ui->table(['Page', 'Locale', 'Proprietes'], $rows);

        if ($dryRun) {
            $ui->note(\sprintf('%d page(s) seraient modifiees. Relancer sans --dry-run pour appliquer.', $touched));

            return Command::SUCCESS;
        }

        $ui->success(\sprintf('%d page(s) mises a jour depuis "%s".', $touched, $sourceLocale));

        if (!$publish) {
            $ui->note('Les brouillons sont a jour. Ajouter --publish pour remettre en ligne les locales deja publiees.');
        }

        return Command::SUCCESS;
    }
}
