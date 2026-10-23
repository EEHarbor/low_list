<?php

namespace LowListTests;

class FieldtypeModels
{
    public $entries = array();
    public $calls = array();

    /**
     * Start a bounded ChannelEntry lookup using hydrated rows supplied by the test.
     *
     * @param string $name
     * @return FieldtypeQuery
     * @throws \LogicException
     */
    public function get($name)
    {
        if ($name !== 'ChannelEntry') {
            throw new \LogicException('Unexpected fieldtype model: ' . $name);
        }
        $this->calls[] = array('get', $name);

        return new FieldtypeQuery($this);
    }
}

class FieldtypeQuery
{
    private $models;
    private $entryId;

    /**
     * Retain the model service shared by one independent query.
     *
     * @param FieldtypeModels $models
     * @return void
     */
    public function __construct(FieldtypeModels $models)
    {
        $this->models = $models;
    }

    /**
     * Select the requested entry using the source's native two-argument filter.
     *
     * @param string $field
     * @param mixed $value
     * @return self
     * @throws \LogicException
     */
    public function filter($field, $value)
    {
        if ($field !== 'entry_id') {
            throw new \LogicException('Unexpected fieldtype filter: ' . $field);
        }
        $this->entryId = $value;
        $this->models->calls[] = array('filter', $field, $value);

        return $this;
    }

    /**
     * Return the selected hydrated entry or native first()'s missing-result null.
     *
     * @return object|null
     */
    public function first()
    {
        $this->models->calls[] = array('first');

        return isset($this->models->entries[$this->entryId]) ? $this->models->entries[$this->entryId] : null;
    }
}

class FieldtypeTable
{
    public $calls = array();

    /**
     * Record the exact native table call without rendering or normalizing cells.
     *
     * @return void
     */
    public function add_row()
    {
        $this->calls[] = func_get_args();
    }
}

class FieldtypeXss
{
    public $calls = array();

    /**
     * Record strict opt-in delegation to the external XSS service.
     *
     * @param mixed $value
     * @return array
     */
    public function clean($value)
    {
        $this->calls[] = $value;

        return array('cleaned' => $value);
    }
}

class CleanRequestValues
{
    public $calls = array();

    /**
     * Preserve only the already-clean license strings verified against native XSS.
     *
     * @param string|array $value
     * @return string|array
     * @throws \LogicException
     */
    public function clean($value)
    {
        $this->calls[] = $value;
        $values = is_array($value) ? $value : array($value);
        foreach ($values as $part) {
            if (!is_string($part) || !preg_match('/^[a-z0-9_ &-]*$/iD', $part)) {
                throw new \LogicException('The bounded XSS collaborator requires a verified clean license value.');
            }
        }

        return $value;
    }
}
