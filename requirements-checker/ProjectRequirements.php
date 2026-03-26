<?php

namespace RequirementsChecker;


class ProjectRequirements extends RequirementCollection
{
    public function __construct($rootDir)
    {
        $installedPhpVersion = phpversion();

        $rootDir = $this->getComposerRootDir($rootDir);

        $phpVersion = '7.4';

        $this->addRequirement(
            version_compare($installedPhpVersion, $phpVersion, '>='),
            sprintf('PHP version must be at least %s (%s installed)', $phpVersion, $installedPhpVersion),
            sprintf('You are running PHP version "<strong>%s</strong>", but Symfony needs at least PHP "<strong>%s</strong>" to run.
            Before using Symfony, upgrade your PHP installation, preferably to the latest version.',
                $installedPhpVersion, $phpVersion),
            sprintf('Install PHP %s or newer (installed version is %s)', $phpVersion, $installedPhpVersion)
        );


        if (is_dir( $rootDir . '/var' )) {
            $this->addRequirement(
                is_writable($rootDir . '/var'),
                sprintf('%s directory must be writable', $rootDir . '/var'),
                sprintf('Change the permissions of "<strong>%s</strong>" directory so that the web server can write into it.', $rootDir . '/' . '/var')
            );
        }

        if (is_dir($cacheDir = $rootDir . '/var/cache')) {
            $this->addRequirement(
                is_writable($cacheDir),
                sprintf('%s/cache/ directory must be writable', $rootDir . '/var'),
                sprintf('Change the permissions of "<strong>%s/cache/</strong>" directory so that the web server can write into it.', $rootDir . '/var')
            );
        }

        if (is_dir($logsDir = $rootDir . '/var/log')) {
            $this->addRequirement(
                is_writable($logsDir),
                sprintf('%s/log/ directory must be writable', $rootDir . '/var'),
                sprintf('Change the permissions of "<strong>%s/log/</strong>" directory so that the web server can write into it.', $rootDir . '/var')
            );
        }


        $this->addRequirement(
            function_exists('simplexml_import_dom'),
            'simplexml_import_dom() must be available',
            'Install and enable the <strong>SimpleXML</strong> extension.'
        );

        $this->addRequirement(
            function_exists('json_encode'),
            'json_encode() must be available',
            'Install and enable the <strong>JSON</strong> extension.'
        );
    }

    private function getComposerRootDir($rootDir)
    {
        $dir = $rootDir;
        while (!file_exists($dir . '/composer.json')) {
            if ($dir === dirname($dir)) {
                return $rootDir;
            }

            $dir = dirname($dir);
        }

        return $dir;
    }

    private function readComposer($rootDir)
    {
        $composer = json_decode(file_get_contents($rootDir . '/composer.json'), true);
        $options = array(
            'bin-dir' => 'bin',
            'conf-dir' => 'conf',
            'etc-dir' => 'etc',
            'src-dir' => 'src',
            'var-dir' => 'var',
            'public-dir' => 'public',
            'vendor-dir' => 'vendor',
        );

        foreach (array_keys($options) as $key) {
            if (isset($composer['extra'][$key])) {
                $options[$key] = $composer['extra'][$key];
            } elseif (isset($composer['extra']['symfony-' . $key])) {
                $options[$key] = $composer['extra']['symfony-' . $key];
            } elseif (isset($composer['config'][$key])) {
                $options[$key] = $composer['config'][$key];
            }
        }

        return $options;
    }


    static function isApplicationInstalled():bool
    {
        return is_dir(__DIR__ . '/../var/cache');
    }
    static function isApplicationConnectedWithCloud():bool
    {
        return is_file(__DIR__ . '/../var/application_installed.json');
    }
}
