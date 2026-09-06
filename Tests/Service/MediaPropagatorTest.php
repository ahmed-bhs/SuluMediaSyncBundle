<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Tests\Service;

use Ahmed\SuluMediaSyncBundle\Service\MediaPropagator;
use Ahmed\SuluMediaSyncBundle\Service\MediaPropertyResolver;
use Ahmed\SuluMediaSyncBundle\Tests\Double\FakeDimensionContent;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Sulu\Content\Domain\Model\DimensionContentInterface;
use Sulu\Content\Domain\Model\WorkflowInterface;

class MediaPropagatorTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/media-sync-' . uniqid('', true);
        mkdir($this->directory);
        file_put_contents($this->directory . '/homepage.xml', <<<'XML'
            <?xml version="1.0" ?>
            <template xmlns="http://schemas.sulu.io/template/template">
                <key>homepage</key>
                <properties>
                    <property name="title" type="text_line"/>
                    <property name="heroImage" type="media_selection"/>
                </properties>
            </template>
            XML);
    }

    protected function tearDown(): void
    {
        unlink($this->directory . '/homepage.xml');
        rmdir($this->directory);
    }

    public function testItCopiesMediaFromTheReferenceLocale(): void
    {
        $source = $this->draft('fr', ['title' => 'Accueil', 'heroImage' => ['ids' => [1, 2]]]);
        $target = $this->draft('en', ['title' => 'Home', 'heroImage' => ['ids' => [9]]]);

        $result = $this->propagator()->propagate([$source, $target], 'fr');

        $this->assertSame(['ids' => [1, 2]], $target->getTemplateData()['heroImage']);
        $this->assertSame(['en' => ['heroImage']], $result->getLocales());
    }

    public function testItLeavesTranslatedTextsUntouched(): void
    {
        $source = $this->draft('fr', ['title' => 'Accueil', 'heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['title' => 'Home', 'heroImage' => ['ids' => [9]]]);

        $this->propagator()->propagate([$source, $target], 'fr');

        $this->assertSame('Home', $target->getTemplateData()['title']);
    }

    public function testItNeverWritesTheReferenceLocale(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['heroImage' => ['ids' => [9]]]);

        $result = $this->propagator()->propagate([$source, $target], 'fr');

        $this->assertArrayNotHasKey('fr', $result->getLocales());
    }

    public function testItNeverWritesTheLiveStage(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $live = new FakeDimensionContent('en', DimensionContentInterface::STAGE_LIVE, 'homepage', ['heroImage' => ['ids' => [9]]]);

        $this->propagator()->propagate([$source, $live], 'fr');

        $this->assertSame(['ids' => [9]], $live->getTemplateData()['heroImage']);
    }

    public function testItReportsNoChangeWhenMediaAlreadyMatch(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['heroImage' => ['ids' => [1]]]);

        $result = $this->propagator()->propagate([$source, $target], 'fr');

        $this->assertFalse($result->hasChanges());
    }

    public function testItMarksAPublishedLocaleWhoseLiveStageStillDiffers(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['heroImage' => ['ids' => [1]]], WorkflowInterface::WORKFLOW_PLACE_PUBLISHED);
        $live = new FakeDimensionContent('en', DimensionContentInterface::STAGE_LIVE, 'homepage', ['heroImage' => ['ids' => [9]]]);

        $result = $this->propagator()->propagate([$source, $target, $live], 'fr');

        $this->assertSame(['en'], $result->getPublishedLocales());
    }

    public function testItLeavesAnUnpublishedLocaleOffline(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['heroImage' => ['ids' => [9]]], WorkflowInterface::WORKFLOW_PLACE_UNPUBLISHED);

        $result = $this->propagator()->propagate([$source, $target], 'fr');

        $this->assertSame([], $result->getPublishedLocales());
    }

    public function testDryRunReportsWithoutWriting(): void
    {
        $source = $this->draft('fr', ['heroImage' => ['ids' => [1]]]);
        $target = $this->draft('en', ['heroImage' => ['ids' => [9]]]);

        $result = $this->propagator()->propagate([$source, $target], 'fr', true);

        $this->assertTrue($result->hasChanges());
        $this->assertSame(['ids' => [9]], $target->getTemplateData()['heroImage']);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function draft(string $locale, array $data, ?string $workflowPlace = null): FakeDimensionContent
    {
        return new FakeDimensionContent(
            $locale,
            DimensionContentInterface::STAGE_DRAFT,
            'homepage',
            $data,
            $workflowPlace,
        );
    }

    private function propagator(): MediaPropagator
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);

        return new MediaPropagator($entityManager, new MediaPropertyResolver([$this->directory]));
    }
}
