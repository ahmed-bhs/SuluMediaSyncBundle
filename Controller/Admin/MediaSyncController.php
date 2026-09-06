<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Controller\Admin;

use Ahmed\SuluMediaSyncBundle\Service\MediaSyncSettings;
use Sulu\Component\Security\Authorization\PermissionTypes;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Exposes the settings to the admin in the shape a Sulu form expects: a
 * single resource, read and written at the same path.
 */
class MediaSyncController
{
    public function __construct(
        private readonly MediaSyncSettings $settings,
        private readonly SecurityCheckerInterface $securityChecker,
    ) {
    }

    #[Route('/admin/api/media-sync-settings', name: 'sulu_media_sync.get_settings', methods: ['GET'])]
    public function get(): JsonResponse
    {
        $this->securityChecker->checkPermission('sulu.settings.media_sync', PermissionTypes::VIEW);

        return new JsonResponse($this->toArray());
    }

    #[Route('/admin/api/media-sync-settings', name: 'sulu_media_sync.put_settings', methods: ['PUT', 'POST'])]
    public function put(Request $request): JsonResponse
    {
        $this->securityChecker->checkPermission('sulu.settings.media_sync', PermissionTypes::EDIT);

        /** @var array<string, mixed> $payload */
        $payload = json_decode($request->getContent(), true) ?: [];

        if (\array_key_exists('enabled', $payload)) {
            $this->settings->setEnabled((bool) $payload['enabled']);
        }

        $sourceLocale = $payload['sourceLocale'] ?? null;
        if (\is_string($sourceLocale) && '' !== $sourceLocale) {
            $this->settings->setSourceLocale($sourceLocale);
        }

        return new JsonResponse($this->toArray());
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(): array
    {
        return [
            'id' => 'media_sync',
            'enabled' => $this->settings->isEnabled(),
            'sourceLocale' => $this->settings->getSourceLocale(),
        ];
    }
}
