<?php
	require 'facebook/src/facebook.php';
	
	// App information
	$app_secret = '7341d578889ab343d851284665976ea8';
	$app_id = '139006766174656';
	$app_addr = 'http://apps.facebook.com/unvitation/';
	
	// Part of redirect script
	$js = "<script type='text/javascript'>top.location.href =";
	
	// Facebook API wrapper
	$fb = new Facebook(array('appId' => $app_id, 'secret' => $app_secret));
	
	$user = $fb->getUser();
	if ($user) {
		$user_info = $fb->api("/$user");
		if (array_key_exists('username', $user_info)) {
			$username = $user_info['username'];
			echo "<img src=\"http://graph.facebook.com/$username/picture\"><br>";
		}
		$name = $user_info['name'];
		$email = $user_info['email'];
		echo "Hello, $name<br>Your email is: $email";
	} else {
	    $scope = 'email,offline_access';
	    $params = array('scope' => $scope, 'redirect_uri' => $app_addr);
	    $login = $fb->getLoginUrl($params);
        echo $js . "'$login';</script>";
        exit;
	}
?>
