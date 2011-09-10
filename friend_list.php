<?php
require_once('utils.php');

/**
 * @brief Helper sort function
 *
 * @param $a Variable to compare
 * @param $b Second variable to compare
 * @param $asc Defines order of sorting: -1 asc, 1 desc
 *
 */
function helperSort($a, $b, $asc) {
	$na = $a->statusWeight();
	$nb = $b->statusWeight();
	if ($na == $nb) {
		return 0;
	}
	return ($na < $nb) ? $asc : -$asc;
}

/**
 * @brief Helper sort function
 *
 * @param $a Variable to compare
 * @param $b Second variable to compare
 * @param $asc Defines order of sorting: -1 asc, 1 desc
 *
 */
function helperSortMutual($a, $b, $asc) {
	$na = $a->countMutualFriends();
	$nb = $b->countMutualFriends();
	if ($na == $nb) {
		return 0;
	}
	return ($na < $nb) ? $asc : -$asc;
}

/**
 * @brief Sorting in descending order
 */
function helperSort_score($a, $b) {
	return helperSort($a, $b, 1);
}

/**
 * @brief Sorting in descending order
 */
function helperSort_mutual($a, $b) {
	return helperSortMutual($a, $b, 1);
}

/**
 * Callback for an array_map function
 * Replaces the friend object with a string representing the friend's full name
 */
function process_friend_name($friend) {
	return $friend->__toString();
}

/**
 * A list of all the user's friends.
 */
class FriendList {
	private $list = null; // Friend info array
	private $timeLimit = 0; // Time of the oldest entries counted in
	
	/**
	 * @brief Populates the class with data from Facebook
	 *
	 * @param fb Facebook API wrapper object
	 * @param since Lower time limit for data retrieved
	 */
	public function fromFacebook($fb, $since) {
		$startTime = round(mtime());
		if ($this->list == null) {
			$this->getFriendList($fb);
		}
		$this->timeLimit = $since;
		
		// List of graph call to make
		$graphCalls = array('inbox', 'outbox', 'tagged', 'photos', 'pokes', 'feed', 'home');
		$res = batchRequest($fb, $graphCalls, $since);
		
		$receivedResponseTime = round(mtime());

		foreach ($res as $key => $value) {
			$decoded = json_decode($value["body"], true);
			$decoded = $decoded["data"];
			crossCheck($this, checkinteraction($decoded), $graphCalls[$key]);
		}
		
		$buildingListTime = round(mtime());
		$this->get_mutual_friends_data($fb);
		$friendEdgesTime = round(mtime());
		
		if (defined("debug")) {
			echo "Graph request: " . ($receivedResponseTime - $startTime) . "ms<br>\n";
			echo "Data processing: " . ($buildingListTime - $receivedResponseTime) . "ms<br>\n";
			echo "Social graph request: " . ($friendEdgesTime - $buildingListTime) . "ms<br>\n";
		}
	}

    function get_mutual_friends_data($fb) {
		global $query_chunk_size;
        $friend_ids = $this->__toString();
		$query = array();
        foreach ($this->list as $id => $friend) {
			$query[$id] = "select uid2 from friend where uid1=$id and uid2 in $friend_ids";
        }
		$queries = array_chunk($query, $query_chunk_size, true);
		$result = array();
		foreach ($queries as $chunk) {
			$result = array_merge($result, $this->send_query($fb, $chunk));
		}
		
		foreach ($result as $result_entry) {
			$result_id = $result_entry["name"];
			$number = count($result_entry["fql_result_set"]);
			$this->list[$result_id]->set_mutual_friends($number);
		}
    }
	
	function send_query($fb, $query) {
		$query = json_encode($query);

		$param = array(
			'method'   => 'fql.multiquery',
			'queries'  => $query,
			'callback' => ''
		);
		$result = $fb->api($param);

		return $result;
	}
	
	/**
	 * @brief Save all data to the database
	 *
	 * @param db Database link
	 * @param fb Facebook API wrapper reference
	 */
	public function dbDump($db, $fb) {
		$user = $fb->getUser();
		
		foreach ($this->list as $friendID => $friend) {
			$result = $friend->dbDump($db, $friendID);
			if (defined("debug") && $result == FALSE) {
				echo "Error inserting Facebook user: $friend<br>";
			}
			$result = $db->insertFriend($user, $friendID, $friend->countMutualFriends());
			if (defined("debug") && $result == FALSE) {
				echo "Error inserting friend relation with: $friend<br>";
			}
			$friend->getStatus()->dbDump($db, $db->getID());
		}
		
		$db->blacklistFriends($user, $this->__toString());
	}
	
