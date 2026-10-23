<?php

/** Bounded native fieldtype initialization contract; rendering stays in the add-on. */
abstract class EE_Fieldtype
{
    public $field_id;
    public $field_name;
    public $row;
    public $var_id;
    public $settings = array();
    public $field_fmt;
    protected $EE;
    protected $id;
    protected $name;
    protected $content_id = null;
    protected $content_type = 'channel';

    /**
     * Reinitialize native identifiers while clearing omitted singleton content IDs.
     *
     * @param array $config
     * @return void
     */
    public function _init($config = array())
    {
        $id = isset($config['id']) ? $config['id'] : (isset($config['field_id']) ? $config['field_id'] : null);
        $name = isset($config['name']) ? $config['name'] : (isset($config['field_name']) ? $config['field_name'] : null);
        if (isset($id) && $this->id != $id) {
            $config['id'] = $id;
        }
        if (isset($name) && $this->name != $name) {
            $config['name'] = $name;
        }
        foreach ($config as $key => $value) {
            $this->$key = $value;
        }
        $this->field_id = $this->id;
        $this->field_name = $this->name;
        if (!isset($config['content_id'])) {
            $this->content_id = null;
        }
    }

    /**
     * Return the current field identifier.
     *
     * @return mixed
     */
    public function id()
    {
        return $this->id;
    }

    /**
     * Return the current field name.
     *
     * @return mixed
     */
    public function name()
    {
        return $this->name;
    }

    /**
     * Return the current content identifier, including supported falsey values.
     *
     * @return mixed
     */
    public function content_id()
    {
        return $this->content_id;
    }

    /**
     * Return the initialized parent content type.
     *
     * @return string
     */
    public function content_type()
    {
        return $this->content_type;
    }

    /**
     * Render a field through the concrete add-on implementation.
     *
     * @param mixed $data
     * @return mixed
     */
    abstract public function display_field($data);
}
