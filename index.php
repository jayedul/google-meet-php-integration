<?php
/**
 * This file uses google php packages for API calls
 * 
 * Follow the instructions here to get your credentials https://docs.themeum.com/tutor-lms/addons/google-meet-integration/#configuring-app-credentials
 */

define('ROOT_PATH', __DIR__);
define('CREDENTIAL_PATH', __DIR__ . '/credentials');
define('ROOT_URL', 'http://localhost/meet');

require_once 'classes/Init.php';
$meet = (new Init)->start();

require_once 'views/view.php' ;