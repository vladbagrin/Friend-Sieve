<?php
require_once('facebook/src/facebook.php');
require_once('friend_list.php');
require_once('utils.php');
require_once('db.php');
session_start();

$fb = logged_in_check();
$list = prepare_friend_data($fb);

// Get the pagesize and page number
if (isset($_GET["pagesize"])) {
	$pagesize = $_GET["pagesize"];
} else {
	$pagesize = 10;
}
if (isset($_GET["page"])) {
	$page = $_GET["page"];
} else {
	$page = 1;
}

// Sort again if requested
if (isset($_GET["resort"]) && isset($_GET["order"]) && isset($_GET["by"])) {
	$list->sort($_GET["order"], $_GET["by"]);
}

// Filter by search terms
if (isset($_GET["filter"])) {
	$search_terms = rawurldecode($_GET["filter"]);
	filter_friend_list($list, $search_terms);
}

// Generate page links
genPageLinks($pagesize, ceil($list->length() / $pagesize), $page);

// List in nice HTML
$list->toHTML($pagesize, $page);

// Send script to call the feed dialog
if ($list->is_fresh()) {
	echo "<script type=\"text/javascript\">post_to_wall();</script>\n";
}
?>
