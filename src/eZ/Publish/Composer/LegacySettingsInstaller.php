<?php

namespace eZ\Publish\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Util\Filesystem;
use Symfony\Component\Finder\Finder;
use React\Promise\PromiseInterface;

class LegacySettingsInstaller extends LegacyInstaller
{
    protected $settingsInstallPath;

    public function __construct( IOInterface $io, Composer $composer, $type = '' )
    {
        parent::__construct($io, $composer, $type);

        $this->settingsInstallPath = $this->ezpublishLegacyDir . '/settings';
    }

    public function supports($packageType)
    {
        return $packageType === 'ezpublish-legacy-settings';
    }

    public function getInstallPath(PackageInterface $package)
    {
        if ($this->io->isVerbose()) {
            $this->io->write("eZ Publish legacy settings directory is '{$this->settingsInstallPath}'");
        }

        return $this->settingsInstallPath;
    }

    public function install( InstalledRepositoryInterface $repo, PackageInterface $package )
    {
        $downloadPath = $this->getInstallPath( $package );
        $fileSystem = new Filesystem();
        if ( !is_dir( $downloadPath ) || $fileSystem->isDirEmpty( $downloadPath ) )
        {
            return parent::install( $repo, $package );
        }

        $actualSettingsInstallPath = $this->settingsInstallPath;
        $this->settingsInstallPath = $this->generateTempDirName();
        if ( $this->io->isVerbose() )
        {
            $this->io->write( "Installing settings in temporary directory." );
        }

        $install = function () use ( $fileSystem, $actualSettingsInstallPath, $package ){
            /// @todo the following function does not warn of any failures in copying stuff over. We should probably fix it...
            if ( $this->io->isVerbose() )
            {
                $this->io->write( "Updating settings over existing installation." );
            }
            $fileSystem->copyThenRemove( $this->settingsInstallPath, $actualSettingsInstallPath );

            $this->settingsInstallPath = $actualSettingsInstallPath;
            $this->copyClusterSettings($package);
        };

        $promise = parent::install( $repo, $package );
        // Composer v2 might return a promise here
        if ( $promise instanceof PromiseInterface )
        {
            return $promise->then( $install );
        }

        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $install();
    }

    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $update = function () use ($target){
            $this->copyClusterSettings($target);
        };

        $promise = parent::update($repo, $initial, $target);
        // Composer v2 might return a promise here
        if ( $promise instanceof PromiseInterface )
        {
            return $promise->then( $update );
        }

        // If not, execute the code right away as parent::update executed synchronously (composer v1, or v2 without async)
        $update();
    }

    private function copyClusterSettings(PackageInterface $package)
    {
        $extra = $package->getExtra();
        if (isset( $extra['cluster-config-directory'] )) {
            $clusterConfigSourceDirectory = $this->settingsInstallPath . '/' . $extra['cluster-config-directory'];
            if (is_dir($clusterConfigSourceDirectory)) {
                $this->io->write("Read cluster config from directory '{$clusterConfigSourceDirectory}'");
                $finder = new Finder();
                $finder->files()->in($clusterConfigSourceDirectory);
                foreach ($finder as $file) {
                    $this->io->write("Copy " . $file->getRelativePathname() . " in document root");
                    copy($file->getRealPath(), $this->ezpublishLegacyDir . '/' . basename($file->getRelativePathname()));
                }
            }
        }
    }

    protected function generateTempDirName()
    {
        $tmpDir = sys_get_temp_dir() . '/' . uniqid( 'composer_ezlegacysettings_' );
        if ( $this->io->isVerbose() )
        {
            $this->io->write( "Temporary directory for ezpublish-legacy-settings updates: $tmpDir" );
        }

        return $tmpDir;
    }
}
