<?php

namespace WebdevToolbox\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use WebdevToolbox\Timer;

class HttpPing extends Command
{
    protected function configure()
    {
        $this
            ->setName('httpping')
            ->setDescription('Ping an HTTP(S) endpoint.')
            ->addArgument(
                'url',
                InputArgument::REQUIRED,
                'Which url to ping.'
            )
            ->addOption(
                'count',
                'c',
                InputOption::VALUE_OPTIONAL,
                'Repeat ping count times, 0 means never stop.',
                0
            )
            ->addOption(
                'interval',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Interval between pings.',
                1
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $url = $input->getArgument('url');
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException("The given URL does not seem to be a valid one: `$url`.");
        }

        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'], ['http', 'https'], true)) {
            throw new \RuntimeException("Invalid scheme in url: `{$parsed['scheme']}`.");
        }

        $request = sprintf(
            "GET %s HTTP/1.1\r\n%s\r\n",
            array_key_exists('path', $parsed) ? $parsed['path'] : '',
            $this->getRawHeaders($parsed['host'])
        );

        $count = 0;
        $maxCount = (int) $input->getOption('count');
        for (;;) {
            $stats = $this->sendRequest($parsed['host'], $parsed['scheme'], $request);
            $output->writeln(json_encode($stats));

            $count += 1;
            if ($maxCount > 0 && $count >= $maxCount) {
                break;
            }

            sleep((int) $input->getOption('interval'));
        }
    }

    /**
     * @param string $host
     * @param string $request
     * @return mixed[] stats
     */
    private function sendRequest($host, $scheme, $request)
    {
        assert('substr($request, -2) === "\r\n"');
        assert('substr($request, 0, 3) === "GET"');

        $timers = [];

        $remote = sprintf(
            '%s://%s:%d',
            $scheme === 'https' ? 'tls' : 'tcp',
            $host,
            $scheme === 'http' ? 80 : 443
        );

        $time = time();
        $timers['connecting'] = Timer::create();
        $socket = stream_socket_client($remote);
        stream_set_blocking($socket, 1);
        $timers['connecting']->end();

        $timers['sending'] = Timer::create();
        fwrite($socket, $request);
        fflush($socket);
        $timers['sending']->end();

        // Actually the time to first byte, that's what Chromium does.
        // stream_select returns right away giving nothing meaningful.
        $timers['waiting'] = Timer::create();
        $size = fread($socket, 1);
        $timers['waiting']->end();

        $timers['receiving'] = Timer::create();
        while (!feof($socket)) {
            $size += strlen(fread($socket, 4096));
        }
        $timers['receiving']->end();

        fclose($socket);

        return [
            'time' => $time,
            'size' => $size,
            'timers' => array_combine(
                array_keys($timers),
                array_map(function ($v) {
                    return round($v->elapsed() * 1000);
                }, $timers)
            ),
        ];
    }

    /**
     * @param string $host value for Host header.
     * @return string
     */
    private function getRawHeaders($host)
    {
        return implode("\r\n", [
            "Host: $host",
            'User-Agent: Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:42.0, httpping) Gecko/20100101 Firefox/42.0',
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: en,en-US;q=0.7,fr-FR;q=0.3',
            'Accept-Encoding: gzip, deflate',
            'DNT: 1',
            'Connection: close',
        ]) . "\r\n";
    }
}
