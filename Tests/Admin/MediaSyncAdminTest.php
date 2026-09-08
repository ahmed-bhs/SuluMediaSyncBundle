<?php

declare(strict_types=1);

namespace Ahmed\SuluMediaSyncBundle\Tests\Admin;

use Ahmed\SuluMediaSyncBundle\Admin\MediaSyncAdmin;
use PHPUnit\Framework\TestCase;
use Sulu\Bundle\AdminBundle\Admin\View\ViewBuilderFactory;
use Sulu\Bundle\AdminBundle\Admin\View\ViewCollection;
use Sulu\Component\Security\Authorization\SecurityCheckerInterface;

/**
 * L'ecran de reglages est une page unique, sans identifiant dans l'URL.
 * Une vue Form isolee ne peut pas s'afficher : le composant React recoit
 * son resourceStore d'une vue parente, et sans elle il leve
 * « The view "Form" needs a resourceStore to work properly ».
 */
class MediaSyncAdminTest extends TestCase
{
    public function testTheSettingsFormHangsUnderAParentThatCarriesTheResourceStore(): void
    {
        $views = $this->configureViews();

        $form = $views->get(MediaSyncAdmin::SETTINGS_VIEW)->getView();

        $this->assertSame(
            MediaSyncAdmin::SETTINGS_TABS_VIEW,
            $form->getParent(),
            'Sans parent, l ecran de reglages reste blanc.',
        );
    }

    public function testTheParentPinsTheSingletonIdentifier(): void
    {
        $views = $this->configureViews();

        $tabs = $views->get(MediaSyncAdmin::SETTINGS_TABS_VIEW)->getView();

        $this->assertSame(
            'media_sync',
            $tabs->getAttributeDefault('id'),
            'L API ne renvoie qu un seul enregistrement : son identifiant doit etre fixe ici.',
        );
    }

    public function testTheMenuOpensTheParentAndNotTheForm(): void
    {
        $this->assertSame(
            'sulu_media_sync.settings',
            MediaSyncAdmin::SETTINGS_TABS_VIEW,
            'Le menu pointe cette vue : changer sa valeur casserait les liens existants.',
        );
    }

    private function configureViews(): ViewCollection
    {
        $securityChecker = $this->createStub(SecurityCheckerInterface::class);
        $securityChecker->method('hasPermission')->willReturn(true);

        $admin = new MediaSyncAdmin(new ViewBuilderFactory(), $securityChecker);

        $views = new ViewCollection();
        $admin->configureViews($views);

        return $views;
    }
}
