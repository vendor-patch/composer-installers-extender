<?php

declare(strict_types = 1);

namespace VendorPatch\OomphInc\ComposerInstallersExtender\Installers;

use Composer\Installer\LibraryInstaller;
use Composer\Installers\Installer as InstallerBase;
use Composer\CustomDirectoryInstaller\LibraryInstaller as BaseCustomDirectoryLibraryInstallerInstaller;
use Composer\Installers\BaseInstaller;

use Composer\Composer;
use Composer\Installer\BinaryInstaller;
use Composer\IO\IOInterface;
use Composer\Package\Package;
use Composer\Package\PackageInterface;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;

class Installer extends InstallerBase
{
    /*
    protected $locations = [];
    public function getLocations()
    {
        return $this->locations;
    }
   
    */
    
    protected $installerTypes;
    /** @var string */
    protected $vendorDir;

    /** @var DownloadManager|null */
    protected $downloadManager;

    protected $package;
        /** @var PartialComposer */
    protected $composer;

    /** @var IOInterface */
    protected $io;

    /** @var string */
    protected $type;

    /** @var Filesystem */
    protected $filesystem;

    /** @var BinaryInstaller */
    protected $binaryInstaller;



    /**
     * {@inheritDoc}
     */
    public function getInstallPath(PackageInterface $package, string $frameworkType = ''): string
    {

       $args = func_get_args();
       @list($package, $frameworkType) = $args;
        if(isset($frameworkType) && !empty($frameworkType) || !$this->composer){
         return $this->getInstallPath_LibraryBase($package, $frameworkType ?? null);
        }
        
      try{   
        $installer = new CustomInstaller($package, $this->composer, $this->io);
        $path = $installer->getInstallPath($package, $package->getType());


        
        return $path ?: LibraryInstaller::getInstallPath($package);
      }catch(\Exception $e){
        return LibraryInstaller::getInstallPath($package);
      }
    }

    


    
    public function getInstallPath_LibraryBase(PackageInterface $package, $frameworkType = '')
    {
        $type = $this->package->getType();

        $prettyName = $this->package->getPrettyName();
        if (strpos($prettyName, '/') !== false) {
            list($vendor, $name) = explode('/', $prettyName);
        } else {
            $vendor = '';
            $name = $prettyName;
        }

               
        $p = explode('/', $name);
        $package_local_name = array_pop($p);   
        $availableVars = $this->inflectPackageVars(compact('name', 'vendor', 'type', 'package_local_name'));

        $extra = $package->getExtra();
        if (!empty($extra['installer-name'])) {
            $availableVars['name'] = $extra['installer-name'];
        }

        if ($this->composer->getPackage()) {
            $extra = $this->composer->getPackage()->getExtra();
            if (!empty($extra['installer-paths'])) {
                $customPath = $this->mapCustomInstallPaths($extra['installer-paths'], $prettyName, $type, $vendor);
                if ($customPath !== false) {
                    return $this->templatePath($customPath, $availableVars);
                }
            }
        }

        $packageType = substr($type, strlen($frameworkType) + 1);
        $locations = $this->getLocations();
        if (!isset($locations[$packageType])) {
            throw new \InvalidArgumentException(sprintf('Package type "%s" is not supported', $type));
        }

        return $this->templatePath($locations[$packageType], $availableVars);
    }


    
    protected function mapCustomInstallPaths(array $paths, $name, $type, $vendor = NULL)
    {
        foreach ($paths as $path => $names) {
            $names = (array) $names;
            if (in_array($name, $names) || in_array('type:' . $type, $names) || in_array('vendor:' . $vendor, $names)) {
                return $path;
            }
        }

        return false;
    }
    
    /**
     * {@inheritDoc}
     */
    public function supports($packageType): bool
    {
        return in_array($packageType, $this->getInstallerTypes()) || null === $this->type || $packageType === $this->type;
    }

    /**
     * Get a list of custom installer types.
     *
     * @return array
     */
    public function getInstallerTypes(): array
    {
        if ( !is_array($this->installerTypes) ) {
            $extra = $this->composer->getPackage()->getExtra();
            $this->installerTypes = $extra['installer-types'] ?? [];
        }else{
            $extra = $this->composer->getPackage()->getExtra();
            $this->installerTypes = array_merge($this->installerTypes,
                                                $extra['installer-types'] ?? []
                                               );
        }

       if(isset($this->package) && !empty($this->package) ){
            $extra = $this->package->getExtra();
            $this->installerTypes = array_merge($this->installerTypes,
                                                $extra['installer-types'] ?? []
                                               );
       }

        
        return $this->installerTypes;
    }

        
    protected function templatePath(string $path, array $vars = []): string
    {
        if (strpos($path, '{') !== false) {
            extract($vars);
            preg_match_all('@\{\$([A-Za-z0-9_]*)\}@i', $path, $matches);
            if (!empty($matches[1])) {
                foreach ($matches[1] as $var) {
                    $path = str_replace('{$' . $var . '}', $$var, $path);
                }
            }
        }

        return $path;
    }
}
