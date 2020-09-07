# PlexHue
PlexHue is a PHP Application which connects Plex to the Philips Hue Lighting System. As an example, you can trigger a certain light state when starting to watch a movie on a defined player. When pausing or stopping the movie, the lights will resume to the original state.

## IMPORTANT
PlexHue and this Documentation is work in progress and is not feature complete yet!

## Prerequisites
* PHP 5.6 or greater with curl extension
* Plex Pass (required for using the Webhooks in Plex Media Server)
* The Webserver hosting PlexHue must be reachable from Plex Media Server and must be able to reach the Philips Hue Bridge.

## Files
* `PlexHue.json` Used by PlexHue.php to store settings. This file shouldn't be edited as it is fully controlled by PlexHue.
* `PlexHue.log` Used by PlexHue.php for logging to troubleshoot issues.
* `PlexHue.php` Contains the main logic.
* `PmsWebhookPayloadEmulator.html` Testing utility to simulate Plex Webhooks.

## Installing
1. Copy "PlexHue.json", "PlexHue.log", "PlexHue.php" to your Webserver.
2. Open "PlexHue.php" and modify the below options 
	* `PLEX_PLAYER` Define the name of the Plex Player. (e.g. Living)
	* `HUE_API_URL` Define the Address to your Philips Hue Bridge with a valid API Token. (There are tons of blog posts in the internet on how to get an API Token from the Hue Bridge).
	* `$hueGroups` Define all the Hue Groups/Room in your Home, which you want to control. Currently you need to retrieve the group id directly from the bridge (work in progress).
	* `$huePlayResumeStates` Define the states (configured below), which should apply to the groups when playing or resuming a movie.
	* `$hueStatesSettings` Define the light states that are used above. By default, this contains a "movie" state, which is a darkened red, as well as a state to turn the lights off.
3. Open Plex Media Server -> Settings -> Webhooks -> Add Webhook. Define the URL to PlexHue.php
4. Everything should be good. You can use "PmsWebhookPayloadEmulator.html" to simulate a Plex Webhook for finetuning your settings, without the need to play/stop a movie every time.
