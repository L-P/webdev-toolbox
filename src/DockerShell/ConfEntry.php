<?php

namespace WebdevToolbox\DockerShell;

use lpeltier\Struct;

class ConfEntry
{
    use Struct;

    /// @var string will be matched against the full container name.
    public $pattern = null;

    /// @var bool use /bin/login if true, $shell otherwise. Mutually exclusive with $user.
    public $noLogin = false;

    /// @var string|null user to login as, default to the current system user.
    public $user = null;

    /// @var string|null override shell to use when entering the container as root.
    public $shell = null;
}
