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
 * @brief Sorting in ascending order
 */
function helperSortAsc($a, $b) {
	return helperSort($a, $b, -1);
}

/**
 * @brief Sorting in descending order
 */
function helperSortDesc($a, $b) {
	return helperSort($a, $b, 1);
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
		if ($this->list == null) {
			$this->getFriendList($fb);
		}
		$this->timeLimit = $since;
		
		// List of graph call to make
		$graphCalls = array('inbox', 'tagged', 'photos', 'pokes', 'feed', 'home');
		$res = batchRequest($fb, $graphCalls, $since);

		foreach ($res as $key => $value) {
			$decoded = json_decode($value["body"], true);
			$decoded = $decoded["data"];
			crossCheck($this, checkInterraction($decoded), $graphCalls[$key]);
		}
	}
	
	public function dbDump($db, $fb) {
		$user = $fb->getUser();
		
		foreach ($this->list as $friendID => $friend) {
			$friend->dbDump($db, $friendID);
			$db->insertFriend($user, $friendID);
			$friend->getStatus()->dbDump($db, $db->getID());
		}
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
								"profile_url" => "http://www.facebook.com/profile.php?id=$fb_id");
			$friend = new Friend($entry);
			$status = $friend->getStatus();
			$status->fromDatabase($db, $row["id"], $since);
			$this->list[$fb_id] = $friend;
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
	 * @brief String representation of data in this class.
	 *
	 * @return A string of friend names, with HTML breaks for newlines
	 */
	public function __toString() {
		$value = '';
		foreach ($this->list as $friend) {
			$value = $value . $friend . '<br>';
		}
		return $value;
	}

	/**
	 * @brief HTML code representing the friends.
	 *
	 * @param pagesize Number of elements to represent on each page
	 * @param page Page number (starting from 1)
	 * @return String representation of the code
	 */
	public function toHTML($pagesize, $page) {
		$value = '';
		foreach (array_slice($this->list, $pagesize * ($page - 1), $pagesize, true) as $uid => $friend) {
			$value = $value . $friend->toHTML($uid);
		}
		return $value;
	}

	public function getList() {
		return $this->list;
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
	 * @param $order Order of elements: true - descending, false - ascending
	 */
	public function sort($order) {
		uasort($this->list, $order ? "helperSortDesc" : "helperSortAsc");
	}
}

/*
 * Info about a friend.
 */
class Friend {
	private $pic; // URL to profile picture (50px*50px max)
	public $name; // Full name
	private $status; // Amount of interaction with user / reason to remove
	private $profile; // Profile URL

	/*
	 * @param entry Array containing the friend's name, picture URL (small) and profile URL
	 */
	function __construct($entry) {
		$this->name = $entry["name"];
		$this->pic = $entry["pic_square"];
		$this->status = new FriendStatus();
		$this->profile = $entry["profile_url"];
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
	 * @return String representation of the HTML tags
	 *
	 * TODO: Maybe just echo everything directly, without an intermediary string
	 */
	public function toHTML($id) {
		$value = "<div id=\"$id\" class=\"friend\" onmouseover=\"linkHover('$id', true)\" onmouseout=\"linkHover('$id', false)\">\n" .
			"\t<img class=\"friend\" src=\"$this->pic\" />\n" .
			"\t<div class=\"friendText\">\n" .
			"\t\t<p class=\"name\">$this->name</p>\n" .
			"\t\t<p class=\"status\">" . $this->status . "</p>\n" .
			"\t\t<a class=\"score\" onclick=\"requestDetails('$id')\">Score: " . $this->status->getScore() . "</a>\n" .
			"\t</div>\n" .
			"\t<a id=\"$id" . "profile_link\" class=\"friend\" href=\"$this->profile\" target=\"_blank\">Go to profile</a>\n" .
			"</div>\n";
		return $value;
	}

	/**
	 * @brief Appends a text to the friend status.
	 *
	 * @param name Text to be appended
	 * @param score Individual entry score
	 * @param date How recent this interraction took place - in seconds since std. Unix
	 */
	public function updateStatus($name, $score, $date) {
		$this->status->add($name, $date, $score);
	}

	/**
	 * @brief The computed interraction index
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
	}
	
	public function getList() {
		return $this->list;
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
			$value = "Undefined";
		}

		return $value;
	}
	
	public function dbDump($db, $id) {
		foreach ($this->list as $type => $details) {
			foreach($details as $detail) {
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
			if ($type == "home") {
				$score = round($score / 10);
			}
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
		return $this->date;
	}
	
	public function getScore() {
		return $this->score;
	}
}
?>