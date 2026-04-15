#!/usr/bin/env php
<?php

$options = parseOptions($argv);

if (!empty($options['help'])) {
    printUsage();
    exit(0);
}

$root = dirname(__DIR__);

$sourcePath = $options['source'] ?? ($root . '/scripts/hardware_clusters.json');
$outEn = $options['out-en'] ?? ($root . '/data/pages/en/resources/hardware_git_sync.txt');
$outCs = $options['out-cs'] ?? ($root . '/data/pages/cs/resources/hardware_git_sync.txt');

$sourceRealPath = resolvePath($root, $sourcePath);
if (!is_file($sourceRealPath)) {
    fwrite(STDERR, "hardware sync failed: source file not found ({$sourcePath})\n");
    exit(1);
}

$json = @file_get_contents($sourceRealPath);
if ($json === false) {
    fwrite(STDERR, "hardware sync failed: unable to read source file\n");
    exit(1);
}

$data = json_decode($json, true);
if (!is_array($data)) {
    fwrite(STDERR, "hardware sync failed: source is not valid JSON\n");
    exit(1);
}

$errors = validateSource($data);
if ($errors !== []) {
    fwrite(STDERR, "hardware sync failed:\n");
    foreach ($errors as $error) {
        fwrite(STDERR, "  {$error}\n");
    }
    exit(1);
}

$enContent = renderPage($data, 'en');
$csContent = renderPage($data, 'cs');

$outEnPath = resolvePath($root, $outEn);
$outCsPath = resolvePath($root, $outCs);

ensureParentDirectory($outEnPath);
ensureParentDirectory($outCsPath);

if (file_put_contents($outEnPath, $enContent) === false) {
    fwrite(STDERR, "hardware sync failed: unable to write {$outEnPath}\n");
    exit(1);
}

if (file_put_contents($outCsPath, $csContent) === false) {
    fwrite(STDERR, "hardware sync failed: unable to write {$outCsPath}\n");
    exit(1);
}

fwrite(STDOUT, "hardware sync complete:\n");
fwrite(STDOUT, "  {$outEnPath}\n");
fwrite(STDOUT, "  {$outCsPath}\n");

exit(0);

function parseOptions($argv)
{
    $options = [];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (strpos($arg, '--') !== 0) continue;

        $kv = explode('=', substr($arg, 2), 2);
        $key = $kv[0];
        $value = count($kv) === 2 ? $kv[1] : true;
        $options[$key] = $value;
    }
    return $options;
}

function printUsage()
{
    $usage = <<<TXT
Usage:
  php scripts/git_hardware_sync.php [--source=scripts/hardware_clusters.json] [--out-en=data/pages/en/resources/hardware_git_sync.txt] [--out-cs=data/pages/cs/resources/hardware_git_sync.txt]

TXT;

    fwrite(STDOUT, $usage);
}

function resolvePath($root, $path)
{
    if ($path === '') return $root;
    if ($path[0] === '/') return $path;
    return $root . '/' . $path;
}

function validateSource($data)
{
    $errors = [];

    if (!isset($data['metadata']) || !is_array($data['metadata'])) {
        $errors[] = "missing metadata object";
        return $errors;
    }

    if (!isset($data['metadata']['source_repo']) || !is_string($data['metadata']['source_repo'])) {
        $errors[] = "metadata.source_repo must be a string";
    }

    if (!isset($data['metadata']['last_updated']) || !is_string($data['metadata']['last_updated'])) {
        $errors[] = "metadata.last_updated must be a string";
    }

    if (!isset($data['organizations']) || !is_array($data['organizations'])) {
        $errors[] = "missing organizations array";
        return $errors;
    }

    foreach ($data['organizations'] as $orgIndex => $org) {
        $prefix = "organizations[{$orgIndex}]";

        if (!is_array($org)) {
            $errors[] = "{$prefix} must be an object";
            continue;
        }

        foreach (['name_en', 'name_cs', 'clusters'] as $required) {
            if (!array_key_exists($required, $org)) {
                $errors[] = "{$prefix}.{$required} is required";
            }
        }

        if (!isset($org['clusters']) || !is_array($org['clusters'])) continue;

        foreach ($org['clusters'] as $clusterIndex => $cluster) {
            $clusterPrefix = "{$prefix}.clusters[{$clusterIndex}]";
            if (!is_array($cluster)) {
                $errors[] = "{$clusterPrefix} must be an object";
                continue;
            }

            foreach (['name', 'cpu', 'nodes', 'location_en', 'location_cs', 'details_url'] as $requiredField) {
                if (!array_key_exists($requiredField, $cluster)) {
                    $errors[] = "{$clusterPrefix}.{$requiredField} is required";
                }
            }
        }
    }

    return $errors;
}

function renderPage($data, $language)
{
    $isEnglish = $language === 'en';

    $title = $isEnglish ? 'Hardware (Git Sync Pilot)' : 'Hardware (Git Sync pilot)';
    $intro = $isEnglish
        ? 'This page is generated from a canonical hardware source used for Git integration experiments.'
        : 'Tato stranka je generovana z kanonickeho zdroje hardware pro experimenty s Git integraci.';
    $sourceLabel = $isEnglish ? 'Source repository' : 'Zdrojovy repozitar';
    $lastUpdateLabel = $isEnglish ? 'Last source update' : 'Posledni aktualizace zdroje';
    $clusterLabel = $isEnglish ? 'Cluster' : 'Cluster';
    $cpuLabel = 'CPU';
    $nodesLabel = $isEnglish ? 'nodes' : 'uzlu';
    $locationLabel = $isEnglish ? 'Location' : 'Lokalita';
    $detailsLabel = $isEnglish ? 'Details' : 'Detaily';
    $photoLabel = $isEnglish ? 'Photo' : 'Fotka';

    $lines = [];
    $lines[] = "====== {$title} ======";
    $lines[] = '';
    $lines[] = $intro;
    $lines[] = '';
    $lines[] = "  * {$sourceLabel}: **{$data['metadata']['source_repo']}**";
    $lines[] = "  * {$lastUpdateLabel}: **{$data['metadata']['last_updated']}**";
    $lines[] = '';
    $lines[] = '---- GENERATED:BEGIN hardware-clusters ----';
    $lines[] = '';

    foreach ($data['organizations'] as $org) {
        $orgName = $isEnglish ? $org['name_en'] : $org['name_cs'];
        $lines[] = "===== {$orgName} =====";

        foreach ($org['clusters'] as $cluster) {
            $location = $isEnglish ? $cluster['location_en'] : $cluster['location_cs'];

            $lines[] = "  * **{$clusterLabel}: {$cluster['name']}**";
            $lines[] = "    * {$cpuLabel}: {$cluster['cpu']}";
            $lines[] = "    * {$nodesLabel}: {$cluster['nodes']}";
            $lines[] = "    * {$locationLabel}: {$location}";
            $lines[] = "    * {$detailsLabel}: [[{$cluster['details_url']}|{$cluster['details_url']}]]";

            if (!empty($cluster['photo_url'])) {
                $lines[] = "    * {$photoLabel}: [[{$cluster['photo_url']}|{$cluster['photo_url']}]]";
            }
        }

        $lines[] = '';
    }

    $lines[] = '---- GENERATED:END hardware-clusters ----';
    $lines[] = '';

    return implode("\n", $lines);
}

function ensureParentDirectory($path)
{
    $dir = dirname($path);
    if (is_dir($dir)) return;

    if (!mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "hardware sync failed: unable to create directory {$dir}\n");
        exit(1);
    }
}
