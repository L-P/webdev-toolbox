<?php

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use WebdevToolbox\Conf;

class DockerShellCommand extends Command
{
    /// @var string name of the configuration file used by docker-compose.
    const COMPOSE_CONFIG = 'docker-compose.yml';

    /// @var OutputInterface
    private $out;

    /// @var Conf
    private $conf;

    protected function configure()
    {
        $this
            ->setName('docker-shell')
            ->setDescription('Log into a docker container matched by a fuzzy name.')
            ->addArgument(
                'term',
                InputArgument::REQUIRED,
                'Fuzzy search term to match against a container name.'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Don\'t actually login into the container.'
            )
            ->setHelp(wordwrap(
                "Container names are namespaced (eg. 'ns_actualname') and only "
                . "the actual name will be matched against the search term.\n"
                . "The namespace will be inferred from the name of the first "
                . "parent directory containing a `docker-compose.yml` file. "
                . "This means you need to be in your project or one of its "
                . "subdirectory for this command to match the right container."
            ))
        ;
    }

    public function __construct()
    {
        parent::__construct();

        $this->conf = new Conf();
        $home = posix_getpwuid(posix_getuid())['dir'];
        $path = "$home/.config/webdev-toolbox/docker-shell.json";
        if (file_exists($path)) {
            $this->conf->load($path);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->out = $output;

        try {
            $namespace = $this->inferNamespace(getcwd());
            $containers = array_filter($this->getRunningContainerNames(), function ($name) use ($namespace) {
                return explode('_', $name)[0] === $namespace;
            });
        } catch (\RuntimeException $e) {
            $containers = $this->getRunningContainerNames();
        }

        $name = $this->fuzzyGetContainer($containers, $input->getArgument('term'));
        $command = $this->getCommandForContainer($name);
        $this->out->writeln(implode(' ', $command), "\n");

        if (!$input->getOption('dry-run')) {
            pcntl_exec($command[0], array_slice($command, 1));
            assert('false /* unreachable */');
        }
    }

    /**
     * Get the docker namespace of a dockerized project directory or one of is
     * subdirectory.
     *
     * @param string $dir
     * @return string
     */
    private function inferNamespace($dir)
    {
        clearstatcache();

        $parts = explode('/', realpath($dir));
        foreach (range(count($parts), 1) as $i) {
            $curPath = implode('/', array_slice($parts, 0, $i));
            $composePath = "$curPath/" . self::COMPOSE_CONFIG;

            if (file_exists($composePath)) {
                return $this->pathToNamespaceSlug($curPath);
            }
        }

        throw new \RuntimeException("Unable to infer namespace from directory `$dir`.");
    }

    /**
     * @param string $path
     * @return string
     */
    private function pathToNamespaceSlug($path)
    {
        $namespace = preg_replace('`\W`', '', basename($path));
        if (strlen($namespace) <= 0) {
            throw new \RuntimeException("Unable to create a namespace from path `$path`.");
        }

        return $namespace;
    }

    /// @return string[]
    private function getRunningContainerNames()
    {
        exec('docker ps | tail -n +2 | awk "{print \\$NF}"', $out);
        return $out;
    }

    /**
     * @param string[] $containers
     * @param string $fuzz
     * @return string|null closest docker container name or null if none found.
     */
    private function fuzzyGetContainer(array $containers, $fuzz)
    {
        if (count($containers) <= 0) {
            return null;
        }

        $distance = array_combine($containers, array_map(
            function ($container) use ($fuzz) {
                return $this->getScore($container, $fuzz);
            },
            $containers
        ));

        asort($distance, SORT_NUMERIC);
        if ($this->out->isVerbose()) {
            $this->out->write('Scores: ');
            $this->out->writeln(json_encode($distance, JSON_PRETTY_PRINT));
        }

        return key($distance);
    }

    /**
     * Return abritrary string likeness score, lower is closer.
     *
     * Levenshtein alone is not enough, especially since its result depends
     * greatly on the string length, so other scoring methods are used.
     *
     * @param string $container
     * @param string $fuzz
     * @return float
     */
    private function getScore($container, $fuzz)
    {
        $name = explode('_', $container)[1];

        $scores = [
            'levenshtein' => levenshtein($fuzz, $name, 1, 3, 3),

            // Each time one one the char from the fuzz appear, add -1.
            'matches' => -1 * preg_match_all(
                sprintf('`[%s]`', preg_quote($fuzz)),
                $name
            ),
        ];

        /* Finding a whole substring adds a big bonus proportional to the
         * substring length. Another bonus is given if the substring is close
         * to the start of the name.
         * A minor malus is given if nothing is found + the name length to be
         * consistent with the start of string bonus.
         */
        if (false !== ($pos = strpos($container, $fuzz))) {
             $scores['strpos'] = -1 * strlen($fuzz);
             $scores['strpospos'] = $pos;
        } else {
            $scores['strpos'] = 2;
            $scores['strpospos'] = strlen($container);
        }

        if ($this->out->isVeryVerbose()) {
            $this->out->write("$name: ");
            $this->out->writeln(json_encode($scores, JSON_PRETTY_PRINT));
        }

        return (float) array_sum($scores);
    }

    /// @return string[]
    private function getCommandForContainer($name)
    {
        $baseArgs = [
            exec('which docker'),
            'exec', '-it',
            $name
        ];

        $conf = $this->conf->get($name);

        if ($conf->noLogin) {
            return array_merge($baseArgs, [
                '/bin/sh',
            ]);
        } else {
            return array_merge($baseArgs, [
                '/bin/login', '-p', '-f', $conf->user,
                'TERM=xterm'
            ]);
        }
    }
}
