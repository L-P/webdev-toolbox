<?php
declare(strict_types=1);

namespace WebdevToolbox\Runner;

class Conf
{
    use \lpeltier\Struct;

    /// @var Job[]
    public $jobs = [];

    /// @var string
    public $statsReference;

    public function __construct(array $data)
    {
        $data += ['jobs' => [], 'statsReference' => null];

        foreach ($data['jobs'] as $job) {
            /* HACK: is_array($job) is true but "must be of the type array,
             * object given", wtf. */
            $this->jobs[] = new Job((array) $job);
        }

        $this->statsReference = $data['statsReference'];
    }

    /**
     * Expand variables in string templates.
     */
    public function expand(): self
    {
        foreach ($this->jobs as $job) {
            $jobs[] = $job->expand();
        }

        return new self([
            'jobs' => $jobs,
            'statsReference' => $this->statsReference,
        ]);
    }
}
