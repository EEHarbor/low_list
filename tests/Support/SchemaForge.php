<?php

namespace LowListTests;

/** Bounded recorder for successful logical-table schema operations. */
class SchemaForge
{
    public $calls = array();
    public $db;
    private $fields = array();
    private $primaryKeys = array();

    /**
     * Retain the same database backend exposed to the native schema builder.
     *
     * @param Database $database
     * @return void
     */
    public function __construct(Database $database)
    {
        $this->db = $database;
    }

    /**
     * Accumulate field definitions until the next table is created.
     *
     * @param array $fields
     * @return void
     */
    public function add_field(array $fields)
    {
        $this->calls[] = array('add_field', $fields);
        $this->fields = array_merge($this->fields, $fields);
    }

    /**
     * Record primary and ordinary index requests using the native public shape.
     *
     * @param string|array $key
     * @param bool $primary
     * @return void
     */
    public function add_key($key, $primary = false)
    {
        $this->calls[] = array('add_key', $key, $primary);
        if ($primary) {
            $this->primaryKeys = array_merge($this->primaryKeys, (array) $key);
        }
    }

    /**
     * Expose a successfully created table, its fields and its primary-key metadata.
     *
     * @param string $table
     * @return bool
     */
    public function create_table($table)
    {
        $this->calls[] = array('create_table', $table);
        $prefix = $this->db->dbprefix;
        if (!isset($this->db->data_cache['table_names'])) {
            $this->db->data_cache['table_names'] = array_map(static function ($name) use ($prefix) {
                return $prefix . $name;
            }, $this->db->tables);
        }
        $this->db->data_cache['table_names'][] = $prefix . $table;
        $this->db->tables[] = $table;
        $this->db->fields[$table] = array_keys($this->fields);
        $rows = array();
        foreach ($this->primaryKeys as $key) {
            $rows[] = array('Column_name' => $key, 'Non_unique' => '0');
        }
        $this->db->results["SHOW KEYS FROM exp_" . $table . " WHERE Key_name = 'PRIMARY'"] = $rows;
        $this->fields = array();
        $this->primaryKeys = array();

        return true;
    }

    /**
     * Expose successful column additions and invalidate cached field metadata.
     *
     * @param string $table
     * @param array $fields
     * @return bool
     */
    public function add_column($table, array $fields)
    {
        $this->calls[] = array('add_column', $table, $fields);
        $existing = isset($this->db->fields[$table]) ? $this->db->fields[$table] : array();
        $this->db->fields[$table] = array_values(array_unique(array_merge($existing, array_keys($fields))));
        unset($this->db->data_cache['field_names'][$table]);

        return true;
    }
}
