# PlexHue
PlexHue is a PHP Application which connects Plex to the Philips Hue Lighting System. As an example, you can trigger a certain light state when starting to watch a movie on a defined player. When pausing or stopping the movie, the lights will resume to the original state.

## Prerequisites
* PHP 7.0 or greater with curl extension.
* Plex Pass (https://www.plex.tv/plex-pass/) (required for using the Webhooks in Plex Media Server)
* The Webserver hosting PlexHue must be reachable from Plex Media Server and must be able to reach the Philips Hue Bridge.

## Files
* `PlexHue.json` Used by PlexHue.php to store settings. This file shouldn't be edited as it is fully controlled by PlexHue.
* `PlexHue.log` Used by PlexHue.php for logging to troubleshoot issues.
* `PlexHue.php` Contains the main logic.
* `PmsWebhookPayloadEmulator.html` Standalone testing utility to simulate Plex Webhooks.

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

## Enable / Disable PlexHue via HTTP Request
There are always situations, where you don't like that your lights start dimming while watching a movie. Since version 3.0.0 the option to enable/disable PlexHue can be configured via a simple HTTP Call to the main PlexHue.php Script.

### Enable PlexHue
```
PlexHue.php?SetEnable
```
### Disable PlexHue
```
PlexHue.php?SetDisable
```
### Get Status
```
PlexHue.php?GetStatus
```
Returns 1 if PlexHue is enabled and 0 if disabled.

### Apple Homekit Integration
Personally, I use [Homebridge](https://github.com/homebridge/homebridge) with the [Homebridge Http Switch Plugin](https://www.npmjs.com/package/homebridge-http-switch) for quicky enabling and disabling PlexHue. An example utilizing a stateful switch can be found below.

```
"accessories": [
	{
		"accessory": "HTTP-SWITCH",
		"name": "PlexHue",
		"switchType": "stateful",
		"onUrl": "http://server/PlexHue.php?SetEnable",
		"offUrl": "http://server/PlexHue.php?SetDisable",
		"statusUrl": "http://server/PlexHue.php?GetStatus"
	}
],
```
![PlexHue Homekit Switch 1](https://msitproblog.com/wp-content/uploads/2021/02/PlexHue_HomeKit_HTTP-Switch_1.png) ![PlexHue Homekit Switch 2](https://msitproblog.com/wp-content/uploads/2021/02/PlexHue_HomeKit_HTTP-Switch_2.png)
