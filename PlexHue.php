<?php
/**
 * PlexHue uses Plex Media Server Webhooks to trigger Philips Hue groups on certain events.
 *
 * @author		@SimonDettling
 * @copyright	2019 Simon Dettling
 * @version		2.2.0 - 2019-05-26
 *
 *
 * VERSION HISTORY
 * 
 *  2.2.0 - 2019-06-25
 * - Option $hueLightPlayResumeStates added
 * - Option $plexLibrarySectionTypeExclude added
 * 
 * 2.1.0 - 2019-05-26
 * - Option HUE_RESUME_MAX_BRI added
 * 
 * 2.0.1 - 2019-05-23
 * - Minor bug fixes
 *
 * 2.0.0 - 2019-04-25
 * - Light will be reverting to the initial state when a stop/pause event has been receieved.
 * - Lock file functionallity which will prevent PlexHue to run simultaneously at the same time.
 *
 * 1.1.0 - 2019-04-23
 * - Stores the last Webhook event into new settings file for logical comparison on run. (e.g. two stop events successively or a stop event after a pause event don't trigger any further actions)
 * - Renamed LOG_NAME Constant to LOG_FILE_NAME
 *
 * 1.0.0 - 2019-04-10
 * - Initial Release
 */

// Reset opcache if installed
if (function_exists('opcache_reset')) {
	opcache_reset();
}

/*
 * OPTIONS
 */
define('PLEXHUE_ENABLED', true);
define('PLEXHUE_VERSION', '2.2.0');

define('LOG_ENABLED', true);
define('LOG_FILE_NAME', 'PlexHue.log');
define('LOG_MAX_SIZE_KB', 512);
define('SETTING_FILE_NAME', 'PlexHue.json');

define('LOCK_FILE_ENABLED', true);
define('LOCK_FILE_TIMER_SECONDS', 10);

define('PLEX_PLAYER', 'Living');

define('HUE_API_URL', 'http://192.168.1.2/api/API_KEY/');
define('HUE_TRANSITION_TIME', 60);
define('HUE_THROTTLING_MS', 200); // Delays the execution of the next light for the specified amount of milliseconds. See: https://developers.meethue.com/develop/application-design-guidance/hue-system-performance/
define('HUE_RESUME_MAX_BRI', 150); // if the brightness of the saved lights state exceeds this value, it will be lowered to the defined value. To disabled this, specify 0.

$hueGroups = array(
	'living' => 6,
	'dining' => 2,
	'kitchen' => 1,
	'bedroom' => 7,
	'office' => 15,
	'bathroom' => 4
);

$hueGroupPlayResumeStates = array(
	'living' => 'movie',
	'dining' => 'movie',
	'kitchen' => 'turnOff',
	'bedroom' => 'turnOff',
	'office' => 'turnOff',
	'bathroom' => 'turnOff'
);

$hueLightPlayResumeStates = array(
	32 => 'turnOff',
	33 => 'movie'
);

$hueStatesSettings = array(
	'movie' => array(
		"on" => true,
		"bri" => 45,
		"hue" => 0,
		"xy" => array(0.6445, 0.3059),
		"transitiontime" => HUE_TRANSITION_TIME,
	),
	'turnOff' => array(
		"on" => false,
		"transitiontime" => HUE_TRANSITION_TIME,
	)
);

$plexLibrarySectionTypeExclude = array('artist');

// Delete log file if neccessary
if (LOG_ENABLED && file_exists(LOG_FILE_NAME) && filesize(LOG_FILE_NAME) >= LOG_MAX_SIZE_KB*1024) {
	unlink(LOG_FILE_NAME);
	logWrite('Log file has been truncated due to reaching max size');
}

// Insert new line into log
logWrite('');

// Check if PlexHue is enabled
if (!PLEXHUE_ENABLED) {
	exitPlexHue('PlexHue is disabled. Aborting execution!', false);
}

logWrite('############## Starting PlexHue '.PLEXHUE_VERSION.' ##############');

// Check Lock file
if (LOCK_FILE_ENABLED && file_exists('PlexHue.LOCK')) {
	// check if file is older than specified seconds
	if (time() - filemtime('PlexHue.LOCK') <= LOCK_FILE_TIMER_SECONDS) {
		exitPlexHue('Lock file detected. Aborting execution!', false);
	}
	else {
		logWrite('Lock file is older than '.LOCK_FILE_TIMER_SECONDS.' seconds. Continue...');
	}
}
elseif (LOCK_FILE_ENABLED) {
	logWrite('Lock file created');
	file_put_contents('PlexHue.LOCK', '');
}

// Check if setting file exists
if (!(file_exists(SETTING_FILE_NAME))) {
	exitPlexHue("Setting file '".SETTING_FILE_NAME."' doesn't exists. Aborting execution!");
}

// Get and validate Settings
$settings = json_decode(file_get_contents(SETTING_FILE_NAME));

// Check for POST Request
if (!isset($_POST['payload'])) {
	exitPlexHue('Invalid POST request received. Aborting execution!');
}

