<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
<head>
<?php
// require_once('config.php');
require_once('friend_list.php');
require_once('utils.php');

echo "<style>\n";
include('style.css');
echo "</style>\n";
?>
<div id="fb-root"></div>
<script type="text/javascript">
window.fbAsyncInit = function() {
        FB.init({appId: '139006766174656', status: true, cookie: true, xfbml: true});
    };
    
    //Load the SDK asynchronously
    (function() {
        var e = document.createElement('script'); e.async = true;
        e.src = document.location.protocol +
          '//connect.facebook.net/en_US/all.js';
        document.getElementById('fb-root').appendChild(e);
    }());

function load()
{
	var frame = document.getElementById('frame');
	var intro = document.getElementById('intro');
	var pagesize = document.getElementById('pagesize');
	pagesize = pagesize.options[pagesize.selectedIndex].value;
	
	var entryHeight = 72;
	var listHeight = pagesize * entryHeight * 2;
	var frameHeight = intro.offsetHeight + listHeight;
	
	frame.height = listHeight;
	FB.Canvas.setSize({ width: 700, height: frameHeight });
}

/**
 * @brief Common form data
 *
 * @return Link to list page with basic data
 */
function createBasicLink() {
	var order = document.getElementById('order');
	order = order.options[order.selectedIndex].value;
	var pagesize = document.getElementById('pagesize');
	pagesize = pagesize.options[pagesize.selectedIndex].value;
	return "list.php?pagesize=" + pagesize + "&order=" + order;
}

function refreshList() {
	var frame = document.getElementById('frame');
	var since = document.getElementById('since');
	since = since.options[since.selectedIndex].value;
	frame.src = createBasicLink() + "&refresh=true" + "&since=" + since;
}

function updateData() {
	var frame = document.getElementById('frame');
	var since = document.getElementById('since');
	since = since.options[since.selectedIndex].value;
	frame.src = createBasicLink() + "&refresh=true" + "&since=" + since + "&update=true";
}

function changePageSize() {
	var frame = document.getElementById('frame');
	frame.src = createBasicLink();
}

function changeOrder() {
	var frame = document.getElementById('frame');
	frame.src = createBasicLink() + "&resort=true";
}
</script>
</head>
<body>
<div id="intro">
<div>
<?php
session_start();
require_once('facebook/src/facebook.php');
require_once('db.php');

// App information
$app_secret = '7341d578889ab343d851284665976ea8';
$app_id = '139006766174656';
$app_addr = 'http://apps.facebook.com/unvitation/';

// Part of redirect script
$js = "<script type='text/javascript'>top.location.href =";

// Facebook API wrapper
$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
$user = $fb->getUser();

// User has to log in
if (!$user) {
    $scope = 'email,offline_access,read_mailbox,read_stream,friends_photo_video_tags,user_photo_video_tags';
    $params = array('scope' => $scope, 'redirect_uri' => $app_addr);
    $login = $fb->getLoginUrl($params);
	echo $js . "'$login';</script>";
    exit;
}
$_SESSION["fb"] = $fb;

// Database stuff
$db = new dbWrapper();
$dbUserInfo = $db->getUser($user);
if ($dbUserInfo == null) {
	$user_info = $fb->api("/$user?fields=name");
	$name = $user_info['name'];
	
	// Updating the database with our new user
	if (!$db->insertUser($user, $name)) {
		throw new Exception("Could not add new user in the database");
	}
} else {
	$name = $dbUserInfo["name"];
}

echo "<img style=\"float:left\" src=\"http://graph.facebook.com/$user/picture\"><br>";
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
<p>For now, this application will list your friends and indicate how they interracted with you (eg: inbox, pokes, photo tags, etc.). The list is sorted in descending order by amount
of interraction.
</p>
<p>Move the cursor over a friend info-box to get a link to his profile.</p>

<input id="refresh" type="button" value="Update now" onclick="updateData()">

<span style="float:right;">
Since:
<select id="since" onchange="refreshList()">
	<option value="-1week">Last week</>
	<option value="-1month">Last month</>
	<option value="-3month">Last 3 months</>
	<option value="-6month">Last half year</>
	<option value="-1year">Last year</>
	<option value="-2year">Last 2 years</>
<select>
Page size:
<select id="pagesize" onchange="changePageSize()">
	<option value="10">10</option>
	<option value="25">25</option>
	<option value="50">50</option>
	<option value="100">100</option>
</select>
Order:
<select id="order" onchange="changeOrder()">
	<option value="desc">Descending</>
	<option value="asc">Ascending</>
</select>
</span>
</div>

<iframe id="frame" src="list.php" width="700" height = "500" frameborder="0" scrolling="no" onload="load()">
	Your browser doesn't support iframes
</iframe>
</body>
</html>