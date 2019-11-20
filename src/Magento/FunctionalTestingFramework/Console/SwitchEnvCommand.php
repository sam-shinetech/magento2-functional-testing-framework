<?php
// @codingStandardsIgnoreFile
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
declare(strict_types = 1);

namespace Magento\FunctionalTestingFramework\Console;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class SwitchEnvCommand extends Command
{
    /**
     * Configures the base command.
     *
     * @return void
     */
    protected function configure()
    {
        $this->setName('switch:env')
            ->setDescription('Switch the env file and then build project')
            ->addArgument(
                'env',
                InputArgument::OPTIONAL,
                'Specify the ENV template',
                'default'
            );
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $this->switchEnv($input, $output);
        $setupEnvCommand = new BuildProjectCommand();
        $commandInput = new ArrayInput([]);
        $setupEnvCommand->run($commandInput, $output);
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
        $envTemplate = $input->getArgument('env') ?? 'default';
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
