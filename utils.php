<?php
require_once('facebook/src/facebook.php');
require_once('db.php');
require_once('friend_list.php');

// In lieu of magic numbers - here we go
//define("debug", TRUE);
$inbox_key_name = "inbox";
$outbox_key_name = "outbox";
$home_key_name = "home";
$tag_key_name = "tagged";
$score_percentage = array($home_key_name => 5, $inbox_key_name => 10, $outbox_key_name => 10, $tag_key_name => 20);
$message_reply_window = 3600 * 24 * 3; // 3 days
$query_chunk_size = 200;
$timeout_limit = 600;

ini_set('mysql.connect_timeout', $timeout_limit);
ini_set('default_socket_timeout', $timeout_limit);
set_time_limit($timeout_limit);

// App information
$app_secret = '7341d578889ab343d851284665976ea8';
$app_id = '139006766174656';
$app_addr = 'http://apps.facebook.com/friend-sieve/';

// Part of redirect script
$js = "<script type=\"text/javascript\">top.location.href =";

function logged_in_check() {
	global $js, $app_addr, $app_secret, $app_id;
	
	/*if (!isset($_SESSION["fb"])) {
		$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
		$_SESSION["fb"] = $fb;
	} else {
		$fb = $_SESSION["fb"];
	}*/
	$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
	
	/*print_r($fb);
	exit;*/
	$user = $fb->getUser();
	if (!$user) {
		$scope = 'read_mailbox,read_stream,friends_photo_video_tags,user_photo_video_tags';
		$params = array('scope' => $scope, 'redirect_uri' => $app_addr);
		$login = $fb->getLoginUrl($params);
		$redirect_script = "$js \"$login\";</script>";
		echo $redirect_script;
		exit;
	} else {
		return $fb;
	}
}

function prepare_friend_data($fb) {
	$user = $fb->getUser();
	$db = new dbWrapper();
	$storedData = $db->getUser($user);

	// Update now if this is the first time
	if ($storedData["last_update"] == 0 || isset($_GET["update"])) {
		$db->close();
		
		$startTime = mtime();
		$list = new FriendList($fb);
		if (defined("debug")) {
			echo "Friend count: " . count($list->getList()) . "<br>";
		}
		$gotListTime = mtime();
		$list->fromFacebook($fb, $storedData["last_update"]);
		$processedTime = mtime();
		
		$db->connect();
		$list->dbDump($db, $fb);
		$db->updateTime($user);
		if (defined("debug")) {
			echo "Friend count after database update: " . count($list->getList()) . "<br>";
		}
		
		$endTime = mtime();
		if (defined("debug")) {
			echo "Friend list request: " . ($gotListTime - $startTime) . "ms<br>\n";
			echo "Database update: " . ($endTime - $processedTime) . "ms<br>\n";
			echo "Total update duration: " . ($endTime - $startTime) . "ms<br>\n";
		}
	}

	if (!isset($_SESSION["friendlist"]) || isset($_GET["refresh"])) {
		$startTime = mtime();

		// Select since when to check interaction
		$since = "-1year";
		if (isset($_GET["since"])) {
			$since = $_GET["since"];
		}
		$since = strtotime($since);

		$list = new FriendList(null);
		$list->fromdatabase($db, $fb, $since);
		
		// Select order of elements for sorting
		if (isset($_GET["order"]) && isset($_GET["by"])) {
			$list->sort($_GET["order"], $_GET["by"]);
		} else {
			$list->sort("desc", "score");
		}

		// Save as session variable
		$_SESSION["friendlist"] = $list;
		
		$endTime = mtime();
		if (defined("debug")) {
			echo "Reading from database: " . ($endTime - $startTime) . "ms<br>\n";
		}
	} else {
		$list = $_SESSION["friendlist"];
	}
	
	return $list;
}

//error_reporting(E_ALL);

/**
 * @brief Get users that interacted in a specified way.
 *
 * @param fb Facebook API wrapper
 * 
 * @return List of friend ID's, who interacted
 */
function checkinteraction($res) {
	$list = array();

    foreach ($res as $entry) {
		if (array_key_exists('updated_time', $entry)) {
			$time = $entry['updated_time'];
		} else if (array_key_exists('created_time', $entry)) {
			$time = $entry['created_time'];
		} else {
			continue;
		}
		
        if (array_key_exists('from', $entry)) {
            $from = $entry['from'];
			$fromId = $from['id'];
			
			// Marking interactions with the same type but with distinct dates
			if (array_key_exists($fromId, $list)) {
				array_push($list[$fromId], $time);
			} else {
				$list[$fromId] = array($time);
			}
        }

        if (array_key_exists('to', $entry)) {
			if (array_key_exists('data', $entry['to'])) {
				$to = $entry['to']['data'];
			} else {
				$to = $entry['to'];
			}
            foreach ($to as $user) {
				$toId = $user['id'];
			
				// Marking interactions with the same type but distinct dates
				if (array_key_exists($toId, $list)) {
					array_push($list[$toId], $time);
				} else {
					$list[$toId] = array($time);
				}
            }
        }
	}

    return $list;
}

