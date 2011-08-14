<?php
	require_once('friend_list.php');
	session_start();
	echo "<style>";
	require("style.css");
	echo "</style>";
	
	if (!isset($_SESSION["friendlist"]) || !isset($_GET["id"])) {
		echo "No data for YOU!";
	} else {
		$list = $_SESSION["friendlist"];
		$id = $_GET["id"];
		$status = $list->getStatus($id);
		if ($status != null) {
			formatStatus($status);
		} else {
			echo "Friend ID not in list";
		}
	}
	
	function formatStatus($status) {
		echo 	"\t<tr>\n" . 
					"\t\t<th>Type</td>\n" .
					"\t\t<th>Date</td>\n" .
					"\t\t<th>Score</td>\n" .
				"\t<tr>\n";

		foreach ($status->getList() as $name => $type) {
			foreach ($type as $entry) {
				$date = $entry->date;
				if ($date >= strtotime("today")) {
					$date = "Today";
				} else if ($date >= strtotime("yesterday") && $date < strtotime("today")) {
					$date = "Yesterday";
				} else {
					$date = date("j/m/Y", $entry->date);
				}
				echo "\t<tr>\n" .
						"\t\t<td>$name</td>\n" .
						"\t\t<td>$date</td>\n" .
						"\t\t<td>$entry->score</td>\n" .
					"\t<tr>\n";
			}
		}
		
		//echo "</table>\n";
	}
?>