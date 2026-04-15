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
