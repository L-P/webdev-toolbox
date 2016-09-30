<?php
declare(strict_types=1);

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use WebdevToolbox\Runner\Job;
use WebdevToolbox\Runner\Stats;
use WebdevToolbox\Runner\Conf;
use WebdevToolbox\FileNotFoundException;

class Runner extends Command
{
    private $output;
    private $input;

    protected function configure()
    {
        $this
            ->setName('runner')
            ->setDescription('Task runner akin to make/ninja.')
            //->addArgument('job', InputArgument::OPTIONAL, 'Run a single job by name.')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the jobs configuration file.',
                './jobs.json'
            )
            ->addOption(
                'stats',
                null,
                InputOption::VALUE_REQUIRED,
                'Where to write the execution stats.',
                './stats.json'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Dry-run. Show commands but don\'t run them.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $configPath = $this->input->getOption('config');
        $dryRun = $this->input->getOption('dry-run');
        $config = $this->getConfig($configPath);

        $this->runJobs($config->jobs, $dryRun);
    }

    private function runJobs(array $jobs, $dryRun)
    {
        foreach ($jobs as $job) {
            if (!$job->shouldRun() || !$job->canRun()) {
                continue;
            }

            // Show as single command but keep original formatting
            $commands = implode(" \\\n", $job->command);
            $this->output->writeln($commands);
            if (!$dryRun) {
                $jobStats = $job->run();
                $this->writeJobStats($job->name, $jobStats);
            }
        }
    }

    private function writeJobStats(string $jobName, Stats $jobStats)
    {
        $statsPath = $this->input->getOption('stats');
        try {
            $stats = $this->getStats($statsPath);
        } catch (FileNotFoundException $e) {
            $stats = [];
        }

        $stats[$jobName] = $jobStats;

        file_put_contents($statsPath, json_encode($stats, JSON_PRETTY_PRINT));
    }

    private function getStats(string $path): array
    {
        return $this->getJsonData($path);
    }

    private function getConfig(string $path): Conf
    {
        $data = $this->getJsonData($path);
        return (new Conf($data))->expand();
    }

    private function getJsonData(string $path): array
    {
        if (!file_exists($path)) {
            throw new FileNotFoundException($path);
        }
        if (!is_file($path) || !is_readable($path)) {
            throw new FileNotReadableException($path);
        }

        $data = json_decode(file_get_contents($path), true);
        if (!is_array($data)) {
            return new \Exception("Invalid configuration file: $path");
        }

        return $data;
    }
}
