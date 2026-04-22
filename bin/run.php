<?php declare(strict_types=1);

/*
 * Runs a vendor/bin binary (phpstan, phpunit, php-cs-fixer…) after locating the
 * project root regardless of where this bundle lives.
 *
 * Layouts supported:
 *   1. Monorepo (bundle at custom/bundles/{name}/)            — root = shopware/platform
 *   2. Composer-installed (bundle at vendor/swag/mcp-dev-tools) — root = consumer project
 *   3. Standalone clone with its own vendor/                   — root = the bundle itself
 *
 * Also renders phpstan.neon from phpstan.neon.dist before invoking phpstan,
 * because PHPStan's NEON parser does not expand %env()% inside `includes:`.
 *
 * Usage:
 *   php bin/run.php <binary> [...args]
 * e.g.
 *   php bin/run.php phpstan analyse --memory-limit=2G
 *   php bin/run.php phpunit
 *   php bin/run.php php-cs-fixer fix --dry-run --diff
 */

use Composer\InstalledVersions;

if ($argc < 2) {
    fwrite(\STDERR, "Usage: php bin/run.php <binary> [...args]\n");
    exit(2);
}

$binary = $argv[1];
$args = \array_slice($argv, 2);

// One-time autoload discovery: same three layouts we support elsewhere.
$autoloadCandidates = [
    __DIR__ . '/../../../../vendor/autoload.php',  // monorepo: custom/bundles/{name}/bin/
    __DIR__ . '/../../../vendor/autoload.php',      // composer-installed: vendor/swag/mcp-dev-tools/bin/
    __DIR__ . '/../vendor/autoload.php',            // standalone clone with own vendor/
];
$autoloaded = false;
foreach ($autoloadCandidates as $candidate) {
    if (is_file($candidate)) {
        require $candidate;
        $autoloaded = true;

        break;
    }
}
if (!$autoloaded || !class_exists(InstalledVersions::class)) {
    fwrite(\STDERR, "Could not find vendor/autoload.php. Run `composer install` first.\n");
    exit(1);
}

$projectRoot = InstalledVersions::getRootPackage()['install_path'] ?? null;
if (!\is_string($projectRoot) || !is_dir($projectRoot)) {
    fwrite(\STDERR, "Could not resolve project root from Composer.\n");
    exit(1);
}
$projectRoot = realpath($projectRoot);

$vendorBin = $projectRoot . '/vendor/bin';
$binaryPath = $vendorBin . '/' . $binary;
if (!is_file($binaryPath)) {
    fwrite(\STDERR, \sprintf("Binary not found: %s\n", $binaryPath));
    exit(1);
}

if ($binary === 'phpstan') {
    renderPhpstanConfig(\dirname(__DIR__), $projectRoot);
}

$cmd = array_merge([$binaryPath], $args);
$escaped = implode(' ', array_map('escapeshellarg', $cmd));
passthru($escaped, $exitCode);
exit($exitCode);

function renderPhpstanConfig(string $bundleDir, string $projectRoot): void
{
    $corePath = null;
    $installed = InstalledVersions::getInstallPath('shopware/core');
    if (\is_string($installed) && is_dir($installed . '/DevOps')) {
        $corePath = realpath($installed);
    }

    if ($corePath === null) {
        $monorepoCandidate = $projectRoot . '/src/Core';
        if (is_dir($monorepoCandidate . '/DevOps')) {
            $corePath = realpath($monorepoCandidate);
        }
    }

    if ($corePath === false || $corePath === null) {
        fwrite(\STDERR, "Could not locate Shopware core.\n");
        exit(1);
    }

    $tmpDir = $projectRoot . '/var/cache/phpstan-swag-mcp-dev-tools';
    if (!is_dir($tmpDir) && !mkdir($tmpDir, 0o775, true) && !is_dir($tmpDir)) {
        fwrite(\STDERR, \sprintf("Failed to create PHPStan tmp dir: %s\n", $tmpDir));
        exit(1);
    }

    $template = file_get_contents($bundleDir . '/phpstan.neon.dist');
    if ($template === false) {
        fwrite(\STDERR, "phpstan.neon.dist not found.\n");
        exit(1);
    }

    $rendered = strtr($template, [
        '__SHOPWARE_CORE_DIR__' => $corePath,
        '__PHPSTAN_TMP_DIR__' => $tmpDir,
    ]);
    file_put_contents($bundleDir . '/phpstan.neon', $rendered);
}