	/**
	 * @brief Gets the list of all friends of a user
	 *
	 * @param db Database link
	 * @param fb Facebook wrapper
	 * @param since Lower time limit
	 */
	public function fromDatabase($db, $fb, $since) {
		$result = $db->getFriends($fb->getUser());
		
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$fb_id = $row["fb_id"];
			$entry = array("name" => $row["name"],
							"pic_square" => $row["picture_url"],
							"profile_url" => "http://www.facebook.com/profile.php?id=$fb_id",
							"mutual_friends" => $row["mutual_friends"]);
			$friend = new Friend($entry);
			$status = $friend->getStatus();
			$status->fromDatabase($db, $row["id"], $since);
			$this->list[$fb_id] = $friend;
		}
		
		$this->apply_score_normalization();
	}
	
	/**
	 * @brief Change some of the score values
	 *
	 * For instance - inbox and outbox will only count as 100% if there are replies in a 3 day interval
	 */
	function apply_score_normalization() {
		global $message_reply_window;
		
		foreach ($this->list as $friend) {
			$status = $friend->getStatus();
			$status->normalize_mailbox($message_reply_window);
			$status->normalize_wall_posts();
			$status->normalize_tags();
			$status->compute_friend_score();
		}
	}
	
	/**
	 * @param fb Facebook API wrapper
	 */
	function __construct($fb) {
		if ($fb != null) {
			$this->getFriendList($fb);
		} else {
			$this->list = array();
		}
	}
	
	/**
	 * @brief Populates the list of friends
	 *
	 * @param fb Facebook API wrapper object
	 */
	public function getFriendList($fb) {
		$this->list = array();

		$query = "select uid, name, pic_square, profile_url from user where uid in (select uid2 from friend where uid1=me())";
		$list = $fb->api(array('method' => 'fql.query', 'query' => $query));

		foreach ($list as $entry) {
			$uid = $entry["uid"];
			$this->list[$uid] = new Friend($entry);
		}
	}

	/**
	 * @brief Makes a string with friend IDs
	 *
	 * For use in MySQL queries
	 *
	 * @return String with friend IDs
	 */
	public function __toString() {
		$value = '(';
		foreach ($this->list as $id => $friend) {
			$value = $value . "$id,";
		}
		return substr($value, 0, -1) . ")";
	}

	/**
	 * @brief HTML code representing the friends.
	 *
	 * @param pagesize Number of elements to represent on each page
	 * @param page Page number (starting from 1)
	 */
	public function toHTML($pagesize, $page) {
		foreach (array_slice($this->list, $pagesize * ($page - 1), $pagesize, true) as $uid => $friend) {
			$friend->toHTML($uid);
		}
	}

	public function getList() {
		return $this->list;
	}
	
	public function setList($list) {
		$this->list = $list;
	}
	
	public function length() {
		return count($this->list);
	}
	
	/**
	 * @brief Access a friend's status
	 *
	 * @param id The Facebook id of the friend
	 * @return FriendStatus object
	 */
	public function getStatus($id) {
		$friend = $this->list[$id];
		return $friend != null ? $friend->getStatus() : null;
	}
	
	/**
	 * @brief Set lower time limit
	 *
	 * @param time Lower time limit, in seconds since the Unix epoch
	 */
	public function setLowerTimeLimit($time) {
		$this->timeLimit = $time;
	}
	
	public function getLowerTimeLimit() {
		return $this->timeLimit;
	}

	/**
	 * @brief Sort by number of status items.
	 *
	 * @param order Order of elements: true - descending, false - ascending
	 * @param by Key to order by
	 */
	public function sort($order, $by) {
		$function = "helperSort_" . $by;
		uasort($this->list, $function);
		$position = 1;
		foreach ($this->list as $friend) {
			$friend->set_position_in_list($position++);
		}
		if ($order == 'asc') {
			$this->list = array_reverse($this->list, true);
		}
	}
	
	public function get_top_friends($number) {
		$top = array_slice($this->list, 0, $number, true);
		return array_map("process_friend_name", $top);
	}
}

