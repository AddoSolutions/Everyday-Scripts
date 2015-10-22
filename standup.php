<?php

header("Content-Type: text/json; charset=UTF-8");

date_default_timezone_set('America/New_York');
require 'vendor/autoload.php';

// Be sure to make your config file!
require('./config.php');

$gist = $config['standup']['templateURL'];

$yesterday = strtotime("yesterday");
if(date("w") == 7 || date("w") == 1) $yesterday = strtotime("last friday");


class AppTrello{

	private static $trello;
	const LINE_LENGTH = 60;

	function getTrello(){

		global $config;

		if(self::$trello) return self::$trello;

		$key = $config['standup']['key'];
		$secret = $config['standup']['secret'];
		$token=$config['standup']['token'];
		$osecret = $config['standup']['osecret'];

		$trello = null;

		if($token){
			$trello = new \Trello\Trello($key, $secret, $token, $osecret);

		}else{
			$trello = new \Trello\Trello($key, $secret);
			$trello->authorize(array(
				'expiration' => 'never',
				'scope' => array(
					'read' => true,
				),
				'name' => 'My Daily Standup'
			));

			$tokenInfo = $trello->token().":".$trello->oauthSecret();

			file_put_contents("trellotoken.txt", $tokenInfo);

			echo "All set! Now you can run this script stright from the CLI!\n\n";

			echo "Your your reading pleasure, the API info is below:\n\n";

			print_r($trello);

			die();

		}

		self::$trello = $trello;

		return $trello;
	}

	function getBoards(){
		$trello = $this->getTrello();

		return $trello->members->get('my/boards', array(
			"filter"=>"open"
		));

	}

	function processCardlist($cards){

		$lastBoard = false;

		$s = "";

		foreach($cards as $card){

			if(!$lastBoard || $lastBoard->id != $card->board->id){
				$s .= $this->getBoardString($card->board);
				$lastBoard = $card->board;
			}

			$s .= $this->getCardString($card);

		}

		return $s;
	}

	function filterLine($line, $lineIndent){

		$count = AppTrello::LINE_LENGTH-strlen($lineIndent);

		return implode(str_split($line,$count),$lineIndent);
	}

	function getBoardString($board){

		$s = $board->name;

		return " - ".$this->filterLine($s,"\n   ")."\n";

	}
	function getCardString($card){

		$s = $card->name;

		$labels = "";

		if($card->labels){
			$labels = array();
			foreach($card->labels as $label){
				if($label->name) $labels[] = $label->name;
			}
			if(count($labels) > 0) $labels = " (".implode($labels,", ").")";
		}

		$s.=$labels;

		$seperator = "\n     ";

		$url = implode('/', array_slice(explode('/', $card->url), 0, -1));

		return " : - ".$this->filterLine($s,$seperator).$seperator.$url."\n";

	}

}


$app = new AppTrello();
$trello = $app->getTrello();

$me = $trello->members->get("me");

$boards = $app->getBoards();

$output = file_get_contents($gist);

$data = array(
	"today" => array(),
	"yesterday" => array()
);

$replace = $data;

$i=0;
$dow = date("N")%7;
while($i<7){

	$marker = "✓";
	if($i==$dow) $marker = "➤";
	if($i > $dow) $marker = "◌";

	$i++;
	$replace["day$i"] = $marker;
}



$replace["yesterdate"] = date('l', $yesterday);

foreach($boards as $board){

	if($config['standup']['boardmatch'] && !preg_match($config['standup']['boardmatch'], $board->name)){
		echo "Skipping Board: ".$board->name."\n";
		continue;
	}

	echo "Loading Board: ".$board->name."\n";

	$lists = $trello->boards->get($board->id."/lists", array(
		"cards"=>"open"
	));

	foreach ($lists as $list){

		$listName = $list->name;

		$reflist = clone $list;
		unset($reflist->cards);

		//We only want todays stuff
		if(stripos($listName, "Today") === false && stripos($listName, "Doing") === false ){
			echo " - Skipping: " . $listName."\n";
			continue;
		}


		foreach($list->cards as $card){

			//Only my cards matter
			if(!in_array($me->id, $card->idMembers)){
				continue;
			}

			$card->list = $reflist;
			$card->board = $board;

			$data['today'][] = $card;

		}
	}

	echo " - Loading Activity";

	$activityFeed = $trello->boards->get($board->id."/actions", array(
		"cards"=>"open",
		"entities"=>"true",
		"limit"=>'300'
	));
	echo "...";


	foreach($activityFeed as $activity){

		//Again, only MY cards
		if($me->id != $activity->idMemberCreator){
			continue;
		}

		if(@!$activity->data->listAfter) $listName = false;
		else $listName = $activity->data->listAfter->name;

		//We only want completed cards
		if(!$listName || (!preg_match("/Done/",$listName) && $listName!="QA")){
			//echo " - No List After: ".$listName."\n";
			continue;
		}

		//print_r($activity);
		//die();

		$date = strtotime($activity->date);
		if($date < $yesterday) continue;

		$card = $trello->boards->get($board->id."/cards/".$activity->data->card->id);

		$card->board = $board;

		$data['yesterday'][$card->id] = $card;

	}
	echo "Done!\n";

}


