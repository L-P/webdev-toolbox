<?php
declare(strict_types=1);

namespace WebdevToolbox\Runner;

use WebdevToolbox\Timer;

class Job
{
    use \lpeltier\Struct;

    /**
     * @var string unique name
     */
    public $name;

    /**
     * @var string file this job will read.
     */
    public $input;

    /**
     * @var string[] files this job will write.
     */
    public $outputs = [];

    /**
     * @var string[] a single command splitted on multiple lines to keep the
     * JSON readable. This will be joined without a separator so remember to
     * end lines with a semi-colon if you want to write a command per line.
     */
    public $command = [];

    /**
     * @var string[] variables to substitute in the command, ie. {pouet} will
     * be replaced by the value at the "pouet" key in this array.
     */
    public $variables = [];

    /**
     * @var string what other job this on 'overrides', ie. copies and then
     * replaces variables
     */
    public $overrides;

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

    /**
     * Take a job and override its properties with ours.
     *
     * Only variables will be merged and replaced, all other properties will be
     * fully replaced.
     *
     * @param Job $job job to override
     */
    public function override(Job $job): self
    {
        $data = [
            // Fully overrides those.
            'name'    => $this->name,
            'input'   => $this->input === null ? $job->input : $this->input,
            'outputs' => empty($this->outputs) ? $job->outputs : $this->outputs,
            'command' => empty($this->command) ? $job->command : $this->command,

            // Only merge variables
            'variables' => array_replace($job->variables, $this->variables),
        ];

        return new Job($data);
    }

    /// Execute the command and return its execution stats
    public function run(): Stat
    {
        $command = implode('', $this->command);
        $timer = Timer::create();
        passthru($command, $returnCode);
        $timer->end();

        $reduceSize = function (int $carry, string $path) : int {
            return file_exists($path)
                ? $carry + filesize($path)
                : $carry
            ;
        };

        return new Stat([
            'name' => $this->name,
            'time' => $timer->elapsed(),
            'size' => array_reduce($this->outputs, $reduceSize, 0),
            'returnCode' => $returnCode,
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
            'input'   => is_null($this->input) ? null : escapeshellarg($this->input),
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

    /**
     * Remove job outputs from disk and return the path deleted.
     */
    public function clean(): array
    {
        return array_filter(array_map(function (string $path) {
            if (is_file($path) && is_writeable(dirname($path))) {
                unlink($path);
                return $path;
            }

            return null;
        }, $this->outputs));
    }
}
