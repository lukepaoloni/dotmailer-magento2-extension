<?php

namespace Dotdigitalgroup\Email\Console\Command;

use Dotdigitalgroup\Email\Console\Command\Provider\SyncProvider;
use Dotdigitalgroup\Email\Model\Sync\SyncInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

class ImporterSyncsCommand extends Command
{
    /**
     * @var SyncProvider
     */
    private $syncProvider;

    /**
     * ImporterSyncsCommand constructor
     * @param SyncProvider $syncProvider
     */
    public function __construct(SyncProvider $syncProvider)
    {
        $this->syncProvider = $syncProvider;
        parent::__construct();
    }

    /**
     * Configure this command
     */
    protected function configure()
    {
        $this
            ->setName('dotdigital:sync')
            ->setDescription(__('Run syncs to populate email_ tables before importing to Engagement Cloud'))
            ->addArgument(
                'sync',
                InputArgument::OPTIONAL,
                sprintf('%s (%s)',
                    __('The name of the sync to run'),
                    implode('; ', $this->syncProvider->getAvailableSyncs())
                )
            )
        ;
        parent::configure();
    }

    /**
     * Execute the data migration
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|void|null
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (!$requestedSync = $input->getArgument('sync')) {
            $requestedSync = $this->askForSync($input, $output);
        }

        // get the requested sync class
        /** @var SyncInterface $syncClass */
        $syncClass = $this->syncProvider->$requestedSync;

        $start = microtime(true);
        $output->writeln(sprintf('[%s] %s: %s',
            date('Y-m-d H:i:s'),
            __('Started running sync'),
            get_class($syncClass)
        ));

        // run the sync
        $syncClass->sync();

        $output->writeln(sprintf('[%s] %s %s',
            date('Y-m-d H:i:s'),
            __('Complete in'),
            round(microtime(true) - $start, 2)
        ));
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return string
     */
    private function askForSync(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');
        $syncQuestion = new ChoiceQuestion(
            __('Please select an Engagement Cloud sync to run'),
            array_values($this->syncProvider->getAvailableSyncs())
        );
        $syncQuestion->setErrorMessage(__('Please select a sync'));
        return $helper->ask($input, $output, $syncQuestion);
    }
}