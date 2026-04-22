<?php declare(strict_types=1);

use Composer\InstalledVersions;
use Shopware\Core\TestBootstrapper;

/**
 * Locates Shopware core's TestBootstrapper.php regardless of whether this
 * bundle lives at custom/bundles/{name}/ (monorepo), vendor/swag/mcp-dev-tools/
 * (composer-installed), or is a standalone clone with its own vendor/.
 */
function locateShopwareCore(): string
{
    // composer-installed or standalone-clone cases
    $installed = InstalledVersions::getInstallPath('shopware/core');
    if (\is_string($installed) && is_file($installed . '/TestBootstrapper.php')) {
        return $installed;
    }

    // monorepo: bundle at custom/bundles/{name}/tests/, core at {project}/src/Core
    $monorepo = \dirname(__DIR__, 4) . '/src/Core';
    if (is_file($monorepo . '/TestBootstrapper.php')) {
        return $monorepo;
    }

    throw new RuntimeException('Could not locate shopware/core TestBootstrapper.');
}

$corePath = locateShopwareCore();
require_once $corePath . '/TestBootstrapper.php';

$projectDir = \dirname($corePath, \str_ends_with($corePath, '/src/Core') ? 2 : 3);

$classLoader = (new TestBootstrapper())
    ->setProjectDir($projectDir)
    ->setLoadEnvFile(true)
    // No addCallingPlugin() — bundles are registered via config/bundles.php, not the plugin system
    ->bootstrap()
    ->getClassLoader();

$bundleDir = \dirname(__DIR__);
$composerJson = json_decode((string) file_get_contents($bundleDir . '/composer.json'), true, 512, \JSON_THROW_ON_ERROR);

foreach ($composerJson['autoload']['psr-4'] ?? [] as $namespace => $path) {
    $classLoader->addPsr4($namespace, $bundleDir . '/' . $path);
}

foreach ($composerJson['autoload-dev']['psr-4'] ?? [] as $namespace => $path) {
    $classLoader->addPsr4($namespace, $bundleDir . '/' . $path);
}

return $classLoader;
