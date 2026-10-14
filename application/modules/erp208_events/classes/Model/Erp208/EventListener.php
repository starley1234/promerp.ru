<?php defined('SYSPATH') or die('No direct script access.');

/**
 * @package Erp208
 * @version 1.0.0
 * @author AI
 */
class Model_Erp208_EventListener extends ORM {

    /**
     * @var string The name of the database table.
     */
    protected $_table_name = 'erp_208_event_subscribers';

    /**
     * @var array Has many relationships.
     *            This model doesn't directly "have many" other ORM models in a typical foreign key sense,
     *            as its primary role is to represent a subscription.
     *            However, conceptually, an event could have many listeners, but this is managed by the EventBus service.
     */
    protected $_has_many = array(
        // No direct ORM relationships for this specific model, as it represents a subscription.
        // The actual "listening" is handled by the Service_Erp208_EventBus.
    );

    /**
     * @var array Belongs to relationships.
     *            A subscriber "belongs to" an event conceptually, but not directly via ORM foreign key to Model_Erp208_Event,
     *            as `event_name` is a string, not an ID.
     *            If `erp_208_events` had a unique `event_name` and `erp_208_event_subscribers` referenced its ID,
     *            this would be a direct relationship.
     */
    protected $_belongs_to = array(
        // 'event' => array(
        //     'model'       => 'Erp208_Event',
        //     'foreign_key' => 'event_name', // This would only work if event_name was a foreign key to event ID
        // ),
    );

    /**
     * Registers a listener for a specific event.
     * This method is more conceptual for the ORM model. The actual registration
     * should happen in Service_Erp208_EventBus.
     * This ORM model primarily manages the *persisted configuration* of a listener.
     *
     * @param string $event_name The name of the event to listen for.
     * @param string $module_id The ID of the module subscribing.
     * @param string $callback_method The method or function to call when the event fires.
     * @param bool $is_active Whether the subscription is active.
     * @return Model_Erp208_EventListener The created or updated subscriber model.
     * @throws Kohana_Exception If the subscriber cannot be saved.
     */
    public function register_listener($event_name, $module_id, $callback_method, $is_active = TRUE)
    {
        // Check if a similar subscription already exists
        $subscriber = ORM::factory('Erp208_EventListener')
            ->where('event_name', '=', $event_name)
            ->and_where('module_id', '=', $module_id)
            ->and_where('callback_method', '=', $callback_method)
            ->find();

        if ( ! $subscriber->loaded())
        {
            $subscriber->event_name      = $event_name;
            $subscriber->module_id       = $module_id;
            $subscriber->callback_method = $callback_method;
        }
        
        $subscriber->is_active = $is_active;
        $subscriber->save();

        return $subscriber;
    }

    /**
     * Unregisters a listener.
     *
     * @param string $event_name The name of the event.
     * @param string $module_id The ID of the module.
     * @param string $callback_method The callback method.
     * @return bool True if unregistered, false otherwise.
     */
    public function unregister_listener($event_name, $module_id, $callback_method)
    {
        $subscriber = ORM::factory('Erp208_EventListener')
            ->where('event_name', '=', $event_name)
            ->and_where('module_id', '=', $module_id)
            ->and_where('callback_method', '=', $callback_method)
            ->find();

        if ($subscriber->loaded())
        {
            $subscriber->delete();
            return TRUE;
        }
        return FALSE;
    }

    /**
     * Retrieves all active listeners for a given event name.
     * This method would typically be used by the EventBus to find which callbacks to execute.
     *
     * @param string $event_name The name of the event.
     * @return Database_Result An array of Model_Erp208_EventListener objects.
     */
    public function get_active_listeners_for_event($event_name)
    {
        return $this->where('event_name', '=', $event_name)
                    ->and_where('is_active', '=', TRUE)
                    ->find_all();
    }

    /**
     * Retrieves all listeners for a given module.
     *
     * @param string $module_id The ID of the module.
     * @return Database_Result An array of Model_Erp208_EventListener objects.
     */
    public function get_listeners_by_module($module_id)
    {
        return $this->where('module_id', '=', $module_id)
                    ->find_all();
    }
}