<?php

namespace eZ\Publish\Composer;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;

class LegacySettingsInstaller extends LegacyInstaller
{
    public function supports($packageType)
    {
        return $packageType === 'ezpublish-legacy-settings';
    }

    public function getInstallPath(PackageInterface $package)
    {
        $settingsInstallPath = $this->ezpublishLegacyDir . '/settings';
        if ($this->io->isVerbose()) {
            $this->io->write("eZ Publish legacy settings directory is '$settingsInstallPath'");
        }

        $extra = $package->getExtra();
        if (isset( $extra['cluster-config-directory'] )) {
            $clusterInstallPath = $this->ezpublishLegacyDir . '/' . $extra['cluster-config-directory'];
            $this->io->write("eZ Publish legacy cluster config directory is '$clusterInstallPath'");
        }

        return $settingsInstallPath;
    }
}
