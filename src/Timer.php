<?php

namespace WebdevToolbox;

class Timer
{
    use \lpeltier\Struct;

    private $start = null;
    private $end = null;

    public function start()
    {
        $this->start = microtime(true);
    }

    public function end()
    {
        $this->end = microtime(true);
    }

    /**
     * Return the elapsed time between the start() and end() calls,
     * if end() was not called, return the elapsed time since the
     * start() call and now.
     *
     * @return float seconds.
     */
    public function elapsed()
    {
        return ($this->end === null)
            ? microtime(true) - $this->start
            : $this->end - $this->start
        ;
    }

    /**
     * Create and start a new timer.
     *
     * return @self
     */
    public static function create()
    {
        $timer = new self();
        $timer->start();
        return $timer;
    }
}
