<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html>
	<head>
	<script type="text/javascript">
		var _gaq = _gaq || [];
		_gaq.push(['_setAccount', 'UA-25518956-1']);
		_gaq.push(['_trackPageview']);

		(function() {
		var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
		ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
		var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
		})();
	</script>
<?php
require_once("utils.php");

if (isset($_POST["text"])) {
	$fb = logged_in_check();
	$user = $fb->getUser();
	$user_info = $fb->api("/$user?fields=name");
	$name = $user_info['name'];
	$date = date("r", time());
	
	$to = "vlad.bagrin@gmail.com";
	$subject = "Friend Sieve - Feedback";
	$message = "Post from: $name\n" .
			"Facebook ID: $user\n" .
			"Date: $date\n\n" .
			$_POST["text"];
	$headers = 'From: webmaster@createit.ro' . "\r\n" .
				'Reply-To: webmaster@createit.ro' . "\r\n" .
				'X-Mailer: PHP/' . phpversion();
	
	mail($to, $subject, $message, $headers);
?>
		<script type="text/javascript">top.location.href = "<?php echo $app_addr; ?>"</script>
	</head>
<?php
} else {
?>
	<link rel="stylesheet" type="text/css" href="facebook_style/fb-buttons.css" />
	<link rel="stylesheet" type="text/css" href="style.css" />
	</head>
	<body>
		<div style="width:450px;margin-left:auto;margin-right:auto">
			<p id="introText">
			Do you agree with the scores? What would you like changed? If you have any suggestions, please tell us. Your feedback is valuable.
			</p>
			<form action="feedback_form.php" method="post">
				<textarea name="text" rows="10" style="width:450px"></textarea>
				<div class="uibutton-group" style="float:right">
					<a class="uibutton icon prev" href="javascript:top.location.href='<?php echo $app_addr; ?>'">Back to app</a>
					<input type="submit" class="uibutton confirm" value="Submit" />
				</div>
			</form>
		</div>
	</body>
<?php } ?>
</html>