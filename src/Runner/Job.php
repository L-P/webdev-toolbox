<?php
declare(strict_types=1);

namespace WebdevToolbox\Runner;

use WebdevToolbox\Timer;

class Job
{
    use \lpeltier\Struct;

    /// @var string
    public $name;

    /// @var string[]
    public $input = [];

    /// @var string[]
    public $outputs = [];

    /// @var string[]
    public $command = [];

    /// @var string[]
    public $variables = [];

    /**
     * Expand variables in string templates.
     */
    public function expand(): self
    {
        $vars = $this->getCommandReplacementSet();
        $replace = function (string $command) use ($vars) : string {
            return str_replace(array_keys($vars), $vars, $command);
        };

        $job = clone $this;
        $job->command = array_map($replace, $this->command);

        return $job;
    }

    public function run()
    {
        $command = implode('', $this->command);
        $timer = Timer::create();
        passthru($command, $returnCode);
        $timer->end();

        $reduceSize = function (int $carry, string $path) : int {
            return $carry + filesize($path);
        };

        return new Stats([
            'time' => $timer->elapsed(),
            'size' => array_reduce($this->outputs, $reduceSize, 0),
            'returnCode' => returnCode(),
        ]);
    }

    /**
     * Return true if any of the output file is missing.
     *
     * A job with no defined outputs should always run.
     */
    public function shouldRun(): bool
    {
        if (count($this->outputs) === 0) {
            return true;
        }

        $outputExists = array_map('file_exists', $this->outputs);
        return in_array(false, $outputExists, true);
    }

    /**
     * Return true if the job can run (its input exists).
     *
     * A job with no input file can always run.
     */
    public function canRun(): bool
    {
        if ($this->input === null) {
            return true;
        }

        return file_exists($this->input);
    }

    /**
     * Get replacement set for command template interpolation.
     *
     * @return string[]
     */
    private function getCommandReplacementSet(): array
    {
        $vars = [
            'name'    => $this->name,
            'input'   => escapeshellarg($this->input),
            'outputs' => implode(' ', array_map('escapeshellarg', $this->outputs)),
        ] + $this->variables;

        $wrap = function (string $str) : string {
            return sprintf('{%s}', $str);
        };

        return array_combine(
            array_map($wrap, array_keys($vars)),
            $vars
        );
    }
}
