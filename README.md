# Fencetag : blocs ``` pour toutes les balises

Plugin DokuWiki (`fencetag`).

Permet d'écrire un bloc Markdown **```` ```balise attributs ````** à la place de `<balise attributs> … </balise>`, pour toute balise ajoutée par un plugin (`mermaid`, `graphviz`, `wrap`, `fmtable`…), comme dans Obsidian.

## Fonctionnement

La conversion se fait sur le texte avant l'analyse (événement `PARSER_WIKITEXT_PREPROCESS`) : les plugins ne voient que la forme habituelle.

- Les balises sont **détectées automatiquement** en sondant les motifs `<balise…` des plugins de syntaxe (résultat gardé dans `data/cache/fencetag.json`).
- Un bloc dont le nom n'est pas une balise (` ```python `, ` ```php `, ` ```html `) reste un bloc de code.
- Les zones littérales (`<code>`, `<file>`, `<nowiki>`, `<html>`, `<php>`, `%%…%%`, blocs `~~~`) ne sont jamais converties.

## Configuration

| Réglage | Défaut | Rôle |
| --- | --- | --- |
| `auto` | oui | détection automatique des balises |
| `extra` | `nowiki,file` | balises supplémentaires (par exemple celles du noyau) |
| `exclude` | | balises à ne jamais convertir |

## Dépendances

Aucune.

## Installation

Copier le contenu de ce dépôt dans `lib/plugins/fencetag/` (le fichier `plugin.info.txt` doit se trouver à la racine de ce dossier), puis vider le cache en rendant la date de `conf/local.php` plus récente (`touch conf/local.php`).

Avec le gestionnaire d'extensions de DokuWiki : *Administration → Gestionnaire d'extensions → Installer*, en indiquant l'adresse du dépôt (archive ZIP).

## Licence

Aucune licence n'a encore été choisie : à ajouter (fichier `LICENSE`) avant de publier le dépôt. DokuWiki est sous GPL-2.0.
