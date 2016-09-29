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
    protected function configure()
    {
        $this
            ->setName('runner')
            ->setDescription('Task runner akin to make/ninja.')
            //->addArgument('job', InputArgument::OPTIONAL, 'Run a single job by name.')
            ->addOption(
                'config',
                null,
                InputOption::VALUE_OPTIONAL,
                'Path to the jobs configuration file.',
                './jobs.json'
            )
            ->addOption(
                'stats',
                null,
                InputOption::VALUE_OPTIONAL,
                'Where to write the execution stats.',
                './stats.json'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $configPath = $input->getOption('config');
        $config = $this->getConfig($configPath);

        $this->runJobs($config->jobs);
    }

    private function runJobs(array $jobs)
    {
        foreach ($jobs as $job) {
            if (!$job->shouldRun() || !$job->canRun()) {
                continue;
            }

            $jobStats = $job->run();
            $this->writeJobStats($job->name, $jobStats);
        }
    }

    private function writeJobStats(string $jobName, Stats $jobStats)
    {
        $statsPath = $input->getOption('stats');
        $stats = $this->getStats($statsPath);
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
