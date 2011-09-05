<?php
require_once('facebook/src/facebook.php');
require_once("utils.php");
require_once("friend_list.php");
session_start();

$count = $_GET["count"];
$fb = logged_in_check();
$list = prepare_friend_data($fb);
$top = $list->get_top_friends($count);
$i = 1;
$value = "";
foreach ($top as $name) {
	$value = $value . "$i. $name<center></center>"; // Line break hack
	$i++;
}
echo $value;
?>