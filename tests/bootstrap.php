<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/api/config/env.php';
require_once dirname(__DIR__) . '/api/config/database.php';
require_once dirname(__DIR__) . '/api/helpers/Response.php';
require_once dirname(__DIR__) . '/api/helpers/Validator.php';

// Base de test séparée
$_ENV['DB_NAME'] = 'restaurant_test';
db_reset();
