<?php

/**
 * This file is a standalone Google Meet integration demo app. 
 * It doesn't use google php packages for API calls, rather uses raw http requests using CURL.
 */

 /**
  * Replace client id, client secret and redirect URI as per your environment. 
  * Follow the instructions here to get your credentials https://docs.themeum.com/tutor-lms/addons/google-meet-integration/#configuring-app-credentials
  */
class GM {

	public $client_id;
	public $client_secret;
	public $redirect_uri = 'http://localhost/meet/index-raw.php';
	public $scope = 'https://www.googleapis.com/auth/calendar.events';
	public $token_url = 'https://oauth2.googleapis.com/token';

	public $wrapper_style = '
		border:2px dashed #BABABA;
		border-radius:6px;
		text-align:center;
		padding:30px 10px;
		max-width: 600px;
		margin: 20px auto;';

	public $token_dir;
	public $token_path;

	public function __construct( $client_id, $client_secret ) {

		$this->client_id = $client_id;
		$this->client_secret = $client_secret;

		$this->token_dir  = __DIR__ . '/credentials/';
		$this->token_path = $this->token_dir . 'token.json';
	}

	public function getTokenURL() {
		return "https://accounts.google.com/o/oauth2/auth?" . http_build_query([
			'response_type' => 'code',
			'client_id'     => $this->client_id,
			'redirect_uri'  => $this->redirect_uri,
			'scope'         => $this->scope,
			'access_type'   => 'offline',
			'prompt'        => 'consent'
		]);
	}

	public function saveToken( $code ) {

		$data = [
			'code'          => $code,
			'client_id'     => $this->client_id,
			'client_secret' => $this->client_secret,
			'redirect_uri'  => $this->redirect_uri,
			'grant_type'    => 'authorization_code'
		];

		$ch = curl_init($this->token_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
		$response = curl_exec($ch);
		curl_close($ch);

		$token_data = json_decode($response, true);

		if (isset($token_data['access_token'])) {
			
			if ( ! file_exists( $this->token_dir ) ) {
				mkdir( $this->token_dir );
			}

			$token_data['expires_in'] = time() + $token_data['expires_in']; 

			file_put_contents( $this->token_path, json_encode($token_data));
			header( 'Location: ' . $this->redirect_uri );
			exit;
		} else {
			echo "Error getting access token!";
		}
	}

	public function createMeeting() {

		$access_token_data = $this->getToken();
		$access_token = $access_token_data['access_token'];
		$time = date('Y-m-d\TH:i:s-07:00', strtotime('tomorrow') + rand(0, 86399));
		$endTime = date('Y-m-d\TH:i:s-07:00', strtotime($time) + 3600);

		$event = [
			'summary'     => 'Demo Meeting - ' . microtime(true),
			'location'    => 'Online (Google Meet)',
			'description' => 'Discussing project updates',
			'start'       => ['dateTime' => $time],
			'end'         => ['dateTime' => $endTime],
			'conferenceData' => [
				'createRequest' => [
					'requestId' => uniqid(),
					'conferenceSolutionKey' => ['type' => 'hangoutsMeet']
				]
			]
		];

		$ch = curl_init('https://www.googleapis.com/calendar/v3/calendars/primary/events?conferenceDataVersion=1');
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $access_token,
			'Content-Type: application/json'
		]);

		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($event));
		$response = curl_exec($ch);
		curl_close($ch);

		header( 'Location: ' . $this->redirect_uri );
		exit;
	}

	public function deleteMeeting( $event_id ) {

		$access_token_data = $this->getToken();
		$access_token = $access_token_data['access_token'];

		$calendar_id = 'primary'; // Default calendar

		$api_url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events/{$event_id}";

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $access_token,
			'Content-Type: application/json'
		]);

		$response = curl_exec($ch);
		$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		// Check response
		if ($http_code === 204) {
			header('Location: ' . $this->redirect_uri);
			exit;
		} else {
			echo "Failed to delete event. Response: " . $response;
		}
	}

	public function getMeetings() {

		$access_token_data = $this->getToken();
		$access_token = $access_token_data['access_token'];

		$calendar_id = 'primary'; // Use 'primary' for the default calendar
		$api_url = "https://www.googleapis.com/calendar/v3/calendars/{$calendar_id}/events?" . http_build_query([
			'maxResults' => 50,
            'timeMin' => date('c'),
		]);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $api_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Authorization: Bearer ' . $access_token,
			'Content-Type: application/json'
		]);

		$response = curl_exec($ch);
		curl_close($ch);

		$events = json_decode($response, true);
		$events = isset( $events['items'] ) ? $events['items'] : array();
		$meetEvents = array();


		foreach ( $events as $event ) {
			if (
				isset($event['hangoutLink']) || 
				(
					isset($event['conferenceData']['conferenceId']) && 
					isset($event['conferenceData']['conferenceSolution']['name']) && 
					strpos(strtolower($event['conferenceData']['conferenceSolution']['name']), 'meet') !== false
				)
			) {
				$meetEvents[] = $event;
			}
		}
		
		
		return $meetEvents;
	}

	private function refreshToken( $old_token ) {

		// Request payload
		$data = [
			'client_id' => $this->client_id,
			'client_secret' => $this->client_secret,
			'refresh_token' => $old_token['refresh_token'],
			'grant_type' => 'refresh_token'
		];

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $this->token_url);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
		curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

		$response = curl_exec($ch);
		curl_close($ch);

		$new_token = json_decode($response, true);

		if (isset($new_token['access_token'])) {

			// Update token file with new access_token
			$old_token['access_token'] = $new_token['access_token'];
			$old_token['expires_in'] = time() + $new_token['expires_in']; // Expiration time
			file_put_contents( $this->token_path, json_encode($old_token));

			return $old_token;
		} else {
			echo "Failed to refresh token: " . $response;
			exit;
		}
	}

	private function getToken() {
		$token = json_decode(file_get_contents($this->token_path), true);
		if ( time() >= $token['expires_in'] ) {
			$token = $this->refreshToken( $token );
		}
		return $token;
	}
}

