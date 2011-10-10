<?php
require_once('facebook/src/facebook.php');
require_once('db.php');
require_once('friend_list.php');

// In lieu of magic numbers - here we go
// define("debug", TRUE);
$inbox_key_name = "inbox";
$outbox_key_name = "outbox";
$home_key_name = "home";
$tag_key_name = "tagged";
$score_percentage = array($home_key_name => 5, $inbox_key_name => 10, $outbox_key_name => 10, $tag_key_name => 20);
$max_status_length = 40;
$message_reply_window = 3600 * 24 * 3; // 3 days
$update_interval = 3600 * 24; // at most 1 day/1 hour between forced updates
$query_chunk_size = 200;
$timeout_limit = 600;

ini_set('mysql.connect_timeout', $timeout_limit);
ini_set('default_socket_timeout', $timeout_limit);
set_time_limit($timeout_limit);

error_reporting(E_ALL);

// App information
$app_secret = 'hidden';
$app_id = '139006766174656';
$app_addr = 'https://apps.facebook.com/friend-sieve/';

// Part of redirect script
$js = "<script type=\"text/javascript\">top.location.href =";

function logged_in_check() {
	global $js, $app_addr, $app_secret, $app_id;
	$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
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
	global $update_interval;
	$user = $fb->getUser();
	$db = new dbWrapper();
	$storedData = $db->getUser($user);
	$current_timestamp = time();
	$last_update = $storedData["last_update"];
	$is_fresh = false;

	// Update now if this is the first time
	if ($current_timestamp - $last_update > $update_interval || isset($_GET["update"])) {
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
		$is_fresh = true;
		
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
	
	if ($is_fresh) {
		$list->set_fresh_data();
	}
	return $list;
}

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

/* Select only the friend entries that match a search string */
function filter_friend_list($friends, $pattern) {
	$pattern = process_string($pattern);
	if (strlen($pattern) == 0) {
		return;
	}
	$match_percent = 75;
	$max_edits = floor(strlen($pattern) * (1 - $match_percent / 100));

	$list = $friends->getList();
	foreach ($list as $id => $friend) {
		$name = $friend->indexable_name();
		if (levenshtein_substr($pattern, $name, $max_edits) == false) {
			unset($list[$id]);
		}
	}
	$friends->setList($list);
}

function process_string($str) {
	return strtolower(preg_replace("/[^A-Za-z0-9]/", "", $str));
}

function levenshtein_substr($s, $t, $max_edits) {
	$n = strlen($t);
	$m = strlen($s);
	$curr_row = array_fill(0, $n + 1, 0); // d[i]
	$prev_row = array_fill(0, $n + 1, 0); // d[i - 1]
	
	for ($i = 1; $i <= $m; $i++) {
		$tmp =& $curr_row; // swap the rows => i++
		$curr_row =& $prev_row;
		$prev_row =& $tmp;

		$minim = $curr_row[0] = $i;
		for ($j = 1; $j <= $n; $j++) {
			if ($s[$i - 1] == $t[$j - 1]) {
				$value = $prev_row[$j - 1];
			} else {
				$value = min(
					$curr_row[$j - 1], // d[i][j - 1]
					$prev_row[$j - 1], // d[i - 1][j - 1]
					$prev_row[$j] // d[i - 1][j]
				) + 1;
			}
			if ($value < $minim) {
				$minim = $value;
			}
			$curr_row[$j] = $value;
		}

		// Check if previous row will exceed max_edits
		if ($minim > $max_edits) {
			return false;
		}
	}

	return true;
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
