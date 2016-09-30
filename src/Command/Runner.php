<?php
declare(strict_types=1);

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use WebdevToolbox\FileNotFoundException;
use WebdevToolbox\Runner\Conf;
use WebdevToolbox\Runner\Job;
use WebdevToolbox\Runner\Stat;
use WebdevToolbox\Runner\StatsFormatter;

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
                'config-file',
                null,
                InputOption::VALUE_REQUIRED,
                'Path to the jobs configuration file.',
                './jobs.json'
            )
            ->addOption(
                'stats-file',
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
            ->addOption(
                'stats',
                null,
                InputOption::VALUE_NONE,
                'Display pretty stats.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;

        $dryRun = $this->input->getOption('dry-run');
        $config = $this->getConfig($this->input->getOption('config-file'));

        if ($this->input->getOption('stats')) {
            $stats = $this->getStats($this->input->getOption('stats-file'));
            $this->displayStats($stats, $config->statsReference);
        } else {
            $this->runJobs($config->jobs, $dryRun);
        }
    }

    private function displayStats(array $stats, string $referenceName)
    {
        $this->output->writeln(
            (new StatsFormatter(compact('stats', 'referenceName')))->run()
        );
    }

    private function runJobs(array $jobs, bool $dryRun)
    {
        foreach ($jobs as $job) {
            if (!$job->shouldRun() || !$job->canRun()) {
                $this->output->writeln('Nothing to be done for job: ' . $job->name);
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

    private function writeJobStats(string $jobName, Stat $jobStats)
    {
        $statsPath = $this->input->getOption('stats-file');
        try {
            $stats = $this->getStats($statsPath);
        } catch (FileNotFoundException $e) {
            $stats = [];
        }

        /* We have the name in each row but we'll also use it as key
         * to ensure uniqueness. */
        $stats[$jobName] = $jobStats;

        file_put_contents($statsPath, json_encode($stats, JSON_PRETTY_PRINT));
    }

    private function getStats(string $path): array
    {
        $data = $this->getJsonData($path);
        return array_map(function (array $v) : Stat {
            return new Stat($v);
        }, $data);
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
