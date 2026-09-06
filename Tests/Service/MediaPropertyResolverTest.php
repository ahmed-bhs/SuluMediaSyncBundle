<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Tests\Service;

use Ahmed\SuluMediaSyncBundle\Service\MediaPropertyResolver;
use PHPUnit\Framework\TestCase;

class MediaPropertyResolverTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/media-sync-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*.xml') ?: [] as $file) {
            unlink($file);
        }

        rmdir($this->directory);
    }

    public function testItFindsMediaPropertiesByType(): void
    {
        $this->writeTemplate('homepage', <<<'XML'
            <property name="title" type="text_line"/>
            <property name="heroImage" type="media_selection"/>
            <property name="poster" type="single_media_selection"/>
            <property name="plan" type="image_map"/>
            XML);

        $this->assertSame(
            ['heroImage', 'poster', 'plan'],
            $this->resolver()->resolve('homepage'),
        );
    }

    public function testItIgnoresPropertiesNamedLikeMediaButTypedOtherwise(): void
    {
        $this->writeTemplate('firm', <<<'XML'
            <property name="photo" type="text_line"/>
            <property name="imageCaption" type="text_area"/>
            XML);

        $this->assertSame([], $this->resolver()->resolve('firm'));
    }

    public function testItLeavesMediaNestedInBlocksAlone(): void
    {
        $this->writeTemplate('team', <<<'XML'
            <property name="heroImage" type="media_selection"/>
            <block name="members" default-type="member">
                <types>
                    <type name="member">
                        <properties>
                            <property name="portrait" type="media_selection"/>
                        </properties>
                    </type>
                </types>
            </block>
            XML);

        $this->assertSame(['heroImage'], $this->resolver()->resolve('team'));
    }

    public function testItReturnsNothingForAnUnknownTemplate(): void
    {
        $this->assertSame([], $this->resolver()->resolve('does-not-exist'));
    }

    private function resolver(): MediaPropertyResolver
    {
        return new MediaPropertyResolver([$this->directory]);
    }

    private function writeTemplate(string $key, string $properties): void
    {
        file_put_contents($this->directory . '/' . $key . '.xml', <<<XML
            <?xml version="1.0" ?>
            <template xmlns="http://schemas.sulu.io/template/template">
                <key>{$key}</key>
                <properties>
                    {$properties}
                </properties>
            </template>
            XML);
    }
}
