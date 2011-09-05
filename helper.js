var details = null; // Table with status details
var loadingGif = '<img class=\"loading\" src=\"loading.gif\" />'; // Image to show when loading
var imageHeight = 32; // Height in pixels of the loading animation
var top_friends_url = 'top_friends.php';
var app_address = 'http://apps.facebook.com/friend-sieve';
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

/*window.fbAsyncInit = function() {
        FB.init({appId: '139006766174656', status: true, cookie: true, xfbml: true});
		alert('Loaded');
    };
    
//Load the SDK asynchronously
function loadSDK() {
    var e = document.createElement('script'); e.async = true;
    e.src = document.location.protocol + '//connect.facebook.net/en_US/all.js';
    document.getElementById('fb-root').appendChild(e);
};*/

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