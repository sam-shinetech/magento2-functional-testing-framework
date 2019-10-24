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
            $fileToTime = $this->explodeCache(file_get_contents($fileToTimePath));
        }
        if (is_file($entityToFilesPath)) {
            $entityToFiles = $this->explodeCache(file_get_contents($entityToFilesPath));
        }

        // Cache Vars
        $cacheInvalidated = false;
        $filesToRead = [];

        // Read cache before anything else
        $output = null;
        if (is_file(TESTS_BP . '/_cache/data/' . $this->defaultScope)) {
            $output = $this->readCache(TESTS_BP . '/_cache/data/' . $this->defaultScope);
        }

        // Go straight to parsing if cache doesn't exist
        // Temporary to find parent key
        if ($output !== null) {
            $entityArrayKey = '';
            foreach ($output as $key => $value) {
                if (is_array($value)) {
                    $entityArrayKey = $key;
                    break;
                }
            }
        }

        // First pass to find changed files
        foreach ($fileList as $key => $content) {
            $fileName = $fileList->getFilename();
            if (isset($fileToTime[$fileName]) && filemtime($fileName) <= $fileToTime[$fileName]) {
                // Do not read
                continue;
            }
            $filesToRead[$fileName] = time();
            // break cache for specific file
            $cacheInvalidated = true;
            echo 'break cache ' . $fileName . PHP_EOL;

            //peek file to find entities touched
            $xmlReader = new Service();
            $dom = $xmlReader->parse($content);
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
                    $filesToRead[$otherFile] = time();
                }
                unset($output[$entityArrayKey][$entityKey]);
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
            $output = $this->converter->convert($configMerger->getDom());
        }

        // Rebuild cache
        if ($cacheInvalidated) {
            // TEMPORARY to find root element
            $entityArrayKey = '';
            foreach ($output as $key => $value) {
                if (is_array($value)) {
                    $entityArrayKey = $key;
                    break;
                }
            }
            // Last pass to rebuild entity->filename relationship
            $entityToFiles = [];
            foreach ($output[$entityArrayKey] as $key => $entity) {
                if (!is_array($entity)) {
                    continue;
                }
                $entityToFiles[$key] = $entity['filename'];
            }
            foreach ($filesToRead as $file => $time) {
                $fileToTime[$file] = $time;
            }
            $this->buildCache($output, TESTS_BP . '/_cache/data/' . $this->defaultScope);
            $this->rewriteCache($fileToTime, $fileToTimePath);
            $this->rewriteCache($entityToFiles, $entityToFilesPath);
        }

        return $output;
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

    protected function explodeCache($contents)
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

    protected function rewriteCache($contents, $filename)
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

    protected function readCache($filename)
    {
        return json_decode(file_get_contents($filename), true);

    }

    protected function buildCache($contents, $filename)
    {
        $json = json_encode($contents);
        if (is_file($filename)) {
            unlink($filename);
        }
        file_put_contents($filename, $json);
    }

    protected function invalidateEntity($contents, $entityName)
    {

    }

}
