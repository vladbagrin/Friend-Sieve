<script type="text/javascript">
function linkHover(id, show) {
		var e = document.getElementById(id);
		for (var c = e.firstChild; c && c.tagName != 'A'; c = c.nextSibling);
		if (c && show) {
			c.setAttribute('style', 'visibility: visible');
		}
		if (c && !show) {
			c.removeAttribute('style');
		}
	}
</script>
<?php
echo "<style>\n";
include('style.css');
echo "</style>\n";
require_once('config.php');
require_once('friend_list.php');
require_once('utils.php');

$user_info = $fb->api("/$user");

// Error. User probably not logged in
if (!$user_info) {
    exit(0);
}

echo "<div>\n";

if (array_key_exists('username', $user_info)) {
    $username = $user_info['username'];
    echo "<img style=\"float:left\" src=\"http://graph.facebook.com/$username/picture\"><br>";
}
$name = $user_info['name'];
echo "Hello, $name!<br>";

// Retrieving friends list
$fql = "SELECT uid2 FROM friend WHERE uid1=$user";
$friends = $fb->api(array(
    'method' => 'fql.query',
    'query' => $fql));

$f_num = count($friends); // Friends in list
echo "You have $f_num friends.<br>";
?>

</div>
<p>For now, this application will list your friends and indicate how they interracted with you lately (eg: inbox, pokes, photo tags, etc.). The list is sorted in descending order by amount of interraction.
</p>
<p>Move the cursor over a friend info-box to get a link to his profile.</p>
<?php

// OOP wrapper for friend list
$list = new FriendList($fb);
echo "<h4>List of friends:</h4>";

// List of graph call to make
$graphCalls = array('inbox', 'tagged', 'photos', 'pokes', 'feed', 'home');
$res = batchRequest($fb, $graphCalls);

foreach ($res as $key => $value) {
	$decoded = json_decode($value['body'])->data;
	crossCheck($list, shortCheckInterraction($decoded), ucwords($graphCalls[$key]));
}

// I'm not sure this sort works as expected.
$list->sort();

echo $list->toHTML();
?>
