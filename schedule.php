<?php

header("Content-Type: text/json; charset=UTF-8");
date_default_timezone_set('America/New_York');

$pattern = '/(schedule|travel)(\r?\n)(---+|===+|___+)\n(.*\n){5,6}/i';
$cleanup = '/[^a-zA-Z\d\s:\-\.,\(\)\@\/\\!]/i';
$startOfWeek = strtotime("last sunday");
$today = strtotime("today");

require 'vendor/autoload.php';

require('./config.php');

use Frlnc\Slack\Http\SlackResponseFactory;
use Frlnc\Slack\Http\CurlInteractor;
use Frlnc\Slack\Core\Commander;

$interactor = new CurlInteractor();
$interactor->setResponseFactory(new SlackResponseFactory);

$commander = new Commander($config['schedule']['slackWebhookId'], $interactor);


$standups = array();
$users = array();
$officeDays = array();


ob_start();

$response = $commander->execute('users.list', [
    'channel' => $config['schedule']['slackChannel'],
		'oldest' 	=> $startOfWeek
]);


foreach($response->getBody()['members'] as $user){
	$user['nice_name'] = $user['profile']['real_name'];
	if(!$user['nice_name']) $user['nice_name'] = '@'.$user['name'];
	$users[$user['id']] = $user;
}



$response = $commander->execute('channels.history', [
    'channel' => $config['schedule']['slackChannel'],
		'oldest' 	=> $startOfWeek
]);

//print_r($response);

foreach($response->getBody()['messages'] as $message){
	if(!stristr($message['text'], "```")) continue;
	if(@!$standups[$message['user']] || $standups[$message['user']]['ts'] < $message['ts']){

		preg_match($pattern, $message['text'], $matches);

		if(@!$matches[0]) continue;

		$message['schedule'] = $matches[0];

		$standups[$message['user']] = $message;
	}

}

foreach($standups as $standup){
	echo "*".$users[$standup['user']]['nice_name']."*:\n\n";


	$lines = explode("\n", preg_replace($cleanup, " ", $standup['schedule']));


	unset($lines[0], $lines[1]);

	$days = array();

	foreach($lines as $line){
		$line = trim($line, "-*O: \t\n\r\0\x0B");
		if(strlen($line) < 2) continue;

		list($day, $location) = preg_split('/(-|:)/', $line, 2, PREG_SPLIT_NO_EMPTY);

		$day = trim($day);
		$originalDay = $day;
		if(strlen($day) == 1){
			switch(strtoupper($day)){
				case 'M': $day = "Monday"; break;
				case 'T': $day = "Tuesday"; break;
				case 'W': $day = "Wednesday"; break;
				case 'R': $day = "Thursday"; break;
				case 'H': $day = "Thursday"; break;
				case 'F': $day = "Friday"; break;
				case 'S': $day = "Saturday"; break;
				case 'U': $day = "Today"; break;
				default: break;
			}
		}

		$day = strtotime($day, $startOfWeek);

		$location = ucwords(trim($location));

		echo "> `".date('D', $day)."`: ";

		$days[$day] = $location;

		if($day == $today){
			echo "*{$location}*";
		}else{
			echo $location;
		}
		echo "\n";

    if(@!$officeDays[$day]) $officeDays[$day] = array();
		if(stristr($location, 'Medina') || strtolower($location) == "office"){
			$officeDays[$day][] = $users[$standup['user']];
		}
	}

	$standup['days'] = $days;

	echo "\n\n\n";

}

ksort($officeDays);

echo "*Office Days*\n\n";

foreach($officeDays as $day=>$officePeople){
	echo "> `".date('D', $day)."`: ";
	if($day == $today) echo "*";

  $names = array();

  if(count($officePeople) > 0){
  	foreach($officePeople as $user){
  		$names[] = $user['nice_name'];
  	}
    echo implode(", ", $names);
  }else{
    echo "_Nobody_";
  }

	if($day == $today) echo "*";
	echo "\n";
}

$text = ob_get_flush();


$response = $commander->execute('chat.postMessage', [
    'channel' => $config['schedule']['slackChannel'],
		'username' => 'Daily Schedule',
		'icon_emoji' => ':calendar2:',
		'text'=>$text
]);
