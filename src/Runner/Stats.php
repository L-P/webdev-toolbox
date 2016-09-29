<?php
declare(strict_types=1);

namespace WebdevToolbox\Runner;

class Stats
{
    use \lpeltier\Struct;

    /// @var float
    public $time;

    /// @var int
    public $size;

    /// @var int
    public $returnCode;

    public function formatTime()
    {
        return gmdate('H:i:s', $this->time);
    }

    public function formatSize()
    {
        return $this->bytesToString($this->size);
    }

    /**
     * eg. 1024 => "1 KiB"
     *
     * @param int $bytes
     * @return string
     */
    private function bytesToString(int $bytes): float
    {
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB', 'EiB', 'ZiB', 'YiB'];

        if ($bytes === 0) {
            return '0 B';
        }

        $index = min(count($units) -1, floor(log($bytes, 1024)));
        $size = round($bytes / pow(1024, $index), 2);

        return $size . ' ' . $units[$index];
    }
}