// Follow the instructions here to get your credentials https://docs.themeum.com/tutor-lms/addons/google-meet-integration/#configuring-app-credentials
$gm = new GM( 'YOUR_CLIENT_ID', 'YOUR_CLIENT_SECRET' );

if ( $gm->client_id === 'YOUR_CLIENT_ID' || $gm->client_secret === 'YOUR_CLIENT_SECRET' ) {
	echo '<div style="' . $gm->wrapper_style . '">
			You need to provide your client ID and client secret.
		</div>';
	exit;
}

// Accept code from consent response back
if ( ! empty( $_GET['code'] ) ) {
	$gm->saveToken( $_GET['code'] );
}

// Show consent link
if ( ! file_exists( $gm->token_path ) ) {
	echo '<div style="' . $gm->wrapper_style . '">
			<a href="' . $gm->getTokenURL() . '">Give Consent</a>
		</div>';
	exit;
}

// Create meeting
if ( ! empty( $_GET['create'] ) ) {
	$gm->createMeeting();
}

// Delete meeting
if ( ! empty( $_GET['delete'] ) && ! empty( $_GET['id'] ) ) {
	$gm->deleteMeeting( $_GET['id'] );
}

?>
	<div style="<?php echo $gm->wrapper_style; ?>">
		<a href="?create=1">Create New Meeting</a>
	</div>
	
	<table style="width: 100%;">
		<thead>
			<tr>
				<th>Meeting title</th>
				<th>Start</th>
				<th>End</th>
				<th>Link</th>
				<th>Delete</th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($gm->getMeetings() as $event): ?>
				<tr>
					<td><?php echo $event['summary'] ?? 'No Title'; ?></td>
					<td><?php echo $event['start']['dateTime'] ?? 'No Start Time' ?></td>
					<td><?php echo $event['end']['dateTime'] ?? 'No End Time' ?></td>
					<td><?php echo $event['conferenceData']['entryPoints'][0]['uri'] ?? 'No Meet Link' ?></td>
					<td><a href="?delete=1&id=<?php echo $event['id']; ?>">Delete</a></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
<?php