// Read JSON Payload (Webhook) from POST
$jsonWebhook = json_decode($_POST['payload']);

if (!isset($jsonWebhook->Player->title) || !isset($jsonWebhook->event)) {
	exitPlexHue('Invalid JSON received from POST request. Aborting execution!');
}

logWrite("Received a Webhook from Player '".$jsonWebhook->Player->title."' with event '".$jsonWebhook->event."'");

// Check for valid events
if ($jsonWebhook->event != 'media.play' && $jsonWebhook->event != 'media.resume' && $jsonWebhook->event != 'media.pause' && $jsonWebhook->event != 'media.stop') {
	exitPlexHue("Webhook event '".$jsonWebhook->event."' is not assigned to any action in PlexHue. Aborting execution!");
}

// Check if the request comes from the defined player. If not, terminate the script.
if ($jsonWebhook->Player->title != PLEX_PLAYER) {
	exitPlexHue("The received Player '".$jsonWebhook->Player->title."' doesn't match with the configured player '".PLEX_PLAYER."'. Aborting execution!");
}

// Check if the same events were triggered successively
if ($settings->LastEvent == $jsonWebhook->event) {
	exitPlexHue("Webhook event '".$jsonWebhook->event."' was found in settings file as last event. This is not valid, aborting execution!");
}
// Check for stop event after pause event
ElseIf ($jsonWebhook->event == 'media.stop' && $settings->LastEvent == 'media.pause') {
	exitPlexHue("Webhook event 'media.stop' received and last event in settings file is 'media.pause'. This is not valid, aborting execution!");
}
// Check for pause event after stop event
ElseIf ($jsonWebhook->event == 'media.pause' && $settings->LastEvent == 'media.stop') {
	exitPlexHue("Webhook event 'media.pause' received and last event in settings file is 'media.stop'. This is not valid, aborting execution!");
}
// Check for play event after resume event
ElseIf ($jsonWebhook->event == 'media.play' && $settings->LastEvent == 'media.resume') {
	exitPlexHue("Webhook event 'media.play' received and last event in settings file is 'media.resume'. This is not valid, aborting execution!");
}
// Check for resume event after play event
ElseIf ($jsonWebhook->event == 'media.resume' && $settings->LastEvent == 'media.play') {
	exitPlexHue("Webhook event 'media.resume' received and last event in settings file is 'media.play'. This is not valid, aborting execution!");
}

// Check for correct library section type
If (in_array($jsonWebhook->Metadata->librarySectionType, $plexLibrarySectionTypeExclude)) {
	exitPlexHue("Webhook library section type '".$jsonWebhook->Metadata->librarySectionType."' is excluded from PlexHue. Aborting execution!");
}

// Save current light settings on play/resume and set the groups to the specified states
if ($jsonWebhook->event == 'media.resume' || $jsonWebhook->event == 'media.play') {
	$lightStates = array();
	foreach ($hueGroups as $groupName => $groupID) {
		// Get all lights which are assigned to this group
		logWrite("Processing group '".$groupName."'");

		// Send cURL request
		$groupSettings = json_decode(sendWebRequest(
			HUE_API_URL.'groups/'.$groupID,
			'GET',
			true
		));

		logWrite("Found ".count($groupSettings->lights)." light(s) in group '".$groupName."'");

		// Get state for each light in each group
		foreach ($groupSettings->lights as $lightID) {

			// Send cURL request
			$url = HUE_API_URL.'lights/'.$lightID;
			logWrite("Saving current state settings for light '".$lightID."'");
			$lightSettings = json_decode(sendWebRequest(
				$url,
				'GET',
				true
			));

			if ($lightSettings->state->on == true) {
				// check if brightness exceeds HUE_RESUME_MAX_BRI
				if (HUE_RESUME_MAX_BRI && $lightSettings->state->bri > HUE_RESUME_MAX_BRI) {
					logWrite("Brightness state setting '".$lightSettings->state->bri."' exceeds defined option. Adjusting to '".HUE_RESUME_MAX_BRI."'");
					$lightSettings->state->bri = HUE_RESUME_MAX_BRI;
				}

				$settings->LightStates[$groupName][$lightID] = array(
					'on' => $lightSettings->state->on,
					'bri' => $lightSettings->state->bri,
					'hue' => $lightSettings->state->hue,
					'xy' => $lightSettings->state->xy
				);
			}
			else {
				$settings->LightStates[$groupName][$lightID] = array(
					'on' => $lightSettings->state->on,
				);
			}

			// Get specific group or light state settings
			if (isset($hueLightPlayResumeStates[$lightID])) {
				$stateSettings = $hueStatesSettings[$hueLightPlayResumeStates[$lightID]];
				logWrite("New state '".$hueLightPlayResumeStates[$lightID]."' will be applied");
			}
			else {
				$stateSettings = $hueStatesSettings[$hueGroupPlayResumeStates[$groupName]];
				logWrite("New state '".$hueGroupPlayResumeStates[$groupName]."' will be applied");
			}


			// If light needs to be turned off and it is already turned off, do nothing.
			If ($lightSettings->state->on == false && $stateSettings['on'] == false) {
				logWrite("Light '".$lightID."' is already turned off. Continue with next light");
				continue;
			}
			else {
				logWrite("Setting new state setting for light '".$lightID."'");
			}

			// Encode state settings for this specific group
			$jsonHueEncoded = json_encode($stateSettings);

			// Send cURL request
			$url = HUE_API_URL.'lights/'.$lightID.'/state';
			sendWebRequest(
				$url,
				'PUT',
				false,
				$jsonHueEncoded,
				array(
					'Content-Type: application/json',
					'Content-Length: '.strlen($jsonHueEncoded)
				)
			);

			// Delay the execution of the next request
			if (HUE_THROTTLING_MS) {
				logWrite('Sleeping for '.(HUE_THROTTLING_MS).' milliseconds');
				sleep(HUE_THROTTLING_MS/1000);
			}
		}
	}
}

