<?php
require_once('facebook/src/facebook.php');

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
?>
