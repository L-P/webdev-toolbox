<?php

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class AdminerCommand extends Command
{
    protected static $DOWNLOADS = [
        'index.php'   => 'https://adminer.org/latest-en.php',
        'adminer.css' => 'https://raw.github.com/vrana/adminer/master/designs/hever/adminer.css'
    ];

    protected function configure()
    {
        $this
            ->setName('adminer')
            ->setDescription('Launch Adminer in a browser tab.')
            ->addOption(
                'force-update',
                null,
                InputOption::VALUE_NONE,
                'Update adminer.php even if it\'s already present.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $cacheDir = $this->getCacheDirPath();
        $update = (bool) $input->getOption('force-update');

        $this->prepareCache($cacheDir, self::$DOWNLOADS, $update);

        $port = 8083;
        if ($this->serverIsRunning($port)) {
            return 0;
        }

        $this->runServer($cacheDir, $port);
        $this->launchBrowser($port);
    }

    /// @return string path to cache directory.
    private function getCacheDirPath()
    {
        $home = posix_getpwuid(posix_getuid())['dir'];
        return "$home/.cache/adminer";
    }

    /// @param int $port
    private function launchBrowser($port)
    {
        $host = "localhost:$port";
        exec('firefox -remote "ping()" > /dev/null 2>&1', $_, $running);
        if ($running === 0) {
            exec("firefox -remote 'openurl(http://$host/, new-tab)' > /dev/null 2>&1 &");
        } else {
            exec("firefox 'http://$host/' > /dev/null 2>&1 &");
        }
    }

    /**
     * @param string $cacheDir
     * @param int $port
     */
    private function runServer($cacheDir, $port)
    {
        exec(sprintf(
            "cd %s && setsid php -S localhost:%s > /dev/null 2>&1 &",
            escapeshellarg($cacheDir),
            (int) $port
        ));
    }

    /// @return bool true if adminer is already being served.
    private function serverIsRunning($port)
    {
        exec(
            sprintf(
                "netstat -plant 2> /dev/null | grep -E '^tcp.*:$port.*php$'",
                (int) $port
            ),
            $_,
            $exit
        );
        return $exit === 0;
    }

    /**
     * Create and populate cache if needed.
     *
     * @param string $cacheDir path to cache directory.
     * @param string[] $downloads {dest: url}
     * @param bool $update re-download even when cached
     */
    private function prepareCache($cacheDir, $downloads, $update)
    {
        if (!file_exists($cacheDir)) {
            if (mkdir($cacheDir) !== true) {
                throw new \RuntimeException("Could not create cache directory: `$cacheDir`.");
            }
        }

        if (!is_dir($cacheDir)) {
            throw new \RuntimeException("Cache directory is not a folder: `$cacheDir`.");
        }

        if (!is_writable($cacheDir)) {
            throw new \RuntimeException("Can't write to cache directory: `$cacheDir`.");
        }

        foreach ($downloads as $destName => $url) {
            $dest = "$cacheDir/$destName";

            if (!file_exists($dest) || $update) {
                $contents = file_get_contents($url);
                if ($contents === false) {
                    throw new \RuntimeException("Unable to download `$url`.");
                }

                $written = file_put_contents($dest, $contents);

                if ($written !== strlen($contents)) {
                    throw new \RuntimeException("Unable to write to `$dest`.");
                }
            }
        }
    }
}
