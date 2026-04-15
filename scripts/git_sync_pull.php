#!/usr/bin/env php
<?php

$options = parseOptions($argv);

if (!empty($options['help'])) {
    printUsage();
    exit(0);
}

$repo = $options['repo'] ?? '';
if ($repo === '') {
    fwrite(STDERR, "git sync pull failed: missing --repo=/path/to/content-repo\n");
    exit(1);
}

$repo = realpath($repo);
if ($repo === false || !is_dir($repo)) {
    fwrite(STDERR, "git sync pull failed: invalid repo path\n");
    exit(1);
}

$remote = $options['remote'] ?? 'origin';
$branch = $options['branch'] ?? 'main';
$allowDirty = !empty($options['allow-dirty']);
$reindexCommand = $options['reindex-cmd'] ?? '';
$dokuwikiRoot = $options['dokuwiki-root'] ?? dirname(__DIR__);

assertGitRepo($repo);

if (!$allowDirty && hasWorkingTreeChanges($repo)) {
    fwrite(STDERR, "git sync pull failed: working tree is dirty\n");
    fwrite(STDERR, "FIX: commit/revert local changes first, or rerun with --allow-dirty\n");
    exit(1);
}

$before = trim(runOrFail('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD', $repo)['stdout']);

runOrFail('git -C ' . escapeshellarg($repo) . ' fetch ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch), $repo);

$mergeCommand = 'git -C ' . escapeshellarg($repo) . ' merge --ff-only ' . escapeshellarg($remote . '/' . $branch);
runOrFail($mergeCommand, $repo);

$after = trim(runOrFail('git -C ' . escapeshellarg($repo) . ' rev-parse HEAD', $repo)['stdout']);

if ($before !== $after && $reindexCommand !== '') {
    $cwd = realpath($dokuwikiRoot);
    if ($cwd === false || !is_dir($cwd)) {
        fwrite(STDERR, "git sync pull failed: invalid --dokuwiki-root\n");
        exit(1);
    }

    runOrFail($reindexCommand, $cwd);
}

if ($before === $after) {
    fwrite(STDOUT, "git sync pull complete: already up to date\n");
} else {
    fwrite(STDOUT, "git sync pull complete: updated from {$before} to {$after}\n");
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
  php scripts/git_sync_pull.php --repo=/path/to/content-repo [--remote=origin] [--branch=main] [--reindex-cmd='php bin/indexer.php -q'] [--dokuwiki-root=/path/to/dokuwiki] [--allow-dirty]

TXT;

    fwrite(STDOUT, $usage);
}

function assertGitRepo($repo)
{
    $result = runCommand('git -C ' . escapeshellarg($repo) . ' rev-parse --is-inside-work-tree', $repo);
    if ($result['exitCode'] !== 0 || trim($result['stdout']) !== 'true') {
        fwrite(STDERR, "git sync pull failed: path is not a git repository\n");
        exit(1);
    }
}

function hasWorkingTreeChanges($repo)
{
    $result = runCommand('git -C ' . escapeshellarg($repo) . ' status --porcelain', $repo);
    if ($result['exitCode'] !== 0) return true;
    return trim($result['stdout']) !== '';
}

function runOrFail($command, $cwd)
{
    $result = runCommand($command, $cwd);
    if ($result['exitCode'] !== 0) {
        fwrite(STDERR, "git sync pull failed while running:\n  {$command}\n");
        if ($result['stdout'] !== '') fwrite(STDERR, $result['stdout']);
        if ($result['stderr'] !== '') fwrite(STDERR, $result['stderr']);
        exit($result['exitCode']);
    }

    return $result;
}

function runCommand($command, $cwd)
{
    $descriptors = [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ];

    $process = proc_open($command, $descriptors, $pipes, $cwd);
    if (!is_resource($process)) {
        return ['exitCode' => 1, 'stdout' => '', 'stderr' => 'unable to start process\n'];
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    fclose($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[2]);

    $exitCode = proc_close($process);

    return [
        'exitCode' => $exitCode,
        'stdout' => $stdout,
        'stderr' => $stderr,
    ];
}
