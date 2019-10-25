<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\FunctionalTestingFramework\Config\Reader;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Config\MftfDom;
use Magento\FunctionalTestingFramework\Exceptions\Collector\ExceptionCollector;
use Magento\FunctionalTestingFramework\Util\Iterator\File;
use Sabre\Xml\Service;

class MftfFilesystem extends \Magento\FunctionalTestingFramework\Config\Reader\Filesystem
{
    const CACHE_DIR = TESTS_BP . "/_cache/";
    const CACHE_DATA_DIR = self::CACHE_DIR . "data/";

    const SCOPE_TO_ARRAY_KEY = [
        'Test' => 'tests',
        'Page' => 'page',
        'Section' => 'section',
        'Data' => 'entity',
        'ActionGroup' => 'actionGroups'
    ];

    /**
     * Method to redirect file name passing into Dom class
     *
     * @param File $fileList
     * @return array
     * @throws \Exception
     */
    public function readFiles($fileList)
    {
        $exceptionCollector = new ExceptionCollector();
        /** @var \Magento\FunctionalTestingFramework\Test\Config\Dom $configMerger */
        $configMerger = null;
        $debugLevel = MftfApplicationConfig::getConfig()->getDebugLevel();

        // Read files if cache exists
        $fileToTimePath = TESTS_BP . "/_cache/" . $this->defaultScope . "ToTime";
        $entityToFilesPath = TESTS_BP . "/_cache/" . $this->defaultScope . "ToFiles";
        $fileToTime = [];
        $entityToFiles = [];
        if (is_file($fileToTimePath)) {
            $fileToTime = $this->readMappingFile(file_get_contents($fileToTimePath));
        }
        if (is_file($entityToFilesPath)) {
            $entityToFiles = $this->readMappingFile(file_get_contents($entityToFilesPath));
        }

        // Cache Vars
        $cacheInvalidated = false;
        $filesToRead = [];

        // Read cache before anything else
        $newOutput = null;
        $cachedOutput = null;
        if (is_dir(self::CACHE_DATA_DIR . $this->defaultScope)) {
            $cachedOutput = $this->readCache(self::CACHE_DATA_DIR . $this->defaultScope);
        }

        // First pass to find changed files
        foreach ($fileList as $key => $content) {
            $fileName = $fileList->getFilename();
            if (isset($fileToTime[$fileName]) && filemtime($fileName) <= $fileToTime[$fileName]) {
                // Do not read
                continue;
            }
            $timeAccessed = time();
            $filesToRead[$fileName] = $timeAccessed;
            // break cache for specific file
            $cacheInvalidated = true;
            echo 'break cache ' . $fileName . PHP_EOL;

            //peek file to find entities touched
            $xmlReader = new Service();
            $dom = $xmlReader->parse($content);
            $entityKeys = [];
            foreach ($dom as $entity) {
                $entityKeys[] = $entity['attributes']['name'];
            }

            // break cache for other files where this entity appears
            foreach ($entityKeys as $entityKey) {
                if (!isset($entityToFiles[$entityKey])) {
                    continue;
                }
                $otherFiles = explode(',', $entityToFiles[$entityKey]);
                foreach($otherFiles as $otherFile) {
                    if (isset($filesToRead[$otherFile])) {
                        continue;
                    }
                    $filesToRead[$otherFile] = $timeAccessed;
                }
                unset($cachedOutput[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]][$entityKey]);
            }
        }


        // Second pass to read only relevant files
        foreach ($fileList as $key => $content) {
            $fileName = $fileList->getFilename();
            // Refresh content (bug in File.php?)
            $content = file_get_contents($fileName);
            //check if file is empty and continue to next if it is
            if (!parent::verifyFileEmpty($content, $fileName)) {
                continue;
            }
            if (!isset($filesToRead[$fileName])) {
                continue;
            }
            try {
                if (!$configMerger) {
                    $configMerger = $this->createConfigMerger(
                        $this->domDocumentClass,
                        $content,
                        $fileName,
                        $exceptionCollector
                    );
                } else {
                    $configMerger->merge($content, $fileName, $exceptionCollector);
                }
                 // run per file validation with generate:tests -d
                if ($debugLevel === MftfApplicationConfig::LEVEL_DEVELOPER) {
                    $this->validateSchema($configMerger, $fileName);
                }
            } catch (\Magento\FunctionalTestingFramework\Config\Dom\ValidationException $e) {
                throw new \Exception("Invalid XML in file " . $key . ":\n" . $e->getMessage());
            }
        }
        $exceptionCollector->throwException();

