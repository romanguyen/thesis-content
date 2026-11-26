# MetaCentrum Wiki Content Repository

This repository stores versioned DokuWiki content for offline editing,
review, and controlled sync to live wiki host.

## Scope

- tracked pages: `data/pages/**`
- tracked media: `data/media/**`
- excluded demo/default content: `data/pages/wiki/**`, `data/pages/playground/**`, `data/media/wiki/**`

## Offline contributor workflow

```bash
git checkout -b feat/update-page
# edit files under data/pages/** and approved data/media/**
php scripts/git_pr_check.php --repo-root=. --mode=changed --profile=content-repo --include-untracked
git add -A
git commit -m "Update wiki content"
git push origin feat/update-page
```

## Host sync commands

```bash
# push host-local commits to remote
php scripts/git_sync_push.php --repo=/srv/metacentrum-wiki-content --remote=origin --branch=main

# pull merged remote commits to host clone and reindex wiki
php scripts/git_sync_pull.php --repo=/srv/metacentrum-wiki-content --remote=origin --branch=main --reindex-cmd='php bin/indexer.php -q' --dokuwiki-root=/srv/dokuwiki
```

## Selective one-way sync pilot (hardware)

Canonical source snapshot is kept in `scripts/hardware_clusters.json`.

Regenerate pages:

```bash
php scripts/git_hardware_sync.php
```

Generated targets:

- `data/pages/en/resources/hardware_git_sync.txt`
- `data/pages/cs/resources/hardware_git_sync.txt`

## T2 representative content repairs (16 November 2026)

Application/template changes and browser drivers remain in sibling `dokuwiki`; the bounded result and source identities are recorded in `../thesis-writing/T2_INTERFACE_AND_CONTENT.md`.

- CS/EN news retain their imported dates, heading IDs and original permalinks. Their new introduction explicitly identifies the historical archive, not current announcements. The template now preserves this native introduction.
- Contact pages identify addresses/travel instructions/maps as archival. Three unsupported inline PNG data URIs in each language now use the **same decoded bytes** as native media. Original Mapy links remain. Two unavailable remote Prague thumbnails are replaced by descriptive historical source links; link availability and current addresses are not certified. The phone number is plain text because the native parser treated `tel:` as a missing wiki page; no global URL-scheme configuration was changed.
- CS/EN hardware pages now explicitly say the snapshot date/provenance and current machine availability are unverified. The former MetaVO inventory URL was observed redirecting to `https://my.metacentrum.cz/`; the current-resource link now names that general portal, while original source/cluster references are retained as historical and may redirect there rather than to a machine profile.
- The existing CS/EN elements showcase now labels sample metrics/cards as demonstrations, uses the current MetaCentrum layout name, and includes native video/image examples. Maps are **static embedded images**, not interactive map frames. No iframe plugin or raw HTML permission was added.

### Replacement references (no page IDs renamed)

| Previous reference | Replacement |
| --- | --- |
| CS/EN contacts: Prague / České Budějovice / Plzeň inline PNG data | `:contacts:prague-map.png` / `:contacts:ceske-budejovice-map.png` / `:contacts:plzen-map.png` |
| EN About links `en:about:meta`, `en:devel`, `en:devel:virtualization`, `en:results` | The corresponding existing `:start` page IDs |
| CS About/contact links `cs:vo`, and CS About `cs:vo:auger` | `cs:vo:start`, `cs:vo:auger:start` |
| CS MetaVO grid link `cs:sluzby:grid` | `cs:sluzby:grid:start`, with a descriptive grid-service label |
| CS/EN contact link parsed as `tel:420234680222` | Readable `+420 234 680 222` text, not a fabricated wiki destination |

### Media provenance and limits

The contact PNGs were decoded from both pre-T2 authored contact pages and checked byte-for-byte between languages. Their SHA-256s are `092681b1c9c460f8aa34e6234278011602e24ee6af90581fef22e41e8178be54` (Prague), `f180967a86e792233708fbc7085519c5b6df31728dcf956426607119e00a66bd` (České Budějovice), and `08f1244fe282496d4d16a394c687c8b8d2ef4b1af75ddb6d731bf7ccd8c5dadf` (Plzeň). This preserves existing assets; it does **not** establish their original supplier, date or redistribution permission. Those decisions remain open in T0/D4.

`data/media/demo/embedding-sample.webm` is a generated two-second silent blue frame, not infrastructure footage or third-party media. Adjacent CS/EN prose describes its entire content. It needs no autoplay, external video service or runtime FFmpeg installation. Reproduction command (FFmpeg was already installed):

```sh
ffmpeg -hide_banner -loglevel error -f lavfi \
  -i 'color=c=0x204A70:s=480x270:r=10:d=2' -an -c:v libvpx -b:v 50k \
  data/media/demo/embedding-sample.webm
```

The illustrative server-room image was already present in this repository; the showcase does not claim it depicts a particular site. Media/source permissions, a full content migration, current external service availability and two separately deployed websites have not been approved or certified by these local repairs.
