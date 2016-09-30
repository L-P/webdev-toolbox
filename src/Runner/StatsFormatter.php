<?php
declare(strict_types=1);

namespace WebdevToolbox\Runner;

use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Output\BufferedOutput;

class StatsFormatter
{
    use \lpeltier\Struct;

    public $stats = [];
    public $referenceName;
    private $reference;

    public function run(): string
    {
        $this->reference = $this->getReferenceStat($this->referenceName);
        $formatted = $this->formatStats();

        $output = new BufferedOutput();
        $table = new Table($output);
        $table->setHeaders(['name', 'time', 'time_diff', 'size', 'size_diff', 'return_code']);
        $table->setRows($this->formatStats());
        $table->render();

        return $output->fetch();
    }

    private function formatStats(): array
    {
        return array_map(function ($stat) {
            if ($stat->name === $this->referenceName) {
                $size_diff = $time_diff = '-';
            } else {
                $size_diff = $time_diff = '+∞';
                if ($this->reference->size !== 0) {
                    $size_diff = 1 - ($stat->size / $this->reference->size);
                    $size_diff = round($size_diff, 2) . '×';
                }
                if ($this->reference->time !== 0.0) {
                    $time_diff = 1 - ($stat->time / $this->reference->time);
                    $time_diff = round($time_diff, 2) . '×';
                }
            }

            return [
                'name' => $stat->name,
                'time' => $stat->formatTime(),
                'time_diff' =>  $time_diff,
                'size' => $stat->formatSize(),
                'size_diff' =>  $size_diff,
                'return_code' => $stat->returnCode,
            ];
        }, $this->stats);
    }

    public function getReferenceStat(string $reference): Stat
    {
        if (!array_key_exists($reference, $this->stats)) {
            throw new \Exception("Could not find name in results: $reference");
        }

        return $this->stats[$reference];
    }
}
