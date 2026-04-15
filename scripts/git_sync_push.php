#!/usr/bin/env php
<?php

$options = parseOptions($argv);

if (!empty($options['help'])) {
    printUsage();
    exit(0);
}

$repo = $options['repo'] ?? '';
if ($repo === '') {
    fwrite(STDERR, "git sync push failed: missing --repo=/path/to/content-repo\n");
    exit(1);
}

$repo = realpath($repo);
if ($repo === false || !is_dir($repo)) {
    fwrite(STDERR, "git sync push failed: invalid repo path\n");
    exit(1);
}

$remote = $options['remote'] ?? 'origin';
$branch = $options['branch'] ?? 'main';
$allowDirty = !empty($options['allow-dirty']);
$dryRun = !empty($options['dry-run']);

assertGitRepo($repo);

if (!$allowDirty && hasWorkingTreeChanges($repo)) {
    fwrite(STDERR, "git sync push failed: working tree is dirty\n");
    fwrite(STDERR, "FIX: commit/revert local changes first, or rerun with --allow-dirty\n");
    exit(1);
}

$command = 'git -C ' . escapeshellarg($repo) . ' push ' . escapeshellarg($remote) . ' ' . escapeshellarg($branch);
if ($dryRun) $command .= ' --dry-run';

$result = runCommand($command, $repo);
if ($result['exitCode'] !== 0) {
    fwrite(STDERR, "git sync push failed:\n");
    if ($result['stdout'] !== '') fwrite(STDERR, $result['stdout']);
    if ($result['stderr'] !== '') fwrite(STDERR, $result['stderr']);
    exit($result['exitCode']);
}

if ($result['stdout'] !== '') fwrite(STDOUT, $result['stdout']);
if ($result['stderr'] !== '') fwrite(STDOUT, $result['stderr']);

fwrite(STDOUT, "git sync push complete ({$remote}/{$branch})\n");
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
  php scripts/git_sync_push.php --repo=/path/to/content-repo [--remote=origin] [--branch=main] [--allow-dirty] [--dry-run]

TXT;

    fwrite(STDOUT, $usage);
}

function assertGitRepo($repo)
{
    $result = runCommand('git -C ' . escapeshellarg($repo) . ' rev-parse --is-inside-work-tree', $repo);
    if ($result['exitCode'] !== 0 || trim($result['stdout']) !== 'true') {
        fwrite(STDERR, "git sync push failed: path is not a git repository\n");
        exit(1);
    }
}

function hasWorkingTreeChanges($repo)
{
    $result = runCommand('git -C ' . escapeshellarg($repo) . ' status --porcelain', $repo);
    if ($result['exitCode'] !== 0) return true;
    return trim($result['stdout']) !== '';
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
