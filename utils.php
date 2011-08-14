<?php
/**
 * @brief Get users that interracted in a specified way.
 *
 * @param fb Facebook API wrapper
 * @param type Name of Graph API query
 * 
 * @return List of friend ID's, who interracted
 */
function checkInterraction($res) {
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
			
			// Marking interractions with the same type but with distinct dates
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
			
				// Marking interractions with the same type but distinct dates
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
    $list = $main->getList();
	$minTime = $main->getLowerTimeLimit();
	$diffTime = time() - $minTime; // Maximum time interval

    foreach ($sec as $id => $formattedTimeList) {
        if (array_key_exists($id, $list)) {
            $friend = $list[$id];
			
			foreach ($formattedTimeList as $formattedTime) {
				$time = strtotime($formattedTime); // standard Unix time in seconds
				$score = computeScore($time, $minTime, $diffTime);
				if ($message == "home") {
					$score = round($score / 10); // Home is much less useful
				}
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
	//$score = round(log($createdTime - $minTime + M_E) / log($diffTime) * 100);
	$score = round(($createdTime - $minTime) / $diffTime * 100);
	$score = min(100, $score);
	$score = max(1, $score);
	return $score;
}

/**
 * @brief Makes a batch request.
 *
 * @param fb Facebook API wrapper
 * @param apiCalls Array of data to be retrieved
 * @param since String representing difference in time (for strtotime): -1week, -1month, -3month, -6month, -1year, -30year
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
		array_push($queries, array('method' => 'GET', 'relative_url' => urlencode("/me/$call?fields=from$to&since=" . strtotime($since) . "&until=today&limit=1000000")));
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
	for ($i = 1; $i <= $total; $i++) {
		if ($i == $current) {
			echo $i . "&nbsp";
		} else {
			echo "<a href=\"list.php?pagesize=$pagesize&page=$i\">$i</a>\n";
		}
	}
}
?>
