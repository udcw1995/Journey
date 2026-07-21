<?php

/**
 * Shared test bootstrap.
 *
 * Forces all automated tests to run against a dedicated test database
 * instead of the developer's real database, so tests never touch or
 * depend on production/development data.
 *
 * Database::connect() only loads a .env value when the variable isn't
 * already set in the environment, so setting DB_NAME here before any
 * connection is made overrides whatever is in .env.
 */

putenv('DB_NAME=' . (getenv('TEST_DB_NAME') ?: 'backend_journey_test'));

require_once __DIR__ . '/../config/Database.php';
