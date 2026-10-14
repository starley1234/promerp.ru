<?php defined('SYSPATH') OR die('No direct script access.');

/**
 * Project-level ORM extension.
 *
 * Kohana ORM caches the concrete Database object per model in `_init_cache`.
 * Replacing Database::$instances alone is therefore insufficient when one PHP
 * request switches from the main database to the isolated demo schema: models
 * initialized before the switch (notably User and Role) keep writing through
 * the old connection. Resetting this request-local cache makes newly created
 * models resolve the currently configured `default` connection.
 */
class ORM extends Kohana_ORM
{
    /** @return void Forget model metadata that contains stale DB instances. */
    public static function reset_database_instances()
    {
        static::$_init_cache = array();
        static::$_column_cache = array();
    }
}
