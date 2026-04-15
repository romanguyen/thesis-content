#!/usr/bin/env php
<?php

$options = parseOptions($argv);

if (!empty($options['help'])) {
    printUsage();
    exit(0);
}

$repoRoot = realpath($options['repo-root'] ?? getcwd());
if ($repoRoot === false || !is_dir($repoRoot)) {
    fwrite(STDERR, "git PR check failed: invalid --repo-root\n");
    exit(1);
}

$mode = $options['mode'] ?? 'changed';
if (!in_array($mode, ['changed', 'all'], true)) {
    fwrite(STDERR, "git PR check failed: --mode must be 'changed' or 'all'\n");
    exit(1);
}

$profile = $options['profile'] ?? 'content-repo';
if (!in_array($profile, ['content-repo', 'dokuwiki-monorepo'], true)) {
    fwrite(STDERR, "git PR check failed: --profile must be 'content-repo' or 'dokuwiki-monorepo'\n");
    exit(1);
}

$includeUntracked = !empty($options['include-untracked']);
$files = $mode === 'all' ? listAllFiles($repoRoot) : getChangedFiles($repoRoot, $includeUntracked);
if ($files === []) exit(0);

$violations = [];

$allowedPrefixes = [
    '.github/',
    'scripts/',
    'data/pages/',
    'data/media/',
];

$allowedExact = [
    '.gitignore',
    'README.md',
    'CODEOWNERS',
];

$deniedPrefixes = [
    'data/cache/',
    'data/index/',
    'data/locks/',
    'data/log/',
    'data/tmp/',
    'data/meta/',
    'data/media_meta/',
    'data/attic/',
    'data/media_attic/',
    'data/conf/',
];

foreach ($files as $path) {
    if (strpos($path, '.git/') === 0) continue;

    if ($profile === 'content-repo') {
        if (!isAllowedPath($path, $allowedPrefixes, $allowedExact)) {
            $violations[] = "path outside allowlist: {$path}";
            continue;
        }

        foreach ($deniedPrefixes as $prefix) {
            if (strpos($path, $prefix) === 0) {
                $violations[] = "path inside denylist: {$path}";
                continue 2;
            }
        }
    }

    $absolutePath = $repoRoot . '/' . $path;
    if (!is_file($absolutePath)) continue;

    if (isTextPolicyTarget($path)) {
        $textViolations = checkTextPolicy($absolutePath, $path);
        foreach ($textViolations as $violation) {
            $violations[] = $violation;
        }
    }

    if (isWikiPage($path, $profile)) {
        $syntaxViolations = checkWikiSyntaxSanity($absolutePath, $path);
        foreach ($syntaxViolations as $violation) {
            $violations[] = $violation;
        }
    }
}

if ($violations !== []) {
    fwrite(STDERR, "git PR validation failed:\n");
    foreach ($violations as $violation) {
        fwrite(STDERR, "  {$violation}\n");
    }
    fwrite(STDERR, "FIX: Correct files above and rerun `php scripts/git_pr_check.php`.\n");
    exit(1);
}

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
  php scripts/git_pr_check.php [--repo-root=/path] [--mode=changed|all] [--profile=content-repo|dokuwiki-monorepo] [--include-untracked]

Profiles:
  content-repo      Validate allowlist/denylist + text policy + wiki syntax sanity for `data/pages/*.txt`
  dokuwiki-monorepo Validate text policy + wiki syntax sanity for `data/pages/*.txt`

TXT;

    fwrite(STDOUT, $usage);
}

function getChangedFiles($repoRoot, $includeUntracked = false)
{
    $unstaged = runGitLines('git diff --name-only', $repoRoot);
    $staged = runGitLines('git diff --name-only --cached', $repoRoot);
    $untracked = runGitLines('git ls-files --others --exclude-standard', $repoRoot);

    $all = array_values(array_unique(array_merge($unstaged, $staged)));
    if ($includeUntracked) {
        $all = array_values(array_unique(array_merge($all, $untracked)));
    }

    sort($all);
    return $all;
}

function listAllFiles($repoRoot)
{
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($repoRoot, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) continue;

        $absolute = $fileInfo->getPathname();
        $relative = ltrim(str_replace($repoRoot, '', $absolute), '/');
        $files[] = $relative;
    }

    sort($files);
    return $files;
}

function runGitLines($command, $cwd)
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) return [];

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);

    $lines = preg_split('/\r?\n/', trim($stdout));
    if ($lines === false || $lines === ['']) return [];
    return $lines;
}

function isAllowedPath($path, $allowedPrefixes, $allowedExact)
{
    if (in_array($path, $allowedExact, true)) return true;

    foreach ($allowedPrefixes as $prefix) {
        if (strpos($path, $prefix) === 0) return true;
    }

    return false;
}

function isTextPolicyTarget($path)
{
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['txt', 'md', 'yml', 'yaml', 'json', 'ini', 'conf'], true);
}

function checkTextPolicy($absolutePath, $relativePath)
{
    $violations = [];
    $content = @file_get_contents($absolutePath);
    if ($content === false) return $violations;

    if (strpos($content, "\r") !== false) {
        $violations[] = "CRLF line endings found: {$relativePath}";
    }

    if (preg_match('/[ \t]+$/m', $content)) {
        $violations[] = "trailing whitespace found: {$relativePath}";
    }

    if ($content !== '' && !str_ends_with($content, "\n")) {
        $violations[] = "missing trailing newline: {$relativePath}";
    }

    return $violations;
}

function isWikiPage($path, $profile)
{
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'txt') return false;

    if ($profile === 'content-repo') {
        return strpos($path, 'data/pages/') === 0;
    }

    return strpos($path, 'data/pages/') === 0;
}

function checkWikiSyntaxSanity($absolutePath, $relativePath)
{
    $violations = [];
    $text = @file_get_contents($absolutePath);
    if ($text === false) return $violations;

    $pairs = [
        ['[[', ']]', 'link tokens'],
        ['{{', '}}', 'media/include tokens'],
    ];

    foreach ($pairs as $pair) {
        $open = substr_count($text, $pair[0]);
        $close = substr_count($text, $pair[1]);
        if ($open !== $close) {
            $violations[] = "unbalanced {$pair[2]}: {$relativePath} ({$pair[0]}={$open}, {$pair[1]}={$close})";
        }
    }

    return $violations;
}
