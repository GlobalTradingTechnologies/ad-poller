<?php
/**
 * This file is part of the Global Trading Technologies Ltd ad-poller package.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * (c) fduch <alex.medwedew@gmail.com>
 *
 * Date: 22.08.17
 */

namespace Gtt\ADPoller\Command;

use Exception;
use Gtt\ADPoller\Poller;
use Gtt\ADPoller\PollerCollection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Command to run registered pollers
 *
 * @author fduch <alex.medwedew@gmail.com>
 */
class PollCommand extends Command
{
    /**
     * Poller collection
     *
     * @var PollerCollection
     */
    private $pollerCollection;

    /**
     * PollCommand constructor.
     *
     * @param PollerCollection $pollerCollection
     */
    public function __construct(PollerCollection $pollerCollection)
    {
        $this->pollerCollection = $pollerCollection;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setDefinition(array(
                new InputOption(
                    'poller',
                    null,
                    InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY,
                    'List of pollers to be executed. All the registered pollers would be executed by default'
                ),
                new InputOption(
                    'force-full-sync',
                    null,
                    InputOption::VALUE_NONE,
                    'Runs specified pollers (or all the pollers by default) forcing full sync'
                ),
            ))
            ->setName('gtt:pollers:run')
            ->setDescription('Run registered pollers')
            ->setHelp(<<<EOT
This <info>%command.name%</info> runs registered Active Directory pollers
EOT
            );
    }

    /**
     * Tries to execute action specified
     *
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $pollerNames   = $input->getOption('poller');
        $forceFullSync = $input->getOption('force-full-sync');

        if ($pollerNames) {
            $pollersToRun = [];
            foreach ($pollerNames as $name) {
                $pollersToRun[] = $this->pollerCollection->getPoller($name);
            }
        } else {
            $pollersToRun = $this->pollerCollection;
        }

        $exitCode = 0;
        foreach ($pollersToRun as $poller) {
            $isSuccessfulRun = $this->runPoller($poller, $forceFullSync, $output);
            if (!$isSuccessfulRun) {
                $exitCode = 1;
            }
        }

        return $exitCode;
    }

    /**
     * Runs poller specified
     *
     * @param Poller          $poller        poller
     * @param bool            $forceFullSync force full sync mode flag
     * @param OutputInterface $output        output
     *
     * @return bool
     */
    private function runPoller(Poller $poller, $forceFullSync, OutputInterface $output)
    {
        $output->write(
            sprintf("Run poller <info>%s</info>, force mode is <comment>%s</comment>: ", $poller->getName(), $forceFullSync ? 'on' : 'off')
        );
        try {
            $processed = $poller->poll($forceFullSync);
            $output->writeln("<info>OK</info> (Processed: <comment>$processed</comment>)");

            return true;
        } catch (Exception $e) {
            $output->writeln("<error>FAIL</error> (Details: <error>{$e->getMessage()}</error>)");

            return false;
        }
    }
}