         //run validation on merged file with generate:tests
        if ($debugLevel === MftfApplicationConfig::LEVEL_DEFAULT) {
            $this->validateSchema($configMerger);
        }

        if ($configMerger) {
            $newOutput = $this->converter->convert($configMerger->getDom());
        }

        if ($cachedOutput == null) {
            $cachedOutput = $newOutput;
        }

        if ($newOutput !== null) {
            $cachedOutput[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]] = array_merge(
                $cachedOutput[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]],
                $newOutput[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]]
            );
        }

        // Rebuild cache
        if ($cacheInvalidated) {
            // Last pass to rebuild entity->filename relationship
            $entityToFiles = [];
            foreach ($cachedOutput[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]] as $key => $entity) {
                if (!is_array($entity)) {
                    continue;
                }
                $entityToFiles[$key] = $entity['filename'];
            }
            foreach ($filesToRead as $file => $time) {
                $fileToTime[$file] = $time;
            }
            $this->buildCache($cachedOutput, $this->defaultScope);
            $this->writeMappingFile($fileToTime, $fileToTimePath);
            $this->writeMappingFile($entityToFiles, $entityToFilesPath);
        }

        return $cachedOutput;
    }

    /**
     * Return newly created instance of a config merger
     *
     * @param string             $mergerClass
     * @param string             $initialContents
     * @param string             $filename
     * @param ExceptionCollector $exceptionCollector
     * @return \Magento\FunctionalTestingFramework\Config\Dom
     * @throws \UnexpectedValueException
     */
    protected function createConfigMerger($mergerClass, $initialContents, $filename = null, $exceptionCollector = null)
    {
        $result = new $mergerClass(
            $initialContents,
            $filename,
            $exceptionCollector,
            $this->idAttributes,
            null,
            $this->perFileSchema
        );
        if (!$result instanceof \Magento\FunctionalTestingFramework\Config\Dom) {
            throw new \UnexpectedValueException(
                "Instance of the DOM config merger is expected, got {$mergerClass} instead."
            );
        }
        return $result;
    }

    protected function readMappingFile($contents)
    {
        $result = [];
        $lines = explode(PHP_EOL, $contents);
        foreach ($lines as $line) {
            $temp = explode(':', $line);
            if (count($temp) < 2) {
                continue;
            }
            $result[$temp[0]] = $temp[1];
        }
        return $result;
    }

    protected function writeMappingFile($contents, $filename)
    {
        if (is_file($filename)) {
            unlink($filename);
        }
        $string = '';
        foreach ($contents as $key => $value) {
            $string .= $key . ":" . $value . PHP_EOL;
        }
        file_put_contents($filename, $string);
    }

    protected function readCache($cachePath)
    {
        $contents = $this->readCacheMetaContents();

        foreach (array_slice(scandir($cachePath), 2) as $cacheFile) {
            $contents[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]][$cacheFile] = json_decode(file_get_contents($cachePath . '/' . $cacheFile), true);
        }
        return $contents;
    }

    protected function buildCache($contents, $type)
    {
        $cacheDir = self::CACHE_DATA_DIR . $type . "/";
        if (!is_dir(self::CACHE_DIR)) {
            mkdir(self::CACHE_DIR);
        }
        if (!is_dir(self::CACHE_DATA_DIR)) {
            mkdir(self::CACHE_DATA_DIR);
        }
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }


        foreach ($contents as $content) {
            if (!is_array($content)) {
                continue;
            }
            foreach ($content as $name => $entity) {
                //weird nesting depending on entity type
                if (!is_array($entity)) {
                    continue;
                }
                $json = json_encode($entity);
                $filename = $cacheDir . "$name";
                if (is_file($filename)) {
                    unlink($filename);
                }
                file_put_contents($filename, $json);
            }
        }
    }

    protected function readCacheMetaContents()
    {
        $metaArray[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]] = [];

        return $metaArray;
    }
    protected function buildCacheMetaContents($contents)
    {
        $filePath = self::CACHE_DATA_DIR . self::SCOPE_TO_ARRAY_KEY[$this->defaultScope] . '-meta';
        $metaArray = [];
        $metaArray[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]] = [];
        //outer nodeName
        foreach ($contents as $key => $value) {
            if (!is_array($value)) {
                $metaArray[$key] = $value;
            }
        }
        //inner stuff like nodeName
        foreach ($contents[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]] as $key => $value) {
            if (!is_array($value)) {
                $metaArray[self::SCOPE_TO_ARRAY_KEY[$this->defaultScope]][$key] = $value;
            }
        }

        if (is_file($filePath)) {
            unlink($filePath);
        }
        file_put_contents($filePath, json_encode($metaArray));
    }
}
