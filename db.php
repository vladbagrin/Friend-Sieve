<?php

class dbWrapper {
	private $link;
	private $lastInsertID = null;

	public function __construct() {
		$this->link = mysql_connect('localhost', 'createit_vlad', 'vlad@123');
		if (!$this->link) {
			throw new Exception('Could not connect to the database');
		}
		if (!mysql_select_db('createit_unfriend', $this->link)) {
			throw new Exception('Could not select database');
		}
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
		$sql = "SELECT friend.id, fb_user.fb_id, fb_user.name, fb_user.picture_url " .
				"FROM friend " .
				"JOIN fb_user " .
				"ON fb_user.fb_id=friend.facebook_id " .
				"WHERE friend.user_id=$id";
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
	 *
	 * @return TRUE on success and FALSE otherwise
	 */
	function insertFriend($id, $fbId) {
		if (mysql_fetch_assoc(mysql_query("SELECT * FROM friend WHERE user_id=$id AND facebook_id=$fbId")) != null) {
			return FALSE; // Already exists
		}
		$sql = "INSERT INTO friend (user_id, facebook_id) values ($id, $fbId)";
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
		$this->lastInsertID = mysql_insert_id($this->link);
		return $result;
	}
	
	public function getStatus($id, $since) {
		$sql = "SELECT type, date FROM status WHERE friend_relation_id=$id AND date > $since";
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
}
?>