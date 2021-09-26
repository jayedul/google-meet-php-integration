<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

class Meet {

    private $credential_path;
    private $token_path;
    private $service;
    private $client;
    private $app_name = 'Google Meet Demo';
    public $google_callback_url;

    private $required_scopes=array(    
        Google_Service_Calendar::CALENDAR,
        Google_Service_Calendar::CALENDAR_EVENTS,
    );

    private $current_calendar;

	function __construct($owner_id=null, $current_calendar='primary') {
        $this->current_calendar = $current_calendar;

		$this->credential_path = CREDENTIAL_PATH . '/credential.json';
        $this->token_path = CREDENTIAL_PATH . '/token.json';
        $this->google_callback_url = ROOT_URL;

        if($this->isCredentialLoaded()) {
            $this->client = new Google_Client();
            $this->client->setApplicationName($this->app_name);
            $this->client->setAuthConfig($this->credential_path);
            $this->client->setRedirectUri($this->google_callback_url);
            $this->client->addScope($this->required_scopes);
            $this->client->setAccessType("offline");
            $this->client->setApprovalPrompt('force');
            $assigned = !($this->assignTokenToClient()===false);

            if($assigned) {
                // Create service if the token assigned
                $this->service = new Google_Service_Calendar($this->client);
            }
        }
	}

    // Return consent screen url
    public function get_consent_screen_url(){
        return $this->client->createAuthUrl();
    }

    // Upload credential file
    public function saveCredential($file){
        move_uploaded_file($file['tmp_name'], $this->credential_path);
    }

    // Check if credential file uploaded
    public function isCredentialLoaded(){
        return file_exists($this->credential_path);
    }

    // Assign the existing token, or try to refresh if expired.
    public function assignTokenToClient(){

        if (file_exists($this->token_path)) {
            $accessToken = json_decode(file_get_contents($this->token_path), true);
            $this->client->setAccessToken($accessToken);
        }
      
        // Check if token expired
        if ($this->client->isAccessTokenExpired()) {

            $refresh_token = $this->client->getRefreshToken();
            
            if(!$refresh_token){
                return false;
            }

            $new_token = null;
            
            try {
                $new_token = $this->client->fetchAccessTokenWithRefreshToken($refresh_token);
            } catch(\Exception $e) {
                if($e) {
                    return false;
                }
            }

            $this->saveToken(null, $new_token);
        }
    }


    // Save token provided by google
    public function saveToken($code=null, $token=null){
            
        if(!$token){
            $token = $this->client->fetchAccessTokenWithAuthCode($code);
            $this->client->setAccessToken($token);
            $token = $this->client->getAccessToken();
        }
        
        file_put_contents($this->token_path, json_encode($token));
    }


    // Check if the app is permitted by user via consent screen
    public function isAppPermitted(){
        return $this->assignTokenToClient()===false ? false : true;
    }

    private function get_calendar_list($optParams=array()) {
        $list = array();
        $calendarList = $this->service->calendarList->listCalendarList($optParams);
        $pageToken = $calendarList->getNextPageToken();

        foreach ($calendarList->getItems() as $calendarListEntry) {
            $id = $calendarListEntry->getId();
            $list[] = array(
                'id' => $id,
                'summary' => $calendarListEntry->getSummary()
            );
        }

        if ($pageToken) {
            $optParams = array('pageToken' => $pageToken);
            $calendarList = $this->get_calendar_list($optParams);
            is_array($calendarList) ? $list = array_merge($list, $calendarList) : 0;
        }
            
        return $list;
    }

    public function getMeetingList() {

        $optParams = array(
            'maxResults' => 50,
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => date('c'),
        );

        $results = $this->service->events->listEvents($this->current_calendar, $optParams);
        $pageToken = $results->getNextPageToken();
        $list = array();

        foreach ($results->getItems() as $event) {
            $start = $event->start->dateTime;
            if (empty($start)) {
                $start = $event->start->date;
            }
            
            // var_dump($event);

            $id = $event->getId();
            $list[] = array(
                'id' => $id,
                'title' => $event->getSummary(),
                'start' => $start,
                'meeting_link' => $event->hangoutLink
            );
        }

        return $list;
    }

    public function createMeeting(array $payload) {
        
        // Prepare attendees array
        $attendees = isset($payload['attendees']) ? $payload['attendees'] : array();
        $attendees = array_map(function($attendee){
            return is_string($attendee) ? array('email' => $attendee) : $attendee;
        }, $attendees);

        // Mak the create request
        $event = new Google_Service_Calendar_Event(array(
            'summary' => $payload['title'],
            'description' => $payload['description'],
            'start' => array(
              'dateTime' => (new DateTime($payload['start']))->format('c'),
              'timeZone' => (new DateTime($payload['timezone']))->format('c'),
            ),
            'end' => array(
              'dateTime' => (new DateTime($payload['end']))->format('c'),
              'timeZone' => (new DateTime($payload['timezone']))->format('c'),
            ),
            'attendees' => $attendees,
            'conferenceData' => array(
                'createRequest' => array(
                    'requestId' => 'meet_demo_' . microtime(true),
                )
            )
        ));
          
        $event = $this->service->events->insert($this->current_calendar, $event, array('conferenceDataVersion' => 1));
        
        return array(
            'event_id' => $event->getId(),
            'event_link' => $event->htmlLink,
            'conference_id' => $event->conferenceData->conferenceId,
            'conference_link' => $event->hangoutLink,
        );
    }

    public function deleteMeeting($event_id) {
        try {
            $this->service->events->delete($this->current_calendar, $event_id);
        } catch(\Exception $e) {
            
        }
    }

    public function updateMeeting($event_id, array $payload) {
        $event = $this->service->events->get($this->current_calendar, $event_id);

        foreach($payload as $key=>$value) {
            $key=='title' ? $key='summary' : 0;
            $method = 'set' . ucfirst($key);
            
            switch($key) {
                case 'summary' : 
                case 'description' :
                    $event->$method($value);
                    break;

                case 'start' : 
                case 'end' :
                    $date_time = new Google_Service_Calendar_EventDateTime();
                    $date_time->setDateTime((new DateTime($value))->format('c'));
                    $date_time->setTimeZone($payload['timezone']);
                    $event->$method($date_time);
                    break;
            }
        }
        
        $this->service->events->update($this->current_calendar, $event->getId(), $event);
    }
}