// Revert to saved light settings
ElseIf ($jsonWebhook->event == 'media.pause' || $jsonWebhook->event == 'media.stop') {
	foreach($settings->LightStates as $groupName => $lights) {
		logWrite("Processing group '".$groupName."'");
		foreach ($lights as $lightID => $light) {

			// If light needs to be turned off and it is already turned off, do nothing.
			if ($hueStatesSettings[$hueGroupPlayResumeStates[$groupName]]['on'] == false && $light->on == false) {
				logWrite("Light '".$lightID."' is already turned off. Continue with next light");
				continue;
			}
			else {
				logWrite("Reverting light '".$lightID."' to saved state setting");
			}

			// Add Transition time to state settings
			$light->transitiontime = HUE_TRANSITION_TIME;

			// Encode state settings for this specific group
			$jsonHueEncoded = json_encode($light);

			// Send cURL request
			$url = HUE_API_URL.'lights/'.$lightID.'/state';
			sendWebRequest(
				$url,
				'PUT',
				false,
				$jsonHueEncoded,
				array(
					'Content-Type: application/json',
					'Content-Length: '.strlen($jsonHueEncoded)
				)
			);

			// Delay the execution of the next request
			if (HUE_THROTTLING_MS) {
				logWrite('Sleeping for '.(HUE_THROTTLING_MS).' milliseconds');
				sleep(HUE_THROTTLING_MS/1000);
			}
		}
	}

	// Remove saved light settings
	$settings->LightStates = null;
	logWrite('Saved light state settings have been removed');
}

// Assigning webhook event to settings
$settings->LastEvent = $jsonWebhook->event;
logWrite("Webhook event '".$jsonWebhook->event."' has been assigned to lastEvent setting");

// Saving setting into json file
file_put_contents(SETTING_FILE_NAME, json_encode($settings, JSON_PRETTY_PRINT));
logWrite("Settings have been saved into '".SETTING_FILE_NAME."'");

exitPlexHue();

/**
 * Sends Web request using cURL
 * 
 * @param	string	$url
 * @param	string	$requestType
 * @param	boolean	$returnTransfer
 * @param	string	$postFields
 * @param	array	$httpHeader
 * @return	boolean
 */
function sendWebRequest($url, $requestType = 'GET', $returnTransfer = false, $postFields = '', $httpHeader = array()) {
	// Create new cURL resource
	$ch = curl_init();

	// Set cURL options
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, $returnTransfer);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $requestType);
	curl_setopt($ch, CURLOPT_URL, $url);

	if ($postFields != '') {
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
	}
	if (count($httpHeader)){
		curl_setopt($ch, CURLOPT_HTTPHEADER, $httpHeader);
	}

	// Send web request
	$response = curl_exec($ch);

	// Close cURL resource
	curl_close($ch);

	return $response;
}
/**
 * Logs the passed text to the defined log file
 *
 * @param	string	$text
 * @return	none
 *
 */
function logWrite($text) {
	if (LOG_ENABLED) {
		$date = getdate();

		if ($text == '') {
			$outText = "\n";
		} else {
			$outText = date("d.m.Y, H:i:s")." - ".$text."\n";
		}

		file_put_contents(LOG_FILE_NAME, $outText, FILE_APPEND);
	}
}

/**
 * Main function to exit the script
 *
 * @param	string	$text
 * @param	boolean	$removeLockFile
 *
 */
function exitPlexHue($text, $removeLockFile = true) {
	// log text if needed
	if ($text != '') {
		logWrite($text);
		echo $text;
	}

	// remove lock file
	if (LOCK_FILE_ENABLED && $removeLockFile) {
		logWrite('Lock file deleted');
		unlink('PlexHue.LOCK');
	}

	// log final message
	logWrite('############## Stopping PlexHue '.PLEXHUE_VERSION.' ##############');

	// terminate script
	exit;
}

/**
 * Debug function to dump a variable into a text file and terminate the execution of PlexHue.
 */
function dumpVar2File($variable) {
	if (file_exists("dump.txt")) {
		unlink("dump.txt");
	}

	ob_flush();
	ob_start();
	var_dump($variable);
	file_put_contents("dump.txt", ob_get_flush());
	
	exitPlexHue();
}