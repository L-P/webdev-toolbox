<?php

namespace WebdevToolbox\DockerShell;

/**
 * Configuration handler for the docker-shell command.
 *
 * Sample configuration file:
 *
 *  {
 *      "database": {
 *          "user": "postgres"
 *      },
 *      "(memorycache|mailer)": {
 *          "noLogin": true
 *      },
 *      "webserver": {
 *          "user": "root"
 *      }
 *  }
 *
 * The key is a PCRE pattern that will me matched against the full container
 * name. See ConfEntry for details.
 */
class Conf
{
    /// @var ConfEntry[] {<pattern>: ConfEntry,}
    private $entries = [];

    /// @var ConfEntry
    private $default = null;

    public function __construct()
    {
        $this->default = new ConfEntry([
            'noLogin' => true
        ]);
    }

    /**
     * Get the ConfEntry that matched the container name.
     *
     * @param string $name
     * @return ConfEntry
     */
    public function get($name)
    {
        foreach ($this->entries as $entry) {
            $pattern = sprintf('`%s`', str_replace('`', '\\`', $entry->pattern));

            if (preg_match($pattern, $name)) {
                return $entry;
            }
        }

        return $this->default;
    }

    public function load($path)
    {
        assert('count($this->entries) === 0');

        if (!file_exists($path)) {
            throw new FileNotFoundException($path);
        }

        $raw = json_decode(file_get_contents($path), true);
        if ($raw === false) {
            throw new \Exception("Invalid JSON in `$path`: " . json_last_error_msg());
        }

        foreach ($raw as $pattern => $data) {
            $this->entries[] = new ConfEntry($data + compact('pattern'));
        }
    }
}
