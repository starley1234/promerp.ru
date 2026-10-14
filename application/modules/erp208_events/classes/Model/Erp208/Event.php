<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.2
 * @author AI
 */
class Model_Erp208_Event extends ORM {

    protected $_table_name = 'erp_208_events';
    protected $_primary_key = 'id';

    protected $_table_columns = array(
        'id'           => array('type' => 'int'),
        'event_name'   => array('type' => 'string'),
        'initiator_id' => array('type' => 'int'),
        'payload'      => array('type' => 'string'),
        'created_at'   => array('type' => 'timestamp'),
    );

    protected $_belongs_to = array(
        'initiator' => array(
            'model'       => 'User',
            'foreign_key' => 'initiator_id',
        ),
    );

    protected $_has_many = array();

    /**
     * Validation rules
     *
     * @return array
     */
    public function rules()
    {
        return array(
            'event_name' => array(
                array('not_empty'),
                array('max_length', array(':value', 255)),
            ),
            'initiator_id' => array(
                array('digit'),
            ),
        );
    }

    /**
     * Saves the current object.
     * Overrides the default save method to handle JSON encoding for payload.
     *
     * @param Validation $validation
     * @return ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if (is_array($this->payload) OR is_object($this->payload))
        {
            $this->payload = json_encode($this->payload, JSON_UNESCAPED_UNICODE);
        }
        return parent::save($validation);
    }

    /**
     * Returns the payload as a decoded array.
     *
     * @return array|null
     */
    public function get_payload_array()
    {
        if ($this->payload)
        {
            $decoded = json_decode($this->payload, TRUE);
            if (json_last_error() === JSON_ERROR_NONE)
            {
                return $decoded;
            }
        }
        return NULL;
    }

    /**
     * Triggers the event again using the Event Bus.
     * Useful for "Repeat Event" functionality.
     *
     * @return mixed Result of the Event Bus trigger
     */
    public function trigger()
    {
        return Erp208_EventBus::instance()->trigger(
            $this->event_name,
            $this->get_payload_array()
        );
    }
}