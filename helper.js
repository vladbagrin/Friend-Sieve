var details = null; // Table with status details
var loadingGif = '<img class=\"loading\" src=\"loading.gif\" />'; // Image to show when loading
var imageHeight = 32; // Height in pixels of the loading animation
var top_friends_url = 'top_friends.php';
var app_address = 'https://apps.facebook.com/friend-sieve-devel/';
var app_id = '152039924883298';
var date_string_map = {
	'-1week': 'last week',
	'-1month': 'last month',
	'-3month': 'last 3 months',
	'-6month': 'last half year',
	'-1year': 'last year',
	'-2year': 'last 2 years'
};

// ID for common elements
var sinceElementID = 'since';

function post_to_wall() {
	var called_before = false; // Another hack - callback being called twice!
	var count = 5; // 'tis what I chose
	var since = document.getElementById('since');
	since = since.options[since.selectedIndex].value;
	send_top_friends_request(count, since, function (data) {
		if (called_before) {
			return;
		} else {
			called_before = true;
		}
		var desc = 'Since ' + date_string_map[since] + ', my top ' + count + ' friends by interaction are:' + 
			'<center></center>' + data;
		FB.ui({
			method: 'feed',
			name: 'Friend Sieve',
			link: app_address,
			caption: ' ',
			picture: 'https://www.createit.ro/unvitation/pic_large.png',
			description: desc,
			actions: [
				{
					name: 'Find out yours',
					link: app_address
				}
			]
		},
		function(response) {
			// Duly noted
		});
	});
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
	var since = document.getElementById('since');
	since = since.options[since.selectedIndex].value;
	var by = document.getElementById('by');
	by = by.options[by.selectedIndex].value;
	return "list.php?pagesize=" + pagesize + "&order=" + order + "&since=" + since + "&by=" + by;
}

function refreshList() {
	sendListRequest(createBasicLink() + "&refresh=true");
}

function updateData() {
	sendListRequest(createBasicLink() + "&refresh=true" + "&update=true");
}

function changePageSize() {
	sendListRequest(createBasicLink());
}

function changeOrder() {
	sendListRequest(createBasicLink() + "&resort=true");
}

function changePage(page) {
	sendListRequest(createBasicLink() + "&page=" + page);
}

function getFilteredList() {
	var search_text = document.getElementById('search_text');
	search_text = encodeURIComponent(search_text.value);
	sendListRequest(createBasicLink() + "&filter=" + search_text + "&refresh=true");
}

function search_box_key_pressed(e) {
	if (e.keyCode == 13) {
		getFilteredList();
	}
}

function invokeScript(divid) {
	var scriptObj = divid.getElementsByTagName('script');
	var len = scriptObj.length;
	for(var i = 0; i < len; i++) {
		var scriptText = scriptObj[i].text;
		var scriptFile = scriptObj[i].src
		var scriptTag = document.createElement('script');
		if ((scriptFile != null) && (scriptFile != '')) {
			scriptTag.src = scriptFile;
		}
		scriptTag.text = scriptText;
		if (!document.getElementsByTagName('head')[0]) {
			document.createElement('head').appendChild(scriptTag)
		} else {
			document.getElementsByTagName('head')[0].appendChild(scriptTag);
		}
	}
}

function sendListRequest(url) {
	try {
		var xmlhttp;
		var list = document.getElementById('list');

		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}

		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200 && xmlhttp.responseText != '') {
				list.innerHTML = xmlhttp.responseText;
				invokeScript(list);
				FB.Canvas.setSize();
			}
		}

		// Send the request
		list.innerHTML = loadingGif;
		xmlhttp.open('GET', url, true);
		FB.Canvas.setSize();
		xmlhttp.send();
	} catch (e) {
		alert(e.message);
	}
}

function send_top_friends_request(count, since, callback) {
	try {
		var xmlhttp;
		var url = top_friends_url + '?since=' + since + '&count=' + count + '&refresh=true';

		if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
			xmlhttp = new XMLHttpRequest();
		} else { // code for IE6, IE5
			xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
		}

		xmlhttp.onreadystatechange = function () {
			if (xmlhttp.readyState == 4 && xmlhttp.status == 200 && xmlhttp.responseText != '') {
				callback(xmlhttp.responseText);
			}
		}

		// Send the request
		xmlhttp.open('GET', url, true);
		xmlhttp.send();
	} catch (e) {
		alert(e.message);
	}
}

function ajaxSwapper(id) {
	var container = document.getElementById(id); // Data container
	var animation = document.createElement('img'); // Animation graphic

	this.resize = function (element) {
		var containerHeight = container.offsetHeight;
		if (element == null) {
			container.removeAttribute('style');
			return;
		}
		var elementHeight = element.offsetHeight ? element.offsetHeight : element.height;

		container.setAttribute('style', 'height: ' + (containerHeight + elementHeight - animation.height) + 'px');
		FB.Canvas.setSize();
	}

	// Replace animation with actual data
	this.swap = function (data) {
		container.removeChild(animation);
		this.resize();
		container.appendChild(data);
		this.resize(data);
	}

	// Remove last 3 children
	this.clear = function () {

	}

	animation.setAttribute('class', 'loading');
	animation.src = 'loading.gif';
	animation.height = imageHeight;

	container.appendChild(animation);
	this.resize(animation);
}

function getSince() {
	var element = document.getElementById('since');
	return element.options[element.selectedIndex].value;
}

/**
* @brief Sends AJAX request for friend details
*
* @param id Friend Facebook ID
*/
function requestDetails(id) {
	var xmlhttp;
	var since = getSince();

	// Remove previously set table
	if (details != null) {
		var parent = details.parentNode;
		parent.removeChild(details);
		parent.removeAttribute('style');
		details = null;

		if (parent == document.getElementById(id)) {
			FB.Canvas.setSize();
			return;
		}
	}

	if (window.XMLHttpRequest) { // code for IE7+, Firefox, Chrome, Opera, Safari
		xmlhttp=new XMLHttpRequest();
	} else { // code for IE6, IE5
		xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
	}

	// Set loading animation swapper
	var animation = new ajaxSwapper(id);

	// Setting up the callback
	xmlhttp.onreadystatechange = function() {
		if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
			try {
				details = document.createElement('div');
				details.setAttribute('class', 'details');
				details.innerHTML = xmlhttp.responseText;			
				animation.swap(details);
			} catch (e) {
				alert(e);
			}
		}
	};

	// Send the request
	xmlhttp.open('GET', 'status_details.php?id=' + id + '&since=' + since, true);
	xmlhttp.send();
}

/**
* @brief Add loading animation for status details
*
* @param id ID to search entry div by
* @return Image DOM element
*/
function addLoadingImage(id) {
	var e = document.getElementById(id);
	e.appendChild(document.createElement('br'));
	e.appendChild(document.createElement('br'));
}
