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

Generated targets (kept outside `data/pages/`, so they are not public wiki pages;
the public hardware page is `resources:hardware:start`):

- `scripts/generated/hardware_git_sync.en.txt`
- `scripts/generated/hardware_git_sync.cs.txt`

The `Hardware Sync Check` workflow regenerates both files and fails if they differ from the committed copies.

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

## Lockdown content changes (26 November 2026)

### Media provenance (lockdown)

Downloaded on 2026-11-26 so pages no longer load them from other sites. The bytes are unchanged; this records where
they came from, not a redistribution permission.

| Local media | Used on | Source URL | SHA-256 |
| --- | --- | --- | --- |
| `:resources:metacentrum-hw-map-2016.png` (400×256 PNG) | `cs:start`, `en:start` (HW map) | `https://www.metacentrum.cz/export/sites/metacentrum/images/mapkaMC_2016_oranz.png_20180039.png` | `0ded4a75c2a1fc783bd564bce61c25cf1e970964f929766c3189a0a27edb05c8` |
| `:news:2026:webinar-metacentrum-cloud-banner.png` (1200×240 PNG) | `news:2026-11-12-webinar-metacentrum-cloud` (cs, en) | `https://metavo.metacentrum.cz/export/sites/meta/images/other/Webinar_bannerx12x.png_524804141.png` | `56242690435c0008f9735218087a0012f2a99d3f59a790a92360bbf316788971` |
| `:news:2026:scratch-types-overview.png` (801×447 PNG) | `news:2026-10-16-multijob-scratch` (cs, en) | `https://metavo.metacentrum.cz/export/sites/meta/images/other/image-scratch-en.png_2027164944.png` | `8b2e3bb7d2e7b5e47ee7f5ce17a14b9495824162bbccbfc83dd3c048e0b48615` |
| `:news:2026:webinar-local-software-installation-banner.png` (800×160 PNG) | `news:2026-10-03-webinar-local-software-installation` (cs, en) | `https://metavo.metacentrum.cz/export/sites/meta/images/other/webinar4_banner.png_1380295843.png` | `e8eeb282e57c87b5b3d32d5969e0a43194716da080993a6bf07a0fab6f058ff2` |
| `:news:2026:seminar-metacentrum-2026-cs.png` (800×400 PNG) | `cs:news:2026-09-02-metacentrum-seminar-cesnet-conference-2026` | `https://metavo.metacentrum.cz/export/sites/meta/images/other/seminar-metacentrum5-mensi.png_1380298540.png` | `3f5814bdc2910ba24805be537f696dfb7c0634a95d1b7f5e87b0f793a177b09a` |
| `:news:2026:seminar-metacentrum-2026-en.jpg` (800×400 JPEG) | `en:news:2026-09-02-metacentrum-seminar-cesnet-conference-2026` | `https://metavo.metacentrum.cz/export/sites/meta/images/other/seminar-metacentra5-EN.jpg_1380298540.jpg` | `e6c265664934b68dddd666722950996944dbf09e79317fce139ef039321f2bfa` |

### News text provenance (lockdown)

The six newest articles in each language were taken on 2026-11-26 from the MetaVO news lists
`https://metavo.metacentrum.cz/cs/news/news.jsp` and `https://metavo.metacentrum.cz/en/news/news.jsp`.
Each article is the page `news:YYYY-MM-DD-name` (date from the MetaVO byline) and ends with a link to its original
`https://metavo.metacentrum.cz/{cs,en}/news/novinka_2026_00NN.html`:

| Page name (cs and en) | MetaVO item |
| --- | --- |
| `2026-11-12-webinar-metacentrum-cloud` | `novinka_2026_0027` |
| `2026-10-27-webinar-local-software-installation-recording` | `novinka_2026_0026` |
| `2026-10-16-multijob-scratch` | `novinka_2026_0024` |
| `2026-10-08-metacentrum-seminar-2026-recap` | `novinka_2026_0023` |
| `2026-10-03-webinar-local-software-installation` | `novinka_2026_0022` |
| `2026-09-02-metacentrum-seminar-cesnet-conference-2026` | `novinka_2026_0021` |

The HTML was converted to DokuWiki syntax (inline styles, empty blocks and spacers dropped; headings, lists, bold
text, tables and links kept; relative MetaVO links made absolute). The two embedded YouTube players were left out
because the wiki allows no iframes; the playlist link in the same article remains. Obvious typos fixed: cs
"opatřetn" → "opatřen", "odkazy ne předchozí" → "odkazy na předchozí", "školících" → "školicích", "titulky: ." →
"titulky.", "Ve dne ch" → "Ve dnech"; en "vidorecording" → "video recording", "opertations" → "operations",
"**D**etailed" link text joined; titles of item 0021 "(30. 9. -1. 10. 2026)" → "(30. 9. – 1. 10. 2026)".
Older articles (2010–2013) stay as the historical archive.
