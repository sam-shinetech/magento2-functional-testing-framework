<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Magento\FunctionalTestingFramework\Util\Filesystem\DirSetupUtil;
use Magento\FunctionalTestingFramework\Util\TestGenerator;
use Symfony\Component\Filesystem\Filesystem;

class BaseGenerateCommand extends Command
{
    /**
     * Configures the base command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->addOption(
            'remove',
            'r',
            InputOption::VALUE_NONE,
            'remove previous generated suites and tests'
        )->addOption(
            'env-template',
            'e',
            InputOption::VALUE_OPTIONAL,
            'Specify the ENV template'
        );;
    }

    /**
     * Remove GENERATED_DIR if exists when running generate:tests.
     *
     * @param OutputInterface $output
     * @param bool $verbose
     * @return void
     */
    protected function removeGeneratedDirectory(OutputInterface $output, bool $verbose)
    {
        $generatedDirectory = TESTS_MODULE_PATH . DIRECTORY_SEPARATOR . TestGenerator::GENERATED_DIR;

        if (file_exists($generatedDirectory)) {
            DirSetupUtil::rmdirRecursive($generatedDirectory);
            if ($verbose) {
                $output->writeln("removed files and directory $generatedDirectory");
            }
        }
    }

    /**
     * Switch env
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function switchEnv(InputInterface $input, OutputInterface $output)
    {
        $fileSystem = new Filesystem();
        $envPath = TESTS_BP . DIRECTORY_SEPARATOR . '.env';
        $envTemplate = $input->getOption('env-template') ?? 'default';
        $envTemplateDir = FW_BP . "/etc/config/envs/";
        $envTemplateFile = ".env.$envTemplate";
        $envTemplatePath = $envTemplateDir . $envTemplateFile;
        if (!$fileSystem->exists($envTemplatePath)) {
            $envTemplateFile = '.env.default';
            $envTemplatePath = $envTemplateDir . $envTemplateFile;
        }
        $fileSystem->copy($envTemplatePath, $envPath, true);
        $output->writeln("<fg=green;options=bold>Apply ENV from $envTemplateFile</>");
    }
}
