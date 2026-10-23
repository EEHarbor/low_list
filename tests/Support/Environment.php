<?php

namespace LowListTests {

/** Test template boundary matching Template::fetch_param's defaults and aliases. */
class Template
{
    public $tagparams = array();
    public $tagdata = '';
    public $parsed = array();

    /**
     * Fetch a template parameter using the native EE normalization rules.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    public function fetch_param($name, $default = false)
    {
        if (!isset($this->tagparams[$name])) {
            return $default;
        }

        $value = $this->tagparams[$name];
        if ($value === 'y' || $value === 'on') {
            return 'yes';
        }
        if ($value === 'n' || $value === 'off') {
            return 'no';
        }

        return $value;
    }

    /**
     * Capture the renderer arguments and return its observable output.
     *
     * @param string $tagdata
     * @param array $variables
     * @return string
     */
    public function parse_variables($tagdata, $variables)
    {
        $this->parsed[] = array($tagdata, $variables);
        $output = '';
        foreach ($variables as $row) {
            $rendered = $tagdata;
            foreach ($row as $key => $value) {
                $rendered = str_replace('{' . $key . '}', (string) $value, $rendered);
            }
            $output .= $rendered;
        }

        return $output;
    }
}

/** Query result boundary for the legacy Active Record methods used here. */
class Result
{
    public $num_rows;
    private $rows;
    private $currentRow = 0;
    private $rowData;

    /**
     * Store database rows without coercing null or scalar column values.
     *
     * @param array $rows
     * @return void
     */
    public function __construct(array $rows)
    {
        $this->rows = array_map(static function ($row) {
            return is_object($row) ? $row : (object) $row;
        }, $rows);
        $this->num_rows = count($rows);
    }

    /**
     * Return the number of result rows.
     *
     * @return int
     */
    public function num_rows()
    {
        return $this->num_rows;
    }

    /**
     * Match EE's named-column, numeric-index and current-row fallback behavior.
     *
     * @param int|string $index
     * @param string $type
     * @return mixed
     */
    public function row($index = 0, $type = 'object')
    {
        if (!is_numeric($index)) {
            if (!is_array($this->rowData)) {
                $this->rowData = $this->row_array(0);
            }
            if (array_key_exists($index, $this->rowData)) {
                return $this->rowData[$index];
            }
            $index = 0;
        }

        return $type == 'object' ? $this->rowObject($index) : $this->row_array($index);
    }

    /**
     * Return the selected result as an array, retaining EE's current-row fallback.
     *
     * @param int|string $index
     * @return array
     */
    public function row_array($index = 0)
    {
        return (array) $this->rowObject($index);
    }

    /**
     * Return the native empty array or the selected row object.
     *
     * @param int|string $index
     * @return object|array
     */
    private function rowObject($index)
    {
        if (!$this->rows) {
            return array();
        }
        if ($index != $this->currentRow && isset($this->rows[$index])) {
            $this->currentRow = $index;
        }

        return $this->rows[$this->currentRow];
    }
}

/** Explicit Active Record recorder; unexpected calls fail immediately. */
class Database
{
    public $calls = array();
    public $rows = array();
    public $data_cache = array();
    public $tables = array();
    public $count = 0;
    public $results = array();
    private $recorder;

    /**
     * Share the operation log across cloned query builders like a shared DB connection.
     *
     * @return void
     */
    public function __construct()
    {
        $this->recorder = (object) array('calls' => array());
        $this->calls = &$this->recorder->calls;
    }

    /**
     * Record a supported query-builder operation.
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     * @throws \LogicException
     */
    public function __call($name, $arguments)
    {
        $this->calls[] = array($name, $arguments);
        if ($name === 'get' || $name === 'get_where' || $name === 'query') {
            if (isset($arguments[0]) && array_key_exists($arguments[0], $this->results)) {
                return new Result($this->results[$arguments[0]]);
            }
            return new Result($this->rows);
        }
        if ($name === 'table_exists') {
            return in_array($arguments[0], $this->tables, true);
        }
        if ($name === 'field_exists') {
            return true;
        }
        if ($name === 'count_all_results') {
            return $this->count;
        }
        if (in_array($name, array('select', 'from', 'where', 'insert', 'update', 'delete'), true)) {
            return $this;
        }

        throw new \LogicException('Unexpected database operation: ' . $name);
    }
}

/** Isolated service registry, with no production I/O. */
class Environment
{
    public $TMPL;
    public $db;
    public $config;
    public $load;
    public $logger;
    public $functions;
    public $input;
    public $uri;
    public $cp;
    public $view;
    public $session;
    public $cache;
    public $dbforge;
    public $services = array();

