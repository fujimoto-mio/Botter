<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="ja" lang="ja">
<head>
<meta name="robots" content="index">
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>TRYPHP!　Twitter API 認証ユーザーのプロフィール画像と背景画像の差し換え POST account/update_profile_background_image POST account/update_profile_image</title>
</head>
<body>

<?php
#########################################
### ページ説明
?>

<h1>Twitter API 認証ユーザーのプロフィール画像と背景画像の差し換え。 POST account/update_profile_background_image POST account/update_profile_image</h1>
<!-- 説明ページurl -->
<h2><a href="http://www.tryphp.net/2012/01/31/phpapptwitter-account_update_profile_image/">→プロフィール画像更新の説明はこちら</a></h2>
<h2><a href="http://www.tryphp.net/2012/01/31/phpapptwitter-account_update_profile_background_image/">→プロフィール背景画像更新の説明はこちら</a></h2>



<hr/>



<?php
#########################################
### 取得したデータを展開

/**
* Update the users profile image, or profile background image using OAuth.
*
* Although this example uses your user token/secret, you can use
* the user token/secret of any user who has authorised your application.
*
* Instructions:
* Instructions:
* 1) If you don't have one already, create a Twitter application on
*      https://dev.twitter.com/apps
* 2) From the application details page copy the consumer key and consumer
*      secret into the place in this code marked with (YOUR_CONSUMER_KEY
*      and YOUR_CONSUMER_SECRET)
* 3) From the application details page copy the access token and access token
*      secret into the place in this code marked with (A_USER_TOKEN
*      and A_USER_SECRET)
* 4) Visit this page using your web browser.
*
* @author themattharris
*/

### 取得したデータを展開
if(!empty($_FILES)){
?>

	<div style="background-color:#f8f8f8;margin:20px; padding:20px; border:solid #cccccc 1px;">

	<!-- // =========================== ここから =========================== -->

	<?php
	require '../PEAR/HTTP/OAuth.php/OAuth.php';
	require '../PEAR/HTTP/OAuth.php/Request2.php';
	$tmhOAuth = new tmhOAuth(array(
	'consumer_key'    => 'TMRwLJaDaGIptASJGHT35w',
	'consumer_secret' => 'YJ8P6rK8R4GxPRbiabxwj3ko1qcfU8XMPHoUr8Wlw',
	'user_token'      => '64417503-oUymW9BEKYX1XS9lONMQKJEjpvP9G8nRFMUzbdD53',
	'user_secret'     => 'gToMmkQpc6FzxFhSxsspIdcHIEvVonnP1XtvzIywg',
	));

	// note the type and filename are set here as well
	$params = array(
	'image' => "@{$_FILES['image']['tmp_name']};type={$_FILES['image']['type']};filename={$_FILES['image']['name']}",
	);

	// if we are setting the background we want it to be displayed
	if ($_POST['method'] == 'update_profile_background_image')
	$params['use'] = 'true';

	$code = $tmhOAuth->request('POST', $tmhOAuth->url("1.1/account/{$_POST['method']}"),
	$params,
	true, // use auth
	true  // multipart
	);

	if ($code == 200){
	    echo "<h1>更新成功しました。</h1>\n";
	    $oJson = json_decode($tmhOAuth->response['response']);
		echo "プロフィール画像URL：".$oJson->profile_image_url."<br/>\n";
		echo "プロフィール背景画像URL：".$oJson->profile_background_image_url."<br/>\n";
	    echo "<h1>取得したオブジェクト</h1>\n";
	    tmhUtilities::pr(json_decode($tmhOAuth->response['response']));
	}else{
	    echo "<h1>更新に失敗しました。</h1>\n";
	    echo "パラメーターを確認して下さい。<br/>\n";
	    echo "投稿内容を確認して下さい。<br/>\n";
	    echo "<hr/>";
	    echo "<h1>取得したオブジェクト</h1>\n";
	    tmhUtilities::pr(htmlentities($tmhOAuth->response['response']));
	}
	?>

	<!-- =========================== ここまで =========================== // -->
	</div>
	<hr/>

<?php
}
?>



<?php
#########################################
### 投稿フォーム
?>

<h1>更新フォーム</h1>
<form action="images.php" method="POST" enctype="multipart/form-data">
<div>
<select name="method" id="method" >
<option value="update_profile_image">プロフィール画像 update_profile_image</option>
<option value="update_profile_background_image">プロフィール背景画像 update_profile_background_image</option>
</select>
<input type="file" name="image" />
<input type="submit" value="Submit" />
</div>
</form>
</body>
</html>