//print_r($data);


$replace["today"] = $app->processCardlist($data["today"]);
$replace["yesterday"] = $app->processCardlist($data["yesterday"]);

$replace["todate"] = date('l');

//print_r($replace);

foreach($replace as $n=>$v){
	$output = str_replace("{{data.".$n."}}",$v, $output);
}


//print_r($me);

/*print_r(
	$trello->boards->get($board->id."/lists", array(
		"cards"=>"all"
	))
);

print_r(
	$trello->boards->get($board->id."/actions", array(
		"cards"=>"all"
	))
);*/


/*
Card Object:
(
    [id] => 51a7b35535089960140064eb
    [checkItemStates] => Array
        (
        )

    [closed] =>
    [dateLastActivity] => 2013-05-30T20:15:17.317Z
    [desc] => Why use Addo?  What makes us special?  What sets up apart?  Why do we rock?
    [descData] =>
    [email] =>
    [idBoard] => 51a76e3d0aabcf35330017ad
    [idList] => 51a76e3d0aabcf35330017ae
    [idMembersVoted] => Array
        (
        )

    [idShort] => 5
    [idAttachmentCover] =>
    [manualCoverAttachment] =>
    [idLabels] => Array
        (
            [0] => 545c12e274d650d5673566b0
        )

    [name] => Company Key Points
    [pos] => 16384
    [shortLink] => Icm6pT18
    [badges] => stdClass Object
        (
            [votes] => 0
            [viewingMemberVoted] =>
            [subscribed] => 1
            [fogbugz] =>
            [checkItems] => 0
            [checkItemsChecked] => 0
            [comments] => 2
            [attachments] => 0
            [description] => 1
            [due] =>
        )

    [due] =>
    [idChecklists] => Array
        (
        )

    [idMembers] => Array
        (
            [0] => 517fe350d7cd147469000d1a
            [1] => 51a76fb78ffb80af45000ae0
        )

    [labels] => Array
        (
            [0] => stdClass Object
                (
                    [id] => 545c12e274d650d5673566b0
                    [idBoard] => 51a76e3d0aabcf35330017ad
                    [name] =>
                    [color] => blue
                    [uses] => 4
                )

        )

    [shortUrl] => https://trello.com/c/Icm6pT18
    [subscribed] => 1
    [url] => https://trello.com/c/Icm6pT18/5-company-key-points
)


Activity Object:

(
            [id] => 5466b3d94c235aec1770d723
            [idMemberCreator] => 517fe350d7cd147469000d1a
            [data] => stdClass Object
                (
                    [listAfter] => stdClass Object
                        (
                            [name] => Doing
                            [id] => 5464f1cf5ee15c7c082b6195
                        )

                    [listBefore] => stdClass Object
                        (
                            [name] => To Do
                            [id] => 5464f1cd4c1b3a623292a1d0
                        )

                    [board] => stdClass Object
                        (
                            [shortLink] => TGIEVQTP
                            [name] => Artman App
                            [id] => 5464f1a446f0536f14843cec
                        )

                    [card] => stdClass Object
                        (
                            [shortLink] => RKl1lU87
                            [idShort] => 14
                            [name] => Migrations
                            [id] => 546551e8491f8d1586c5cc68
                            [idList] => 5464f1cf5ee15c7c082b6195
                        )

                    [old] => stdClass Object
                        (
                            [idList] => 5464f1cd4c1b3a623292a1d0
                        )

                )

            [type] => updateCard
            [date] => 2014-11-15T02:00:57.166Z
            [memberCreator] => stdClass Object
                (
                    [id] => 517fe350d7cd147469000d1a
                    [avatarHash] => 8252c3921f6926bacf6f1b928e53d297
                    [fullName] => Nick Artman
                    [initials] => NA
                    [username] => nickartman
                )

        )
 */


echo "\n\n==============================================\n\n\n\n\n```\n".$output."\n```\n\n";

mkdir(__DIR__."/standup-logs");
$filename = __DIR__."/standup-logs/log-" . strtolower(date("Y-m-M")) . ".txt";

$date = date("F j, Y");
$time = date("g:i a");

function centerText($text){
	$count = (AppTrello::LINE_LENGTH - (strlen($text)+2))/2;
	$mod = $count%1;
	$count = floor($count);
	$divider = str_repeat("-", $count);
	$out = $divider." ".$text." ".$divider;
	if($mod) $out.="-";
	return $out;
}

$dateHeader = centerText($date);
$timeHeader = centerText($time);
$line = str_repeat("-", AppTrello::LINE_LENGTH);

$data = "\n\n$timeHeader\n$line\n$dateHeader\n$line\n\n".$output;
$check = "$line\n$dateHeader\n$line";

$currentFile = @file_get_contents($filename);
if(stristr($currentFile, $check)===FALSE){
	file_put_contents ($filename , $data, FILE_APPEND);
}else{
	$parts = explode($check, $currentFile);
	$data = $parts[0]." ... UPDATED ".$data;
	file_put_contents ($filename , $data);
}
