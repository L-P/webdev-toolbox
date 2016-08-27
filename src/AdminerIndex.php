<?php // @codingStandardsIgnoreStart

// From https://github.com/vrana/adminer/commit/7a33661b721714a8b266bf57c0065ae653bb8097#commitcomment-17728245

function adminer_object()
{
    class AdminerIndex extends Adminer
    {
        public function login($login, $password)
        {
            return true;
        }
    }

    return new AdminerIndex;
}

require_once __DIR__ . '/adminer.php';
