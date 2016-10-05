<?php
declare(strict_types=1);

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use WebdevToolbox\NfsMap\Mount;
use WebdevToolbox\NfsMap\Host;

class NfsMap extends Command
{
    protected function configure()
    {
        $this
            ->setName('nfsmap')
            ->setDescription('Try to understand how deep you are in your NFS mess.')
            ->addArgument(
                'host',
                InputArgument::IS_ARRAY | InputArgument::REQUIRED,
                'Host to SSH into.'
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $hosts = $this->getHosts($input->getArgument('host'));
        $edges = $this->getHostGraphEdges($hosts);

        $dot = $this->renderGraph($edges);
        $output->writeln($dot);
    }

    /**
     * @return Host[]
     */
    private function getHosts(array $hostNames): array
    {
        return array_map(function ($name) {
            $mounts = $this->getMounts($name);
            $ips = $this->getIps($name);
            return new Host(compact('name', 'mounts', 'ips'));
        }, $hostNames);
    }

    /**
     * @return Mount[] mounts from the given host.
     */
    private function getMounts(string $host): array
    {
        return array_map(
            function ($line) use ($host) {
                list($source, $size, $used, , , $target) = explode(' ', $line);

                return new Mount(compact('host', 'source', 'target') + [
                    'size' => (int) $size,
                    'used' => (int) $used,
                ]);
            },
            $this->getDf($host)
        );
    }

    private function getIps(string $host): array
    {
        $cmd = sprintf(
            'ssh %s ip -4 a | tr -s " " | grep inet'   // list IPv4
            . ' | grep -v "scope host lo"'             // remove localhost
            . ' | cut -f 3 -d " " | cut -d "/" -f 1 ', // IP
            escapeshellarg($host)
        );

        exec($cmd, $raw);
        return $raw;
    }

    /**
     * @return string[] df result from the given host, one line per array entry.
     *
     * Results are filtered, only 'real' filesystems are shown.
     */
    private function getDf(string $host): array
    {
        $cmd = sprintf(
            'ssh %s df -B 1 -x devtmpfs -x tmpfs'
            . ' | tail -n +2' // remove header
            . ' | tr -s " "', // collapse columns
            escapeshellarg($host)
        );

        exec($cmd, $raw);

        return array_filter($raw, function ($v) {
            $type = explode(' ', $v)[0];
            return !in_array($type, ['none', 'devtmpfs'], true);
        });
    }

    /**
     * @return string[] [
     *  'source' => [host, mountPath],
     *  'destination' => [remote, path]
     * ]
     */
    private function getHostGraphEdges(array $hosts)
    {
        $edges = [];

        foreach ($hosts as $host) {
            foreach ($host->mounts as $mount) {
                // Local filesystem, skip.
                if (strpos($mount->source, ':') === false) {
                    continue;
                }

                list($remote, $path) = explode(':', $mount->source, 2);

                $edges[] = [
                    'source' => [$host->name, $mount->target],
                    'destination' => [$this->nameFromIp($remote, $hosts), $path]
                ];
            }
        }

        return $edges;
    }

    /**
     * Return a server name (as given in arguments) from an IP address
     */
    private function nameFromIp(string $ip, array $hosts): string
    {
        static $memoize = null;

        if ($memoize === null) {
            foreach ($hosts as $host) {
                foreach ($host->ips as $hostIp) {
                    $memoize[$hostIp] = $host->name;
                }
            }
        }

        return array_key_exists($ip, $memoize) ? $memoize[$ip] : $ip;
    }

    /**
     * @return string graphviz dot digraph
     */
    private function renderGraph(array $edges): string
    {
        $tpl = <<<EOF
digraph mounts {
    %s
}
EOF;
        $edgesStr = implode("\n    ", array_map(function ($v) {
            $source = $v['source'];
            $destination = $v['destination'];

            return sprintf(
                '"%s" -> "%s" [label="  %s  "];',
                addcslashes($source[0], '"'),
                addcslashes($destination[0], '"'),
                addcslashes("{$source[1]}:{$destination[1]}", '"')
            );
        }, $edges));

        return sprintf($tpl, $edgesStr);
    }
}
