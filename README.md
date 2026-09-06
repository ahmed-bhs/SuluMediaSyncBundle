# SuluMediaSyncBundle

Partage les images entre les langues d'une page Sulu.

Une image choisie sur la langue de reference est recopiee sur les autres
langues a l'enregistrement. Les textes restent traduits, et les metadonnees des
medias (titre, description, texte alternatif) restent traduisibles langue par
langue dans la mediatheque.

Le partage s'active et se coupe depuis l'administration, sans deploiement.

## Pourquoi

Sulu stocke la selection d'un media dans le contenu de la page, donc une ligne
par langue. Choisir une image en francais ne touche pas l'anglais : c'est
voulu, une image peut devoir changer selon le marche.

Quand ce n'est pas le cas et que la redaction se fait toujours dans la meme
langue, la recopie manuelle devient une corvee. Ce bundle l'automatise, tout en
gardant l'echappatoire : on desactive le partage le jour ou une langue doit
porter sa propre image.

## Installation

```bash
composer require ahmed/sulu-media-sync-bundle
```

Le bundle est enregistre par Flex. Sinon, dans `config/bundles.php` :

```php
Ahmed\SuluMediaSyncBundle\SuluMediaSyncBundle::class => ['all' => true],
```

Puis creer la table de reglages :

```bash
php bin/console doctrine:schema:update --force
```

## Configuration

Tout est optionnel. Valeurs par defaut :

```yaml
# config/packages/sulu_media_sync.yaml
sulu_media_sync:
    enabled: true
    source_locale: 'fr'
    template_directories:
        - '%kernel.project_dir%/config/templates/pages'
```

`enabled` et `source_locale` ne sont que les valeurs initiales : des qu'un
reglage est change dans l'administration, c'est la base qui fait foi.

## Utilisation

### Depuis l'administration

`Reglages` > `Images partagees entre langues` : une case pour activer le
partage, un champ pour la langue de reference.

### A l'enregistrement

Une fois active, enregistrer une page dans la langue de reference recopie ses
proprietes media vers les autres langues.

Seuls les brouillons sont ecrits. Une langue deja en ligne est ensuite
republiee, pour que le site affiche bien la nouvelle image ; une langue encore
en brouillon le reste, afin de ne pas publier un texte que personne n'a valide.

### Rattraper les pages existantes

L'abonne ne traite que les pages enregistrees apres son activation. Pour les
autres :

```bash
php bin/console media-sync:propagate --dry-run   # montre ce qui changerait
php bin/console media-sync:propagate             # met les brouillons a jour
php bin/console media-sync:propagate --publish   # remet aussi en ligne
```

Options : `--source=en` pour une autre langue de reference que le reglage.

## Ce qui est propage

Les proprietes de premier niveau typees `media_selection`,
`single_media_selection` et `image_map`.

Ce qui ne l'est pas :

- **Les medias imbriques dans un bloc.** Ils suivent leur bloc, et recopier le
  bloc entier melangerait le texte traduit a l'image.
- **Les textes, le SEO, l'extrait.** Rien d'autre que les proprietes media
  n'est touche.
- **Les metadonnees des medias.** Elles vivent sur le media, pas sur la page,
  et restent traduisibles.

## Textes alternatifs

Le bundle partage la *selection*, pas le *rendu*. Si vos gabarits ecrivent les
textes alternatifs en dur plutot que de lire `media.title`, traduire les
metadonnees dans la mediatheque n'aura aucun effet sur le site. C'est
independant du partage d'images.

## Tests

```bash
composer install
vendor/bin/phpunit
```

## Licence

MIT
