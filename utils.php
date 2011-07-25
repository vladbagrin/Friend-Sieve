<?php
/**
 * @brief Get users that interracted in a specified way.
 *
 * @param fb Facebook API wrapper
 * @param type Name of Graph API query
 * 
 * @return List of friend ID's, who interracted
 */
function checkInterraction($fb, $type) {
    $res = $fb->api("/me/$type");
	$res = $res["data"];
	$list = array();

    foreach ($res as $entry) {
        if (array_key_exists('from', $entry)) {
            $from = $entry['from'];
            $list[$from['id']] = $from['name'];
        }

        if (array_key_exists('to', $entry)) {
			if (array_key_exists('data', $entry['to'])) {
				$to = $entry['to']['data'];
			} else {
				$to = $entry['to'];
			}
            foreach ($to as $user) {
		        $list[$user['id']] = $user['name'];
            }
        }
	}

    return $list;
}

/**
 * @brief Like checkInterraction but without the API call
 *
 * Very important: json_decode messes up the data representation. Now everything is an object.
 *
 * @param res Response from the previous batch request
 */
function shortCheckInterraction($res) {
	$list = array();

    foreach ($res as $entry) {
        if (property_exists($entry, 'from')) {
            $from = $entry->from;
			if (gettype($from) == "object") {
				$list["$from->id"] = $from->name;
			}
        }

        if (property_exists($entry, 'to')) {
			if (property_exists($entry->to, 'data')) {
				$to = $entry->to->data;
			} else {
				$to = $entry->to;
			}
            foreach ($to as $user) {
				if (gettype($user) == "object") {
					$list["$user->id"] = $user->name;
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
    foreach ($sec as $id => $user) {
        if (array_key_exists($id, $list)) {
            $friend = $list[$id];
            $friend->updateStatus($message);
        }
    }
}

/**
 * @brief Makes a batch request.
 *
 * @param fb Facebook API wrapper
 * @param apiCalls Array of data to be retrieved
 *
 * @return Array of responses
 */
function batchRequest($fb, $apiCalls) {
	$queries = array();
	
	foreach ($apiCalls as $call) {

		// Temporary fix
		$to = ",to";
		if ($call == "photos") {
			$to = "";
		}
		array_push($queries, array('method' => 'GET', 'relative_url' => urlencode("/me/$call?fields=from$to&since=1199145600&until=today&limit=1000000")));
	}

	$res = $fb->api('/?batch=' . json_encode($queries), 'POST');
	return $res;
}
?>