/**
 * @brief Updates a friend status if his ID is in both lists.
 * 
 * @param $main List of all friends
 * @param $sec List of ID's to check in main
 * @param $message Text to update the status with
 */
function crossCheck($main, $sec, $message) {
	global $home_key_name;
	global $score_percentage;
	
    $list = $main->getList();
	$minTime = $main->getLowerTimeLimit();
	$diffTime = time() - $minTime; // Maximum time interval

    foreach ($sec as $id => $formattedTimeList) {
        if (array_key_exists($id, $list)) {
            $friend = $list[$id];
			
			foreach ($formattedTimeList as $formattedTime) {
				$time = strtotime($formattedTime); // standard Unix time in seconds
				$score = computeScore($time, $minTime, $diffTime);
				$friend->updateStatus($message, $score, $time);
			}
        }
    }
}

/**
 * @brief Applies the score formula.
 *
 * The scale is logarithmic. Values are in seconds.
 *
 * @param createdTime When the entry was created
 * @param minTime Lower limit of entry created time
 * @param diffTime Maximum selected time interval
 *
 * @return Score - from 10 to 100
 */
function computeScore($createdTime, $minTime, $diffTime) {
	$score = round(log($createdTime - $minTime + M_E) / log($diffTime) * 100);
	//$score = round(($createdTime - $minTime) / $diffTime * 100);
	$score = min(100, $score);
	$score = max(1, $score);
	return $score;
}

/**
 * @brief Makes a batch request.
 *
 * @param fb Facebook API wrapper
 * @param apiCalls Array of data to be retrieved
 * @param since String representing difference in time (for strtotime): -1week, -1month, -3month, -6month, -1year, -2year
 *
 * @return Array of responses
 */
function batchRequest($fb, $apiCalls, $since) {
	$queries = array();
	
	foreach ($apiCalls as $call) {

		// Temporary fix
		$to = ",to";
		if ($call == "photos") {
			$to = "";
		}

		$url =  "/me/$call?fields=from$to&since=" . $since . "&until=now&limit=1000000";
		array_push($queries, array('method' => 'GET', 'relative_url' => urlencode($url)));
	}

	$res = $fb->api('/?batch=' . json_encode($queries), 'POST');
	return $res;
}

/**
 * @brief Create a list of page links
 *
 * @param pagesize Number of elements on page (important for links)
 * @param total Total number of pages
 * @param current Current page (different style, not a link)
 */
function genPageLinks($pagesize, $total, $current) {
	$fringeNumber = 3; // Number of page links at both ends
	$sidesNumber = 2; // Number of page links on the sides of the current one

	// Draw previous page link
	if ($current != 1) {
		echo "<a class=\"pages\" onclick=\"changePage($current - 1)\">&laquo;</a>\n";
	}
	
	// Draw first 3 pages always
	for ($i = 1; $i <= $fringeNumber && $i <= $total; $i++) {
		doTheMagic($i, $current);
	}
	
	// Draw the middle 9 pages
	$start = max($current - $sidesNumber, $fringeNumber + 1);
	if ($start > $fringeNumber + 1) {
		echo "<span class=\"page_links\">...&nbsp;</span>";
	}
	for ($i = $start; $i <= $current + $sidesNumber && $i <= $total; $i++) {
		doTheMagic($i, $current);
	}
	
	// Draw the last 3 pages
	$last = max($total - $fringeNumber + 1, $current + $sidesNumber + 1);
	if ($last > $current + $sidesNumber + 1) {
		echo "<span class=\"page_links\">...&nbsp;</span>";
	}
	for ($i = $last; $i <= $total; $i++) {
		doTheMagic($i, $current);
	}
	
	// Draw next page link
	if ($current != $total) {
		echo "<a class=\"pages\" onclick=\"changePage($current + 1)\">&raquo;</a>\n";
	}
	
	echo "<br><br>";
}

function doTheMagic($i, $current) {
	if ($i == $current) {
		echo "<span class=\"pages\">$i</span>\n";
	} else {
		echo "<a class=\"pages\" onclick=\"changePage($i)\">$i</a>\n";
	}
}

/**
 * @brief Time in milliseconds of the Unix Epoch
 *
 * @return Number of milliseconds since January 1st, 1970
 */
function mtime() {
	return microtime(true) * 1000;
}

function cut_score($score, $percent) {
	return round($score * $percent / 100);
}
?>