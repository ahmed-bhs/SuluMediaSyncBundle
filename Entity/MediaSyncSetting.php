<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Entity;

class MediaSyncSetting
{
    private string $name;

    private string $value;

    private \DateTimeImmutable $changed;

    public function __construct(string $name, string $value)
    {
        $this->name = $name;
        $this->value = $value;
        $this->changed = new \DateTimeImmutable();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
        $this->changed = new \DateTimeImmutable();
    }

    public function getChanged(): \DateTimeImmutable
    {
        return $this->changed;
    }
}
