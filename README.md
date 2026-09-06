# SuluMediaSyncBundle

Share images across the locales of a Sulu page.

An image picked on the reference locale is copied to the other locales on save.
Texts stay translated, and media metadata (title, description, alt text) stays
translatable locale by locale in the media library.

Sharing is switched on and off from the admin, without a deploy.

## Why

Sulu stores a media selection in the page content, so one row per locale.
Picking an image in French does not touch English: that is deliberate, an image
may have to differ per market.

When it does not, and editing always happens in the same language, copying by
hand becomes a chore. This bundle automates it while keeping the escape hatch:
turn sharing off the day a locale needs its own image.

## Installation

```bash
composer require ahmed-bhs/sulu-media-sync-bundle
```

The bundle is registered by Flex. Otherwise, in `config/bundles.php`:

```php
Ahmed\SuluMediaSyncBundle\SuluMediaSyncBundle::class => ['all' => true],
```

Then create the settings table:

```bash
php bin/console doctrine:schema:update --force
```

## Configuration

Everything is optional. Defaults:

```yaml
# config/packages/sulu_media_sync.yaml
sulu_media_sync:
    enabled: true
    source_locale: 'fr'
    template_directories:
        - '%kernel.project_dir%/config/templates/pages'
```

`enabled` and `source_locale` are only the initial values: once a setting is
changed in the admin, the database wins.

## Usage

### From the admin

`Settings` > `Shared media across locales`: a checkbox to turn sharing on, and
a field for the reference locale.

### On save

Once enabled, saving a page in the reference locale copies its media
properties to the other locales.

Only drafts are written. A locale already online is then republished, so the
site shows the new image; a locale still in draft stays that way, so text
nobody approved is never published.

### Catching up existing pages

The subscriber only handles pages saved after it was switched on. For the rest:

```bash
php bin/console media-sync:propagate --dry-run   # show what would change
php bin/console media-sync:propagate             # update the drafts
php bin/console media-sync:propagate --publish   # also put back online
```

Options: `--source=en` to use a reference locale other than the stored setting.

## What is propagated

Top-level properties typed `media_selection`, `single_media_selection` and
`image_map`.

What is not:

- **Media nested inside a block.** They belong to their block, and copying the
  whole block would mix translated text with the image.
- **Texts, SEO, excerpt.** Nothing but media properties is touched.
- **Media metadata.** It lives on the media, not on the page, and stays
  translatable.

## Alt texts

The bundle shares the *selection*, not the *rendering*. If your templates
hardcode alt texts instead of reading `media.title`, translating metadata in
the media library will have no effect on the site. That is independent of
image sharing.

## Limitations

- Pages only: articles and snippets are not covered.
- Media nested inside blocks are not propagated.
- The service API may still change before 1.0.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## License

MIT
