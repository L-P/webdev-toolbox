<?php
declare(strict_types=1);

namespace WebdevToolbox\NfsMap;

class Host
{
    use \lpeltier\Struct;

    /// @var string
    public $name;

    /// @var string[]
    public $ips = [];

    /// @var Mount[]
    public $mounts = [];
}
