<?php
	require_once('friend_list.php');
	require_once('db.php');
	require_once('utils.php');
	session_start();

	$startTime = microtime(TRUE) * 1000; // Time processing started
	$id = $_GET["id"];
	
	$fb = logged_in_check();
	$list = prepare_friend_data($fb);
	
	$status = $list->getStatus($id);
	if ($status != null) {
		formatStatus($status);
	} else {
		throw new Exception("Friend ID not in list");
	}
	
	$endTime = microtime(TRUE) * 1000;
	if (defined("debug")) {
		echo "Time to process: " . ($endTime - $startTime) . "ms";
	}
	
	/**
	 * @brief Makes a shorter status list for the HTML table
	 *
	 * @param status Status object
	 * @return Array with name => (date => (count, score))
	 */
	function tinyStatusList($status) {
		$dl = array(); // Shortened details representation - same date and types are concatenated
		foreach ($status->getList() as $name => $entries) {
			$dl[$name] = array();
			foreach ($entries as $entry) {
				$date = date("j/m/Y", $entry->date);
				if (array_key_exists($date, $dl[$name])) {
					$dl[$name][$date][0]++;
					$dl[$name][$date][1] += $entry->score;
				} else {
					$dl[$name][$date] = array(1, $entry->score);
				}
			}
		}
		return $dl;
	}
	
	function formatStatus($status) {
		echo "<br>";
		echo "<table class=\"details\">\n";

		$dl = tinyStatusList($status);
		$dl = unfoldStatus($dl);
		usort($dl, "dateSorter");
		foreach ($dl as $entry) {
			$date = $entry[1];
			$shownName = "";
			if ($entry[2] > 1) {
				$shownName = "<span style=\"color:#BDBDBD\">" . $entry[2] . "x</span>";
			}
			$shownName = $shownName . ucwords($entry[0]);
			echo "\t<tr>\n" .
					"\t\t<td>$shownName</td>\n" .
					"\t\t<td>$date</td>\n" .
					"\t\t<td>$entry[3]</td>\n" .
				"\t<tr>\n";
		}
		
		echo "</table>\n";
	}
	
	/**
	 * @brief Create a flat list of status entries
	 *
	 * For sorting. This better be f*****g temporary! Total anti-pattern...
	 *
	 * @param dl Nested list of status details
	 * @return Flat list containing (index => (0 => name, 1 => date, 2 => score))
	 */
	function unfoldStatus($dl) {
		$list = array();
		foreach ($dl as $name => $entries) {
			foreach ($entries as $date => $entry) {
				array_push($list, array($name, $date, $entry[0], $entry[1]));
			}
		}
		return $list;
	}
	
	/**
	 * @brief Order decision for the unfolded list
	 */
	function dateSorter($a, $b) {
		list($da, $ma, $ya) = explode("/", $a[1]);
		list($db, $mb, $yb) = explode("/", $b[1]);
		$da = intval($da);
		$ma = intval($ma);
		$ya = intval($ya);
		$db = intval($db);
		$mb = intval($mb);
		$yb = intval($yb);
		if ($ya == $yb) {
			if ($ma == $mb) {
				if ($da == $db) {
					return 0;
				}
				return $da < $db ? 1 : -1;
			}
			return $ma < $mb ? 1 : -1;
		}
		return $ya < $yb ? 1 : -1;
	}
?>