/*
 * Info about a friend.
 */
class Friend {
	private $pic; // URL to profile picture (50px*50px max)
	private $name; // Full name
	private $status; // Amount of interaction with user / reason to remove
	private $profile; // Profile URL
	private $mutualFriendsCount; // Number of mutual friends
	private $position_in_list;

	/*
	 * @param entry Array containing the friend's name, picture URL (small) and profile URL
	 */
	function __construct($entry) {
		$this->name = $entry["name"];
		$this->pic = $entry["pic_square"];
		$this->status = new FriendStatus();
		$this->profile = $entry["profile_url"];
		$this->mutualFriends = array();
		$this->mutualFriendsCount = isset($entry["mutual_friends"]) ? $entry["mutual_friends"] : 0;
	}
	
	/**
	 * @brief Assign a friend
	 *
	 * @param id Facebook ID
	 */
	public function addFriend($id) {
		if (!array_key_exists($id, $this->mutualFriends)) {
			$this->mutualFriends[$id] = true;
			$this->mutualFriendsCount++;
		}
	}
	
	public function set_position_in_list($position) {
		$this->position_in_list = $position;
	}
	
	/**
	 * @brief Determine the number of mutual friends
	 *
	 * @return Number of mutual friends
	 */
	public function countMutualFriends() {
		return $this->mutualFriendsCount;
	}
		
	public function set_mutual_friends($number) {
		$this->mutualFriendsCount = $number;
	}
	
	public function dbDump($db, $id) {
		return $db->insertFbUser($id, $this->name, $this->pic);
	}

	/**
	 * @brief String representation.
	 */
	public function __toString() {
		return $this->name;
	}

	/**
	 * @brief User interface to the friend data.
	 *
	 * Represents a div containing the picture, name, status and a link to the user profile.
	 *
	 * @param id User id of the friend; it is not contained in this class to reduce data duplication on higher levels
	 *
	 */
	public function toHTML($id) {
		echo "<div  id=\"$id\" class=\"friend_data_container\"><div class=\"friend_position\">" .
			"<span class=\"position_number\">$this->position_in_list</span></div>" .
			"<div class=\"friend\">\n" .
			"\t<img class=\"friend\" src=\"$this->pic\" />\n" .
			"\t<div class=\"friendText\">\n" .
			"\t\t<a href=\"$this->profile\" class=\"friend\" target=\"_blank\">$this->name</a>\n" .
			"\t\t<p class=\"status\">" . $this->status . "</p>\n" .
			"\t\t<a class=\"mutual_friends\" href=\"https://www.facebook.com/browse/?type=mutual_friends&uid=$id\" target=\"_blank\">" . $this->countMutualFriends() . " mutual friends</a>" .
			"\t</div>\n" .
			"\t\t<a class=\"uibutton icon next score\" onclick=\"requestDetails('$id')\">Score: " . $this->status->getScore() . "</a>\n" .
			"<br></div>\n</div>";
	}

	/**
	 * @brief Appends a text to the friend status.
	 *
	 * @param name Text to be appended
	 * @param score Individual entry score
	 * @param date How recent this interaction took place - in seconds since std. Unix
	 */
	public function updateStatus($name, $score, $date) {
		if ($name == "Shared Photos") echo "--> in the Friend class: $name<br>";
		$this->status->add($name, $date, $score);
	}

	/**
	 * @brief The computed interaction index
	 *
	 * @return Score
	 */
	public function statusWeight() {
		return $this->status->getScore();
	}
	
	/**
	 * @brief Access a friend's status
	 *
	 * @return FriendStatus object
	 */
	public function getStatus() {
		return $this->status;
	}
}

/**
 * Info about a friend's status
 */
class FriendStatus {
	private $list;
	private $score;
	
	/**
	 * @brief Initialize the data structures
	 */
	function __construct() {
		$this->list = array();
		$this->score = 0;	
	}
	
	public function add($key, $date, $score) {
		if (!array_key_exists($key, $this->list)) {
			$this->list[$key] = array();
		}
		array_push($this->list[$key], new StatusDetail($date, $score));
		$this->score += $score;
		
		if ($key == "Shared Photos") {
			print_r($this->list);
		}
	}
	
