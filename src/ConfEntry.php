<?php

namespace WebdevToolbox;

class ConfEntry
{
    /// @var string will be matched against the full container name.
    public $pattern = null;

    /// @var bool use /bin/login if true, /bin/sh otherwise. Mutually exclusive with $user.
    public $noLogin = false;

    /// @var string|null user to login as, default to the current system user.
    public $user = null;

    public function __construct(array $params = [])
    {
        foreach ($params as $k => $v) {
            if (property_exists($this, $k)) {
                $this->$k = $v;
            } else {
                $this->__set($k, $v);
            }
        }
    }

    public function __set($key, $value)
    {
        throw new \InvalidArgumentException("Invalid key `$key`.");
    }

    public function __get($key)
    {
        throw new \InvalidArgumentException("Invalid key `$key`.");
    }
}
