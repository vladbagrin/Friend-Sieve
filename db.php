<?php

class dbWrapper {
	private $link;
	private $lastInsertID = null;

	public function __construct() {
		$this->connect();
	}
	
	public function connect() {
	    $this->link = mysql_connect('127.0.0.1', 'createit_vlad', 'vlad@123') or die(mysql_error());
		if (!$this->link) {
			throw new Exception('Could not connect to the database');
		}
		$database_select_result = mysql_select_db('createit_unfriend', $this->link) or die(mysql_error());
		if (!$database_select_result) {
			throw new Exception('Could not select database');
		}
	}
	
	public function close() {
		mysql_close($this->link);
		$this->link = null;
		$this->lastInsertID = null;
	}
	
	/**
	 * @brief Enters Facebook user info
	 *
	 * @param id Facebook ID
	 * @param name Full name
	 * @param picture Profile picture URL
	 * @return TRUE on success and FALSE otherwise
	 */
	function insertFbUser($id, $name, $picture) {
		$sql = "INSERT INTO fb_user (fb_id, name, picture_url) values ($id, \"$name\", \"$picture\") " .
				"ON DUPLICATE KEY UPDATE name=VALUES(name), picture_url=VALUES(picture_url)";
		return mysql_query($sql, $this->link);
	}
	
	/**
	 * @brief Retrieves Facebook friends
	 *
	 * @param id Facebook ID
	 * @return MySQL resource
	 */
	function getFriends($id) {
		$sql = "SELECT friend.id, friend.mutual_friends, fb_user.fb_id, fb_user.name, fb_user.picture_url " .
				"FROM friend " .
				"JOIN fb_user " .
				"ON fb_user.fb_id=friend.facebook_id " .
				"WHERE friend.user_id=$id AND friend.removed=0";
		return mysql_query($sql, $this->link);
	}

	/**
	 * @brief Insert new app user into the DB
	 *
	 * @param id Facebook ID
	 * @param name Full name
	 * @return TRUE on success and FALSE otherwise
	 */
	function insertUser($id, $name) {
		$sql = "INSERT INTO user (fb_id, name, last_update) VALUES ($id, \"$name\", 0)";
		return mysql_query($sql, $this->link);
	}

	/**
	 * @brief See if a user is already registered with this app
	 *
	 * @param id Facebook ID
	 * @return User data
	 */
	function getUser($id) {
		$sql = "SELECT * FROM user WHERE fb_id=$id";
		return mysql_fetch_array(mysql_query($sql, $this->link), MYSQL_ASSOC);
	}

	/**
	 * @brief Insert friend relation in DB
	 *
	 * @param id User ID
	 * @param fbId Friend ID
	 * @param mutualFriends Number of mutual friends
	 *
	 * @return TRUE on success and FALSE otherwise
	 */
	function insertFriend($id, $fbId, $mutualFriends) {
	    $resource = mysql_query("SELECT * FROM friend WHERE user_id='$id' AND facebook_id='$fbId'", $this->link) or die(mysql_error());
		$row = mysql_fetch_assoc($resource);
		if ($row != null) {
			$result = false;
			$relation_id = $row["id"];
			if ($row["mutual_friends"] != $mutualFriends) {
				$sql = "UPDATE friend SET mutual_friends='$mutualFriends' WHERE id='$relation_id'";
				$result = mysql_query($sql, $this->link);
			}
			$this->lastInsertID = $relation_id;
			return $result;
		}
		$sql = "INSERT INTO friend (user_id, facebook_id, mutual_friends) values ('$id', '$fbId', '$mutualFriends')";
		$result = mysql_query($sql, $this->link);
		$this->lastInsertID = mysql_insert_id($this->link);
		return $result;
	}
	
	/**
	 * @brief Get the ID generated in the last query
	 *
	 * @return lastInsertID
	 */
	public function getID() {
		return $this->lastInsertID;
	}
	
	/**
	 * @brief Insert friend status in DB
	 *
	 * @param id Friend relation ID
	 * @param type Collection source name
	 * @param date Date the entry was added in Facebook
	 *
	 * @return TRUE on success and FALSE otherwise
	 */
	public function insertStatus($id, $type, $date) {
		$sql = "INSERT INTO status (friend_relation_id, type, date) values ($id, \"$type\", $date)";
		$result = mysql_query($sql, $this->link);
		if ($result != false) {
			$this->lastInsertID = mysql_insert_id($this->link);
		}
		return $result;
	}
	
	/**
	 * @brief Get friend status from the database
	 *
	 * @param id Friend relation ID
	 * @param since Minimal timestamp
	 *
	 * @return A MySQL PHP resource
	 */
	public function getStatus($id, $since) {
		$sql = "SELECT type, date FROM status WHERE friend_relation_id=$id AND date > $since";
		return mysql_query($sql, $this->link);
	}
	
	/**
	 * @brief Get friend status based on user ID and friend Facebook ID
	 *
	 * @param user_id User ID from Facebook
	 * @param friend_id Friend Facebook ID
	 * @param since Minimum timestamp
	 *
	 * @return MySQL PHP resource
	 */
	public function getStatusRaw($user_id, $friend_id, $since) {
		$sql = "SELECT type, date FROM status WHERE friend_relation_id in (" .
				"SELECT id FROM friend WHERE user_id=$user_id AND facebook_id=$friend_id) " .
				"AND date > $since ORDER BY date DESC";
		return mysql_query($sql, $this->link);
	}
	
	/**
	 * @brief Set updated time to now
	 *
	 * @param User acebook ID
	 * @return Success status
	 */
	public function updateTime($id) {
		$sql = "UPDATE user SET last_update=" . time() . " WHERE fb_id=$id";
		return mysql_query($sql, $this->link);
	}
	
	/**
	 * @brief Mark friends that were unfriended
	 *
	 * @param ID of the user
	 * @param whitelist Friend IDs that are still friends
	 * @return The result of the query
	 */
	public function blacklistFriends($user_id, $whitelist) {
		$sql = "UPDATE friend SET removed=1 WHERE user_id=$user_id AND facebook_id NOT IN $whitelist";
		return mysql_query($sql, $this->link);
	}
}
?>