	public function normalize_mailbox($interval) {
		global $inbox_key_name;
		global $outbox_key_name;
		global $score_percentage;
		
		$inbox_list = $this->list[$inbox_key_name];
		$outbox_list = $this->list[$outbox_key_name];
		if ($inbox_list != null) {
			foreach ($inbox_list as $inbox_message) {
				$date = $inbox_message->getDate();
				if (!$this->find_mailbox_reply($outbox_list, $date, $interval)) {
					$inbox_message->setScore(cut_score($inbox_message->getScore(), $score_percentage[$inbox_key_name]));
				}
			}
		}
		if ($outbox_list != null) {
			foreach ($outbox_list as $outbox_message) {
				$date = $outbox_message->getDate();
				if (!$this->find_mailbox_reply($inbox_list, $date, $interval)) {
					$outbox_message->setScore(cut_score($outbox_message->getScore(), $score_percentage[$outbox_key_name]));
				}
			}
		}
	}
	
	/**
	 * @brief Find a reply in a specified time window
	 *
	 * @param list Array of messages to search in
	 * @param date Timestamp of the message
	 * @param interval Time in seconds of the window
	 * @return true or false if found or not
	 */
	function find_mailbox_reply($list, $date, $interval) {
		if ($list == null) {
			return false;
		}
		$interval = intval(round($interval / 2));
		$upper_limit = $date + $interval;
		$lower_limit = $date - $interval;
		foreach ($list as $reply) {
			$reply_date = $reply->getDate();
			if ($reply_date <= $upper_limit && $reply_date >= $lower_limit) {
				return true;
			}
		}
		return false;
	}
	
	public function normalize_wall_posts() {
		global $home_key_name;
		global $score_percentage;
		$wall_posts = $this->list[$home_key_name];
		if ($wall_posts == null) {
			return;
		}
		foreach ($wall_posts as $wall_post) {
			$wall_post->setScore(cut_score($wall_post->getScore(), $score_percentage[$home_key_name]));
		}
	}
	
	public function normalize_tags() {
		global $tag_key_name;
		global $score_percentage;
		$tags = $this->list[$tag_key_name];
		if ($tags == null) {
			return;
		}
		foreach ($tags as $tag) {
			$tag->setScore(cut_score($tag->getScore(), $score_percentage[$tag_key_name]));
		}
	}
	
	public function compute_friend_score() {
		$this->score = 0;
		foreach ($this->list as $type_list) {
			foreach ($type_list as $entry) {
				$this->score += $entry->getScore();
			}
		}
	}
	
	public function getList() {
		return $this->list;
	}
	
	public function getByKey($key) {
		return $this->list[$key];
	}
	
	public function getScore() {
		return $this->score;
	}

	/**
	 * @brief String with all keys containing details
	 *
	 * @return String representation of status
	 */
	public function __toString() {
		$value = "";
		
		foreach ($this->list as $text => $details) {
			if (count($details) > 0) {
				$value = $value . ucwords($text) . ', ';
			}
		}
		if (strlen($value)) {
			$value = substr($value, 0, -2);
		} else {
			$value = "No interaction";
		}

		return $value;
	}
	
	/**
	 * @brief Save all data to the database
	 *
	 * @param db Database link
	 * @param id Friend relation ID
	 */
	public function dbDump($db, $id) {
		foreach ($this->list as $type => $details) {
			foreach($details as $detail) {
				if ($type == "Shared Photos") echo "ID: $id<br>";
				$db->insertStatus($id, $type, $detail->getDate());
			}
		}
	}
	
	public function fromDatabase($db, $id, $since) {
		$diffTime = time() - $since; // Maximum time interval
		$result = $db->getStatus($id, $since);
		
		while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
			$type = $row["type"];
			$date = $row["date"];
			$score = computeScore($date, $since, $diffTime);
			$this->add($type, $date, $score);
		}
	}
}

/**
 * Detailed info about a status entry
 */
class StatusDetail {
	public $date; // seconds of standard Unix time
	public $score;
	
	function __construct($date, $score) {
		$this->date = $date;
		$this->score = $score;
	}
	
	public function getDate() {
		return intval($this->date);
	}
	
	public function getScore() {
		return $this->score;
	}
	
	public function setScore($score) {
		$this->score = $score;
	}
}
?>