    /**
     * Create fresh collaborators for one test.
     *
     * @return void
     */
    public function __construct()
    {
        $this->TMPL = new Template();
        $this->db = new Database();
        $this->config = new Config();
        $this->load = new Loader();
        $this->logger = new Logger();
    }
}

class Config
{
    public $items = array('site_id' => 1, 'base_url' => 'https://example.test', 'theme_folder_url' => '/themes/');

    /**
     * Retrieve a configuration value, preserving absent-value false.
     *
     * @param string $name
     * @return mixed
     */
    public function item($name)
    {
        return isset($this->items[$name]) ? $this->items[$name] : false;
    }

    /**
     * Return a configuration path with one trailing slash.
     *
     * @param string $name
     * @return string
     */
    public function slash_item($name)
    {
        return rtrim($this->item($name), '/') . '/';
    }
}

class Loader
{
    public $calls = array();

    /**
     * Record the helper request; test helpers are already loaded.
     *
     * @param string $name
     * @return void
     */
    public function helper($name)
    {
        $this->calls[] = array('helper', $name);
    }

    /**
     * Record the library request.
     *
     * @param string $name
     * @return void
     */
    public function library($name)
    {
        $this->calls[] = array('library', $name);
    }
}

class Logger
{
    public $messages = array();

    /**
     * Record developer diagnostics.
     *
     * @param string $message
     * @return void
     */
    public function developer($message)
    {
        $this->messages[] = $message;
    }
}

}

namespace {
    /**
     * Resolve the explicitly supplied EE collaborator.
     *
     * @param string|null $service
     * @return mixed
     * @throws \LogicException
     */
    function ee($service = null)
    {
        if ($service === null) {
            return $GLOBALS['low_list_ee'];
        }
        if ($service === 'db') {
            return $GLOBALS['low_list_ee']->db;
        }
        if (!isset($GLOBALS['low_list_ee']->services[$service])) {
            throw new \LogicException('Unexpected EE service: ' . $service);
        }

        if ($service === 'CP/URL' && func_num_args() > 1) {
            $arguments = func_get_args();

            return $GLOBALS['low_list_ee']->services[$service]->make($arguments[1]);
        }

        return $GLOBALS['low_list_ee']->services[$service];
    }

    /**
     * Read a cache file using the native helper's missing-file contract.
     *
     * @param string $path
     * @return string|false
     */
    function read_file($path)
    {
        return is_file($path) ? file_get_contents($path) : false;
    }

    /**
     * Write a cache file using the native helper's boolean contract.
     *
     * @param string $path
     * @param string $data
     * @return bool
     */
    function write_file($path, $data)
    {
        return file_put_contents($path, $data) !== false;
    }

    /**
     * Record an opening form helper call.
     *
     * @param string $action
     * @param array $attributes
     * @return string
     */
    function form_open($action, $attributes)
    {
        $GLOBALS['low_list_forms'][] = array('open', $action, $attributes);

        return '<form>';
    }

    /**
     * Record an input helper call.
     *
     * @param string $name
     * @param mixed $value
     * @return string
     */
    function form_input($name, $value)
    {
        $GLOBALS['low_list_forms'][] = array('input', $name, $value);

        return '<input>';
    }

    /**
     * Record a checkbox helper call.
     *
     * @param string $name
     * @param string $value
     * @param mixed $checked
     * @return string
     */
    function form_checkbox($name, $value, $checked)
    {
        $GLOBALS['low_list_forms'][] = array('checkbox', $name, $value, $checked);

        return '<input type="checkbox">';
    }

    /**
     * Return the closing form tag.
     *
     * @return string
     */
    function form_close()
    {
        return '</form>';
    }
}
