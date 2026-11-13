<?php

declare(strict_types=1);

namespace mojo\observer\packages;

/**
 * The Composer packages of the install, as Composer's own vendor/composer/installed.json lists them:
 * name, installed version and whether the package is a development requirement. observer matches
 * them against published security advisories. Nothing but names and versions leaves the site, and
 * a package from a path repository (the project's own code) is left out.
 */
final class PackagesReport
{
    /**
     * @param mixed $installed the decoded installed.json; anything else than Composer 2's shape is an error
     * @return array{packages: list<array{name: string, version: string, dev: bool}>}|array{error: string}
     */
    public static function build(mixed $installed): array
    {
        if (!is_array($installed) || !is_array($installed['packages'] ?? null)) {
            return ['error' => 'vendor/composer/installed.json is missing or not in the form Composer 2 writes'];
        }

        $devNames = [];
        foreach (is_array($installed['dev-package-names'] ?? null) ? $installed['dev-package-names'] : [] as $name) {
            if (is_string($name)) {
                $devNames[strtolower($name)] = true;
            }
        }

        $packages = [];
        foreach ($installed['packages'] as $package) {
            $name = is_array($package) ? ($package['name'] ?? null) : null;
            $version = is_array($package) ? ($package['version'] ?? null) : null;
            if (!is_string($name) || $name === '' || !is_string($version) || $version === '') {
                continue;
            }
            // A package from a path repository is the project's own code, not a published one.
            if (($package['dist']['type'] ?? null) === 'path') {
                continue;
            }
            $name = strtolower($name);
            $packages[] = [
                'name' => $name,
                'version' => preg_replace('/^[vV](?=\d)/', '', $version) ?? $version,
                'dev' => isset($devNames[$name]),
            ];
        }
        usort($packages, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return ['packages' => $packages];
    }

    /** Reads and decodes installed.json; null when there is none or it is not JSON. */
    public static function read(string $path): mixed
    {
        $json = is_file($path) ? @file_get_contents($path) : false;

        return is_string($json) ? json_decode($json, true) : null;
    }
}
