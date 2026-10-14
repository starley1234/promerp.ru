<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 * @author AI
 */
class Model_Erp208_EventPublisher extends ORM {

    protected $_table_name = 'erp_208_events'; // Используем таблицу событий для сохранения
    protected $_primary_key = 'id';

    /**
     * Define relationships to other models.
     *
     * In this specific model (EventPublisher), we are primarily concerned with
     * creating and saving events. Relationships to other models like subscribers
     * are typically handled by the EventBus or EventListener, not directly by
     * the publisher itself, which focuses on the event record.
     */
    protected $_has_many = array();
    protected $_belongs_to = array();

    /**
     * Publishes an event by saving it to the database and then "firing" it
     * for any registered listeners.
     *
     * @param string $event_name The name of the event (e.g., 'erp502.task_done').
     * @param array  $payload    An associative array of data associated with the event.
     * @return Model_Erp208_EventPublisher The saved event object.
     * @throws Kohana_Exception If the event cannot be saved.
     */
    public function publish(string $event_name, array $payload = array()): Model_Erp208_EventPublisher
    {
        // 1. Create a new event record
        $this->clear(); // Clear any previous data in the ORM object
        $this->event_name = $event_name;
        $this->payload = json_encode($payload); // Store payload as JSON string
        $this->created_at = date('Y-m-d H:i:s'); // Set creation timestamp

        try
        {
            // 2. Save the event to the database
            $this->save();

            // 3. "Fire" the event for listeners (using a service like EventBus)
            // This assumes a Service_Erp208_EventBus exists and handles the actual dispatching.
            // The publisher's role is to ensure the event is recorded and then passed to the bus.
            if (class_exists('Service_Erp208_EventBus'))
            {
                Service_Erp208_EventBus::instance()->fire($event_name, $payload, $this->id);
            }
            else
            {
                // Log a warning if EventBus is not available, as listeners won't be notified.
                Kohana::$log->add(Log::WARNING, 'Service_Erp208_EventBus not found. Event ":event_name" (ID: :event_id) published but not dispatched to listeners.', array(
                    ':event_name' => $event_name,
                    ':event_id'   => $this->id,
                ));
            }

            return $this;
        }
        catch (ORM_Validation_Exception $e)
        {
            // Handle validation errors during save
            $errors = $e->errors('models');
            Kohana::$log->add(Log::ERROR, 'Failed to publish event ":event_name" due to validation errors: :errors', array(
                ':event_name' => $event_name,
                ':errors'     => json_encode($errors),
            ));
            throw new Kohana_Exception('Failed to publish event ":event_name": :errors', array(
                ':event_name' => $event_name,
                ':errors'     => implode(', ', $errors),
            ));
        }
        catch (Exception $e)
        {
            // Handle other database or unexpected errors
            Kohana::$log->add(Log::ERROR, 'Failed to publish event ":event_name": :error', array(
                ':event_name' => $event_name,
                ':error'      => $e->getMessage(),
            ));
            throw new Kohana_Exception('Failed to publish event ":event_name": :error', array(
                ':event_name' => $event_name,
                ':error'      => $e->getMessage(),
            ), 0, $e);
        }
    }

    /**
     * Overrides the default save method to ensure 'created_at' is set on creation
     * and payload is always a JSON string.
     *
     * @param Validation $validation
     * @return ORM
     */
    public function save(?Validation $validation = NULL)
    {
        if (!$this->loaded())
        {
            $this->created_at = date('Y-m-d H:i:s');
        }

        if (is_array($this->payload))
        {
            $this->payload = json_encode($this->payload);
        }
        elseif ($this->payload === NULL)
        {
            $this->payload = '{}'; // Ensure payload is at least an empty JSON object if null
        }

        return parent::save($validation);
    }

    /**
     * Defines validation rules for the event.
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
            'payload' => array(
                array('json'), // Ensure payload is valid JSON
            ),
            'created_at' => array(
                array('not_empty'),
                array('date'),
            ),
        );
    }

    /**
     * Custom validation rule to check if a string is valid JSON.
     *
     * @param string $value
     * @return bool
     */
    public function _json($value)
    {
        json_decode($value);
        return (json_last_error() == JSON_ERROR_NONE);
    }
}