<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\FunctionalTestingFramework\Util;

/**
 * Class ModulePathExtractor, resolve module reference based on path
 */
class ModulePathExtractor
{
    const SPLIT_DELIMITER = '_';

    /**
     * Test module paths
     *
     * @var array
     */
    private $testModulePaths = [];

    /**
     * ModulePathExtractor constructor
     */
    public function __construct()
    {
        $verbosePath = true;
        if (empty($this->testModulePaths)) {
            $this->testModulePaths = ModuleResolver::getInstance()->getModulesPath($verbosePath);
        }
    }

    /**
     * Extracts all module names from the path given
     *
     * @param string $path
     * @return string[]
     */
    public function extractAllModuleNames($path)
    {
        $moduleNames = [];

        $keys = $this->extractKeysByPath($path);
        if (empty($keys)) {
            return $moduleNames;
        }

        $parts = $this->splitKeysForParts($keys);

        foreach ($parts as $part) {
            if (isset($part[1])) {
                $moduleNames[] = $part[1];
            }
        }
        return $moduleNames;
    }

    /**
     * Extracts all vendor names for module from the path given
     *
     * @param string $path
     * @return string[]
     */
    public function getAllExtensions($path)
    {
        $extensions = [];

        $keys = $this->extractKeysByPath($path);
        if (empty($keys)) {
            return $extensions;
        }

        $parts = $this->splitKeysForParts($keys);

        foreach ($parts as $part) {
            if (isset($part[0])) {
                $extensions[] = $part[0];
            }
        }
        return $extensions;
    }

    /**
     * @deprecated
     *
     * Extracts first module name from the path given
     *
     * @param string $path
     * @return string
     */
    public function extractModuleName($path)
    {
        $keys = $this->extractKeysByPath($path);
        if (empty($keys)) {
            return "NO MODULE DETECTED";
        }
        $parts = $this->splitKeysForParts($keys);
        return isset($parts[0][1]) ? $parts[0][1] : "NO MODULE DETECTED";
    }

    /**
     * @deprecated
     *
     * Extracts the first vendor name for module from the path given
     *
     * @param string $path
     * @return string
     */
    public function getExtensionPath($path)
    {
        $keys = $this->extractKeysByPath($path);
        if (empty($keys)) {
            return "NO VENDOR DETECTED";
        }
        $parts = $this->splitKeysForParts($keys);
        return isset($parts[0][0]) ? $parts[0][0] : "NO VENDOR DETECTED";
    }

    /**
     * Split keys by SPLIT_DELIMITER and return parts array for all keys
     *
     * @param array $keys
     * @return array
     */
    private function splitKeysForParts($keys)
    {
        $partsArray = [];
        foreach ($keys as $key) {
            $parts = explode(self::SPLIT_DELIMITER, $key);
            $partsArray[] = count($parts) == 2 ? $parts : [];
        }

        return $partsArray;
    }

    /**
     * Extract module name keys by path
     *
     * @param string $path
     * @return array
     */
    private function extractKeysByPath($path)
    {
        $shortenedPath = dirname(dirname($path));
        // Ignore this path if we cannot go to parent directory two levels up
        if (empty($shortenedPath) || $shortenedPath === '.') {
            return [];
        }

        foreach ($this->testModulePaths as $path => $keys) {
            if ($path == $shortenedPath) {
                return $keys;
            }
        }
        return [];
    }
}
