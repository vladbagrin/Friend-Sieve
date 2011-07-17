<?php
/**
 * @brief Helper sort function
 */
function helperSort($a, $b) {
	$na = $a->statusWeight();
	$nb = $b->statusWeight();
		if ($na == $nb) {
		return 0;
	}
	return ($na < $nb) ? 1 : -1;
}

/**
 * A list of all the user's friends.
 */
class FriendList {
	private $list; // Friend info array

	/**
	 * @param fb Facebook API wrapper
	 */
	function __construct($fb) {
		$this->list = array();
		$this->uids = array();

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
	 * @return String representation of the code
	 */
	public function toHTML() {
		$value = '';
		foreach ($this->list as $uid => $friend) {
			$value = $value . $friend->toHTML($uid);
		}
		return $value;
	}

	public function getList() {
		return $this->list;
	}

	/**
	 * @brief Sort by number of status items.
	 */
	public function sort() {
		uasort($this->list, "helperSort");
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
		$this->status = array();
		$this->profile = $entry["profile_url"];
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
		//$css = "CSSNames";
		$value = "<div id=\"$id\" class=\"friend\" onmouseover=\"linkHover('$id', true)\" onmouseout=\"linkHover('$id', false)\">\n" .
			"\t<img class=\"friend\" src=\"$this->pic\" />\n" .
			"\t<div class=\"friendText\">\n" .
			"\t\t<p class=\"name\">$this->name</p>\n" .
			"\t\t<p class=\"status\">" . $this->statusToString() . "</p>\n" .
			"\t</div>\n" .
			"\t<a class=\"friend\" href=\"$this->profile\" target=\"_blank\">Go to profile</a>\n" .
			"</div>\n";
		return $value;
	}

	private function statusToString() {
		$value = "";
		
		foreach ($this->status as $text) {
			$value = $value . $text . ', ';
		}
		if (strlen($value)) {
			$value = substr($value, 0, -2);
		} else {
			$value = "Undefined";
		}

		return $value;
	}

	/**
	 * @brief Appends a text to the friend status.
	 *
	 * @param message Text to be appended
	 */
	public function updateStatus($message) {
		array_push($this->status, $message);
	}

	/**
	 * @brief Number of status entries.
	 *
	 * @return Length of status array
	 */
	public function statusWeight() {
		return count($this->status);
	}
}
?>
