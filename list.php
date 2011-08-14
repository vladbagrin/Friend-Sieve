<script type="text/javascript">
function linkHover(id, show) {
	var e = document.getElementById(id + "profile_link");
	show ? e.setAttribute('style', 'visibility: visible') : e.removeAttribute('style');
}

// Table with status details
var details = null;
var previousHeight = null;

/**
 * @brief Sends AJAX request for friend details
 *
 * @param id Friend Facebook ID
 */
function requestDetails(id) {
	var xmlhttp;
	
	// Remove previously set table
	if (details != null) {
		var parent = details.parentNode;
		parent.removeChild(details.previousSibling); // Remove <br>
		parent.removeChild(details.previousSibling); // Remove <br>
		parent.removeChild(details);
		parent.removeAttribute('style');
		details = null;
		
		if (parent == document.getElementById(id)) {
			return;
		}
	}
	
	if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp=new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}
	
	// Setting up the callback
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			try {
				details = document.createElement('table');
				details.innerHTML = xmlhttp.responseText;
				
				var e = document.getElementById(id);
				e.appendChild(document.createElement('br'));
				e.appendChild(document.createElement('br'));
				e.appendChild(details);
				
				var height = e.offsetHeight;
				var tableHeight = details.offsetHeight;
				
				e.setAttribute('style', 'height: ' + (height + tableHeight));
				
			} catch (e) {
				alert(e);
			}
		}
	};
	
	// Send the request
	xmlhttp.open('GET', 'status_details.php?id=' + id, true);
	xmlhttp.send();
}
</script>
<?php
require_once('facebook/src/facebook.php');
require_once('friend_list.php');
session_start();
// require_once('config.php');
require_once('utils.php');
require_once('db.php');

echo "<style>\n";
include('style.css');
echo "</style>\n";

if (!isset($_SESSION["fb"])) {
	throw new Exception("User not logged in");
}
$fb = $_SESSION["fb"];
$user = $fb->getUser();

$db = new dbWrapper();
$storedData = $db->getUser($user);

// Update now if this is the first time
if ($storedData["last_update"] == 0 || isset($_GET["update"]) && $_GET["update"] == "true") {
	$list = new FriendList($fb);
	$list->fromFacebook($fb, $storedData["last_update"]);
	$list->dbDump($db, $fb);
	$db->updateTime($user);
}

if (!isset($_SESSION["friendlist"]) || isset($_GET["refresh"]) && $_GET["refresh"] == "true") {

	// Select since when to check interraction
	$since = "-1week";
	if (isset($_GET["since"])) {
		$since = $_GET["since"];
	}
	$since = strtotime($since);

	$list = new FriendList(null);
	$list->fromdatabase($db, $fb, $since);
	
	// Select order of elements for sorting
	if (!isset($_GET["order"]) || $_GET["order"] == "desc") {
		$list->sort(true);
	} else {
		$list->sort(false);
	}

	// Save as session variable
	$_SESSION["friendlist"] = $list;
} else {
	$list = $_SESSION["friendlist"];
}

/*if (isset($_GET["refresh"]) && $_GET["refresh"] == "true") {
	
	// OOP wrapper for friend list
	$list = new FriendList($fb);
	
	$list->fromFacebook($fb, $since);

	// Select order of elements for sorting
	if (!isset($_GET["order"]) || $_GET["order"] == "desc") {
		$list->sort(true);
	} else {
		$list->sort(false);
	}

	// Save as session variable
	$_SESSION["friendlist"] = $list;
} else {
	$list = $_SESSION["friendlist"];
}*/

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
if (isset($_GET["resort"]) && $_GET["resort"] == "true" && isset($_GET["order"])) {
	$list->sort($_GET["order"] == "desc"); // LOL
}

// Generate page links
genPageLinks($pagesize, ceil($list->length() / $pagesize), $page);

// List in nice HTML
echo $list->toHTML($pagesize, $page);
?>