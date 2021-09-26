<?php
require_once 'Meet.php';

class Init{
	public static $google_callback_string = 'google-meet-callback';
	private $meet;

	function __construct() {
		$this->meet = new Meet;
	}

	public function start() {
		// Save token if the request comes from google
		$this->saveToken();

		// Save credential if upload file exists
		$this->saveCredential();

		// CRUD
		$this->CRUDOperation();

		return $this->meet;
	}

	public function saveToken() {
		if(isset($_GET['code'])) {
			$this->meet->saveToken($_GET['code']);
			header('Location: '.ROOT_URL);
			exit;
		}
	}

	public function saveCredential(){
		if(isset($_FILES['credential']) && $_FILES['credential']['error']==0) {

			// Save credential file if exist and no error
			$this->meet->saveCredential($_FILES['credential']);

			header('Location: '.ROOT_URL);
			exit;
		}
	}

	public function CRUDOperation() {
		if(isset($_GET['action'])) {
			$now = time();
			$start_time = rand($now+86400, $now+(86400*6));
			$end_time = $start_time+3600;

			switch($_GET['action']) {
				case 'create' :
					$this->meet->createMeeting(array(
						'title' 		=> 'Title New ' . microtime(true),
						'description' 	=> 'Description New ' . microtime(true),
						'start' 		=> date("Y-m-d H:i:s", $start_time),
						'end' 			=> date("Y-m-d H:i:s", $end_time),
						'timezone' 		=> 'America/Los_Angeles'
					));
					break;

				case 'delete' :
					$this->meet->deleteMeeting($_GET['id']);
					break;

				case 'update' :
					$this->meet->updateMeeting($_GET['id'], array(
						'title' 		=> 'Title Update ' . microtime(true),
						'description' 	=> 'Description Update ' . microtime(true),
						'start' 		=> date("Y-m-d H:i:s", $start_time),
						'end' 			=> date("Y-m-d H:i:s", $end_time),
						'timezone' 		=> 'America/Los_Angeles'
					));
					break;
			}
			
		}
	}
}