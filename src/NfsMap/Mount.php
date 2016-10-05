<?php
declare(strict_types=1);

namespace WebdevToolbox\NfsMap;

// A local or remote filesystem mount
class Mount
{
    use \lpeltier\Struct;

    /// @var string
    public $host;

    /// @var string
    public $source;

    /// @var string
    public $target;

    /// @var int
    public $size;

    /// @var int
    public $used;
}
