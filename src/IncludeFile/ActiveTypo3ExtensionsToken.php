<?php
namespace Helhum\Typo3ConsolePlugin\IncludeFile;

/*
 * This file is part of the typo3 console plugin package.
 *
 * (c) Helmut Hummel <info@helhum.io>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Package\PackageInterface;
use Composer\Semver\Constraint\EmptyConstraint;
use Helhum\Typo3ConsolePlugin\Config;

class ActiveTypo3ExtensionsToken implements TokenInterface
{
    /**
     * @var string
     */
    private $name = 'active-typo3-extensions';

    /**
     * @var Config
     */
    private $config;

    /**
     * @var Composer
     */
    private $composer;

    /**
     * @var IOInterface
     */
    private $io;

    /**
     * @var bool
     */
    private $isDevMode;

    /**
     * @var array
     */
    private $frameworkPackages = [];

    /**
     * ActiveTypo3ExtensionsToken constructor.
     *
     * @param IOInterface $io
     * @param Composer $composer
     * @param Config $config
     * @param bool $isDevMode
     */
    public function __construct(IOInterface $io, Composer $composer, Config $config, $isDevMode = false)
    {
        $this->io = $io;
        $this->config = $config;
        $this->composer = $composer;
        $this->isDevMode = $isDevMode;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @throws \RuntimeException
     * @return string
     */
    public function getContent()
    {
        $this->io->writeError('<info>Writing TYPO3_ACTIVE_FRAMEWORK_EXTENSIONS environment variable</info>', true, IOInterface::VERBOSE);
        $configuredActiveTypo3Extensions = $this->config->get('active-typo3-extensions');
        if (!is_array($configuredActiveTypo3Extensions)) {
            $this->io->writeError(sprintf('<error>Extra section "active-typo3-extensions" must be array, "%s" given!</error>', gettype($configuredActiveTypo3Extensions)));
            $configuredActiveTypo3Extensions = [];
        }
        if (count($configuredActiveTypo3Extensions) > 0) {
            $this->io->writeError('<warning>Extra section "active-typo3-extensions" has been deprecated!</warning>');
            $this->io->writeError('<warning>Please just add typo3/cms framework packages to the require section in your composer.json of any package.</warning>');
        }
        $activeTypo3Extensions = array_unique(array_merge($configuredActiveTypo3Extensions, $this->getRequiredCoreExtensionKeysFromPackageRequires()));
        asort($activeTypo3Extensions);
        $this->io->writeError('<info>The following extensions are marked as active:</info> ' . implode(', ', $activeTypo3Extensions), true, IOInterface::VERBOSE);
        return var_export(implode(',', $activeTypo3Extensions), true);
    }

    /**
     * @return array
     */
    protected function getRequiredCoreExtensionKeysFromPackageRequires()
    {
        $this->io->writeError('<info>Determine dependencies to typo3/cms framework packages.</info>', true, IOInterface::VERY_VERBOSE);
        $rootPackage = $this->composer->getPackage();
        $allPackages = $this->composer->getRepositoryManager()->getLocalRepository()->getCanonicalPackages();
        $corePackages = [];
        $this->initializeFrameworkPackages();
        foreach ($allPackages as $package) {
            $corePackages = array_replace($corePackages, $this->getCorePackagesFromRequire($package));
        }
        $corePackages = array_replace($corePackages, $this->getCorePackagesFromRequire($rootPackage, $this->isDevMode));
        return $corePackages;
    }

    private function initializeFrameworkPackages()
    {
        $typo3Package = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage('typo3/cms', new EmptyConstraint());
        foreach ($typo3Package->getReplaces() as $name => $_) {
            if (strpos($name, 'typo3/cms-') === 0) {
                $this->frameworkPackages[] = $name;
            }
        }
    }

    /**
     * @param PackageInterface $package
     * @param bool $includeDev
     * @return array
     */
    private function getCorePackagesFromRequire(PackageInterface $package, $includeDev = false)
    {
        $corePackages = [];
        $requires = $package->getRequires();
        if ($includeDev) {
            $requires = array_merge($requires, $package->getDevRequires());
        }
        foreach ($requires as $name => $link) {
            if (in_array($name, $this->frameworkPackages, true)) {
                $this->io->writeError(sprintf('The package "%s" requires: "%s"', $package->getName(), $link->getTarget()), true, IOInterface::DEBUG);
                $this->io->writeError(sprintf('The extension key for package "%s" is: "%s"', $link->getTarget(), $this->determineExtKeyFromPackageName($link->getTarget())), true, IOInterface::DEBUG);
                $corePackages[$name] = $this->determineExtKeyFromPackageName($link->getTarget());
            }
        }
        return $corePackages;
    }

    /**
     * @param string $packageName
     * @return string
     */
    private function determineExtKeyFromPackageName($packageName)
    {
        return str_replace(['typo3/cms-', '-'], ['', '_'], $packageName);
    }
}
