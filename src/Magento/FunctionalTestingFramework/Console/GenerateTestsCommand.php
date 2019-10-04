<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Magento\FunctionalTestingFramework\Config\MftfApplicationConfig;
use Magento\FunctionalTestingFramework\Exceptions\TestFrameworkException;
use Magento\FunctionalTestingFramework\Suite\SuiteGenerator;
use Magento\FunctionalTestingFramework\Test\Handlers\TestObjectHandler;
use Magento\FunctionalTestingFramework\Util\Manifest\ParallelTestManifest;
use Magento\FunctionalTestingFramework\Util\Manifest\TestManifestFactory;
use Magento\FunctionalTestingFramework\Util\TestGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GenerateTestsCommand extends BaseGenerateCommand
{
    /**
     * Configures the current command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('generate:tests')
            ->setDescription('Run validation and generate all test files and suites based on xml declarations')
            ->addArgument(
                'name',
                InputArgument::OPTIONAL | InputArgument::IS_ARRAY,
                'name(s) of specific tests to generate'
            )->addOption("config", 'c', InputOption::VALUE_REQUIRED, 'default, singleRun, or parallel', 'default')
            ->addOption(
                'time',
                'i',
                InputOption::VALUE_REQUIRED,
                'Used in combination with a parallel configuration, determines desired group size (in minutes)',
                10
            )->addOption(
                'tests',
                't',
                InputOption::VALUE_REQUIRED,
                'A parameter accepting a JSON string used to determine the test configuration'
            );

        parent::configure();
    }

    /**
     * Executes the current command.
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return void
     * @throws TestFrameworkException
     * @throws \Magento\FunctionalTestingFramework\Exceptions\TestReferenceException
     * @throws \Magento\FunctionalTestingFramework\Exceptions\XmlException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $tests = $input->getArgument('name');
        $config = $input->getOption('config');
        $json = $input->getOption('tests'); // for backward compatibility
        $force = $input->getOption('force');
        $time = $input->getOption('time') * 60 * 1000; // convert from minutes to milliseconds
        $debug = $input->getOption('debug') ?? MftfApplicationConfig::LEVEL_DEVELOPER; // for backward compatibility
        $remove = $input->getOption('remove');
        $verbose = $output->isVerbose();
        $allowSkipped = $input->getOption('allow-skipped');

        // Set application configuration so we can references the user options in our framework
        MftfApplicationConfig::create(
            $force,
            MftfApplicationConfig::GENERATION_PHASE,
            $verbose,
            $debug,
            $allowSkipped
        );

        if (!empty($tests)) {
            $json = $this->getTestAndSuiteConfiguration($tests);
        }

        if ($json !== null && !json_decode($json)) {
            // stop execution if we have failed to properly parse any json passed in by the user
            throw new TestFrameworkException("JSON could not be parsed: " . json_last_error_msg());
        }

        if ($config === 'parallel' && $time <= 0) {
            // stop execution if the user has given us an invalid argument for time argument during parallel generation
            throw new TestFrameworkException("time option cannot be less than or equal to 0");
        }

        // Remove previous GENERATED_DIR if --remove option is used
        if ($remove) {
            $this->removeGeneratedDirectory($output, $verbose ||
                ($debug !== MftfApplicationConfig::LEVEL_NONE));
        }


        // METRICS GATHERING
        $perModule = true;
        $moduleToTotalTest = [];
        $moduleToSkippedTest = [];
        $moduleToVersion = [];
        $blackList = ['DevDocs'];
        $testObjects = TestObjectHandler::getInstance()->getAllObjects();
        $totalTests = 0;
        $testsBySeverity = [];
        $skippedBySeverity = [];
        $skippedTests = 0;
        $skippedTestName = [];
        $skippedTestName[] = "VERSION|MODULE|TESTCASEID|TESTNAME|SEVERITY|SKIPPEDIDS";
        foreach ($testObjects as $testObject) {
            if (array_search($testObject->getAnnotations()['features'][0], $blackList) !== false) {
                continue;
            }
            $totalTests++;

            if (!isset($testObject->getAnnotations()['severity'][0])) {
                $severity = "NO SEVERITY SPECIFIED";
            } else {
                $severity = $testObject->getAnnotations()['severity'][0];
            }
            if (!isset($testsBySeverity[$severity])) {
                $testsBySeverity[$severity] = 0;
            }
            $testsBySeverity[$severity] += 1;

            if ($perModule) {
                if (!isset($moduleToTotalTest[$testObject->getAnnotations()['features'][0]])) {
                    $moduleToTotalTest[$testObject->getAnnotations()['features'][0]] = 1;
                } else {
                    $moduleToTotalTest[$testObject->getAnnotations()['features'][0]] += 1;
                }
            }
            $firstFilename = explode(',', $testObject->getFilename())[0];
            $realpath = realpath($firstFilename);

            if (strpos($realpath, '/Inventory') !== false) {
                $moduleToVersion[$testObject->getAnnotations()['features'][0]] = 'MSI';
            } elseif (strpos($realpath, 'magento2ce') !== false) {
                $moduleToVersion[$testObject->getAnnotations()['features'][0]] = 'CE';
            } elseif (strpos($realpath, 'magento2ee') !== false) {
                $moduleToVersion[$testObject->getAnnotations()['features'][0]] = 'EE';
            } elseif (strpos($realpath, 'magento2b2b') !== false) {
                $moduleToVersion[$testObject->getAnnotations()['features'][0]] = 'B2B';
            } elseif (strpos($realpath, 'PageBuilder') !== false) {
                $moduleToVersion[$testObject->getAnnotations()['features'][0]] = 'PB';
            }

            if ($testObject->isSkipped()) {
                $skippedTests++;
                $testCaseId = $testObject->getAnnotations()['testCaseId'] ?? ['NONE'];
                $skipString = "";
                $issues = $testObject->getAnnotations()['skip'] ?? null;
                if (isset($issues)) {
                    $skipString .= implode(",", $issues);
                } else {
                    $skipString .= "NO ISSUES SPECIFIED";
                }

                $skippedTestName[] = $moduleToVersion[$testObject->getAnnotations()['features'][0]]
                    . "|" . $testObject->getAnnotations()['features'][0]
                    . "|" . $testCaseId[0]
                    . "|" . $testObject->getName()
                    . "|" . $severity
                    . "|" . $skipString;

                if (!isset($testObject->getAnnotations()['severity'][0])) {
                    $severity = "NO SEVERITY SPECIFIED";
                } else {
                    $severity = $testObject->getAnnotations()['severity'][0];
                }
                if (!isset($skippedBySeverity[$severity])) {
                    $skippedBySeverity[$severity] = 0;
                }
                $skippedBySeverity[$severity] += 1;

                if ($perModule) {
                    if (!isset($moduleToSkippedTest[$testObject->getAnnotations()['features'][0]])) {
                        $moduleToSkippedTest[$testObject->getAnnotations()['features'][0]] = 1;
                    } else {
                        $moduleToSkippedTest[$testObject->getAnnotations()['features'][0]] += 1;
                    }
                }

            }
        }
        print (PHP_EOL . "TOTAL TESTS (INCLUDING SKIPPED):\t{$totalTests}");
        print (PHP_EOL . "SKIPPED TESTS:\t{$skippedTests}");
        print (PHP_EOL . "TOTAL TESTS BY SEVERITY (INCLUDING SKIPPED):\n");
        foreach ($testsBySeverity as $severity => $value) {
            $skipped = $skippedBySeverity[$severity] ?? 0;
            print ("\t\t{$severity}:\t{$value}\t{$skipped}\n");
        }
        if ($perModule) {
            $total = array_sum($moduleToTotalTest);
            $totalskip = array_sum($moduleToSkippedTest);
            print (PHP_EOL . PHP_EOL . "TESTS PER MODULE: VERSION|MODULE|UNSKIPPED|SKIPPED");
            foreach ($moduleToTotalTest as $module => $total) {
                $skippedSet = 0;
                $version = $moduleToVersion[$module];
                if (isset($moduleToSkippedTest[$module])) {
                    $skippedSet = $moduleToSkippedTest[$module];
                }
                $adjustedTotal = $total - $skippedSet;
                print (PHP_EOL . "$version|$module|$adjustedTotal|$skippedSet");
            }
        }


        print (PHP_EOL . PHP_EOL . "SKIPPED TESTS:" . PHP_EOL . implode(PHP_EOL, $skippedTestName));
        print (PHP_EOL);
        // END METRICS GATHERING
    }
}
