<?php

/** Minimal EE fieldtype collaborator preserving inherited settings and content ID. */
abstract class EE_Fieldtype
{
    public $settings = array();
    protected $entryId;

    /**
     * Return the current entry identifier.
     *
     * @return int|null
     */
    public function content_id()
    {
        return $this->entryId;
    }
}
