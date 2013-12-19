<?php

//Botクラスの読み込み
require_once("bot_core.php");

//Reponderクラスの読み込み
require_once("responder.php");

//Dictionaryクラスの読み込み
require_once("dictionary.php");

//emotionクラスの読み込み
require_once("emotion.php");

//morphemeクラスの読み込み
require_once("morpheme.php");


//デバッグモードのON/OFF(1:ON 0:OFF)
define("DEBUG_MODE", "1");

//============================================================
//EasyBotter Ver2.1.2
//updated 2013/01/08
//============================================================
class EasyBotter
{
	var $myBot;
	var $dic;
	var $emotion;
	
	private $_screen_name;
	private $_consumer_key;
	private $_consumer_secret;
	private $_access_token;
	private $_access_token_secret;
	private $_replyLoopLimit;
	private $_footer;
    private $_dataSeparator;
	private $_tweetData;        
    private $_replyPatternData;        
    private $_logDataFile;
    private $_latestReply;

    function __construct()
    {                        
        //$dir = getcwd();
        //$path = $dir."/PEAR";
        $path = dirname(__FILE__) . "/PEAR";        
        set_include_path(get_include_path() . PATH_SEPARATOR . $path);
        $inc_path = get_include_path();
        chdir(dirname(__FILE__));
        date_default_timezone_set("Asia/Tokyo");        
        
        require_once("setting.php");
        $this->_screen_name = $screen_name;
        $this->_consumer_key = $consumer_key;
        $this->_consumer_secret = $consumer_secret;
        $this->_access_token = $access_token;
        $this->_access_token_secret = $access_token_secret;        
        $this->_replyLoopLimit = $replyLoopLimit;
        $this->_footer  = $footer;
        $this->_dataSeparator = $dataSeparator;        
        $this->_logDataFile = "log.dat";
        $this->_log = json_decode(file_get_contents($this->_logDataFile),true);
        $this->_latestReply = $this->_log["latest_reply"];
        $this->_latestReplyTimeline = $this->_log["latest_reply_tl"];                

        require_once("HTTP/OAuth/Consumer.php");  
		$this->OAuth_Consumer_build();
        $this->printHeader();
    }
       
    function __destruct(){
        $this->printFooter();        
    }

    //つぶやきデータを読み込む
    function readDataFile($file){
        if(preg_match("@\.php$@", $file) == 1){
            require_once($file);
            return $data;
        }else{
            $tweets = trim(file_get_contents($file));
            $tweets = preg_replace("@".$this->_dataSeparator."+@",$this->_dataSeparator,$tweets);
            $data = explode($this->_dataSeparator, $tweets);
            return $data;
        }
    }    
    //リプライパターンデータを読み込む
    function readPatternFile($file){
        $data = array();
        require_once($file);
        if(count($data) != 0){
            return $data;
        }else{
            return $reply_pattern;            
        }
    }    
    //どこまでリプライしたかを覚えておく
    function saveLog($name, $data){
        $this->_log[$name] = $data;
        file_put_contents($this->_logDataFile,json_encode($this->_log));        
    }        
    //表示用HTML
    function printHeader(){
        $header = '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">';
        $header .= '<html xmlns="http://www.w3.org/1999/xhtml" lang="ja" xml:lang="ja">';
        $header .= '<head>';
        $header .= '<meta http-equiv="content-language" content="ja" />';
        $header .= '<meta http-equiv="content-type" content="text/html; charset=UTF-8" />';
        $header .= '<title>EasyBotter</title>';
        $header .= '</head>';
        $header .= '<body><pre>';
        print $header;
    }
    //表示用HTML
    function printFooter(){
        echo "</body></html>";
    }

//============================================================
//bot.phpから直接呼び出す、基本の５つの関数
//============================================================

    //ランダムにポストする
    function postRandom($datafile = "data.txt"){        
        $status = $this->makeTweet($datafile);                
        if(empty($status)){
            $message = "投稿するメッセージがないようです。<br />";
            echo $message;
            return array("error"=> $message);
        }else{                
            //idなどの変換
            $status = $this->convertText($status);
            //フッターを追加
            $status .= $this->_footer;
            return $this->showResult($this->setUpdate(array("status"=>$status)), $status);            
        }    
    }    
    
    //順番にポストする
    function postRotation($datafile = "data.txt", $lastPhrase = FALSE){        
        $status = $this->makeTweet($datafile,0);                
        if($status !== $lastPhrase){
            $this->rotateData($datafile);        
            if(empty($status)){
                $message = "投稿するメッセージがないようです。<br />";
                echo $message;
                return array("error"=> $message);
            }else{                
                //idなどの変換
                $status = $this->convertText($status);    
                //フッターを追加
                $status .= $this->_footer;                       
                return $this->showResult($this->setUpdate(array("status"=>$status)), $status);            
            }
        }else{
            $message = "終了する予定のフレーズ「".$lastPhrase."」が来たので終了します。<br />";
            echo $message;
            return array("error"=> $message);
        }
    }    
    
    //リプライする
    function reply($cron = 2, $replyFile = "data.txt", $replyPatternFile = "reply_pattern.php"){
        $replyLoopLimit = $this->_replyLoopLimit;
        //リプライを取得
        $response = $this->getReplies($this->_latestReply);    
        $response = $this->getRecentTweets($response, $cron * $replyLoopLimit * 3);
        $replies = $this->getRecentTweets($response, $cron);
        $replies = $this->selectTweets($replies);
        if(count($replies) != 0){                           
            //ループチェック
            $replyUsers = array();
            foreach($response as $r){
                $replyUsers[] = $r["user"]["screen_name"];                
            }
            $countReplyUsers = array_count_values($replyUsers);
            $replies2 = array();
            foreach($replies as $rep){
                $userName = $rep["user"]["screen_name"];
                if($countReplyUsers[$userName] < $replyLoopLimit){
                    $replies2[] = $rep;
                }
            }            
            //古い順にする
            $replies2 = array_reverse($replies2);                   
            if(count($replies2) != 0){            
                //リプライの文章をつくる
                $replyTweets = $this->makeReplyTweets($replies2, $replyFile, $replyPatternFile);                
                $repliedReplies = array();
                foreach($replyTweets as $rep){
                    $response = $this->setUpdate(array("status"=>$rep["status"],'in_reply_to_status_id'=>$rep["in_reply_to_status_id"]));
                    $results[] = $this->showResult($response, $rep["status"]);            
                    if($response["in_reply_to_status_id_str"]){
                        $repliedReplies[] = $response["in_reply_to_status_id_str"];
                    }
                }
            }
        }else{
            $message = $cron."分以内に受け取った未返答のリプライはないようです。<br /><br />";
            echo $message;
            $results[] = $message;
        }
        
        //ログに記録
        if(!empty($repliedReplies)){
            rsort($repliedReplies);
            $this->saveLog("latest_reply",$repliedReplies[0]);
        }
        return $results;
    }
    
    //タイムラインに反応する
    function replyTimeline($cron = 2, $replyPatternFile = "reply_pattern.php"){
        //タイムラインを取得
        $timeline = $this->getFriendsTimeline($this->_latestReplyTimeline,100);       
        $timeline2 = $this->getRecentTweets($timeline, $cron);   
        $timeline2 = $this->selectTweets($timeline2);
        $timeline2 = array_reverse($timeline2);        
                
        if(count($timeline2) != 0){
            //リプライを作る        
            $replyTweets = $this->makeReplyTimelineTweets($timeline2, $replyPatternFile);
            if(count($replyTweets) != 0){
                $repliedTimeline = array();
                foreach($replyTweets as $rep){
                    $response = $this->setUpdate(array("status"=>$rep["status"],'in_reply_to_status_id'=>$rep["in_reply_to_status_id"]));
                    $result = $this->showResult($response, $rep["status"]);                    
                    $results[] = $result;
                    if(!empty($response["in_reply_to_status_id_str"])){
                        $repliedTimeline[] = $response["in_reply_to_status_id_str"];
                    }
                }
            }else{
                $message = $cron."分以内のタイムラインに未反応のキーワードはないみたいです。<br /><br />";
                echo $message;
                $results = $message;
            }
        }else{
            $message = $cron."分以内のタイムラインに未反応のキーワードはないみたいです。<br /><br />";
            echo $message;
            $results = $message;        
        }

        //ログに記録        
        if(!empty($repliedTimeline[0])){
            $this->saveLog("latest_reply_tl",$repliedTimeline[0]);
        }
        return $results;        
    }

    //自動フォロー返しする
    function autoFollow(){    
        $followers = $this->getFollowers();
        $friends = $this->getFriends();        
        $followlist = array_diff($followers["ids"], $friends["ids"]);        
        if($followlist){
            foreach($followlist as $id){    
                $response = $this->followUser($id);
                if(empty($response["errors"])){
                    echo $response["name"]."(@<a href='https://twitter.com/".$response["screen_name"]."'>".$response["screen_name"]."</a>)をフォローしました<br /><br />";
                }
            }
        }            
    }

//============================================================
//上の５つの関数から呼び出す関数
//============================================================
    
    //発言を作る
    function makeTweet($file, $number = FALSE){    
        //txtファイルの中身を配列に格納
        if(empty($this->_tweetData[$file])){
            $this->_tweetData[$file] = $this->readDataFile($file);        
        }        
        //発言をランダムに一つ選ぶ場合
        if($number === FALSE){
            $status = $this->_tweetData[$file][array_rand($this->_tweetData[$file])];
        }else{
        //番号で指定された発言を選ぶ場合
            $status = $this->_tweetData[$file][$number];            
        }       
        return $status;
    }    
    
    //リプライを作る
    function makeReplyTweets($replies, $replyFile, $replyPatternFile){
        if(empty($this->_replyPatternData[$replyPatternFile]) && !empty($replyPatternFile)){
            $this->_replyPatternData[$replyPatternFile] = $this->readPatternFile($replyPatternFile);
        }        
        $replyTweets = array();
        
        foreach($replies as $reply){        
            $status = "";
            //指定されたリプライパターンと照合
            if(!empty($this->_replyPatternData[$replyPatternFile])){
                foreach($this->_replyPatternData[$replyPatternFile] as $pattern => $res){
                    if(preg_match("@".$pattern."@u",$reply["text"], $matches) === 1){                                        
                        $status = $res[array_rand($res)];
                        for($i=1;$i <count($matches);$i++){
                            $p = "$".$i;  //エスケープ？
                            $status = str_replace($p,$matches[$i],$status);
                        }
                        break;
                    }
                }            
            }
                         
            //リプライパターンにあてはまらなかった場合はランダムに
            if(empty($status) && !empty($replyFile)){
                $status = $this->makeTweet($replyFile);
            }
            if(empty($status) || stristr($status,"[[END]]")){
                continue;
            }            
            //idなどを変換
            $status = $this->convertText($status, $reply);
            //フッターを追加
            $status .= $this->_footer;
            //リプライ相手、リプライ元を付与
            $re["status"] = "@".$reply["user"]["screen_name"]." ".$status;
            $re["in_reply_to_status_id"] = $reply["id_str"];
            
            //応急処置
            if(!stristr($status,"[[END]]")){
                $replyTweets[] = $re;
            } 
        }                        
        return $replyTweets;    
    }
    
    //タイムラインへの反応を作る
    function makeReplyTimelineTweets($timeline, $replyPatternFile){
        if(empty($this->_replyPatternData[$replyPatternFile])){
            $this->_replyPatternData[$replyPatternFile] = $this->readPatternFile($replyPatternFile);
        }
        $replyTweets = array();        
        foreach($timeline as $tweet){
            $status = "";
            //リプライパターンと照合
            foreach($this->_replyPatternData[$replyPatternFile] as $pattern => $res){
                if(preg_match("@".$pattern."@u",$tweet["text"], $matches) === 1 && !preg_match("/\@/i",$tweet["text"])){                                        
                    $status = $res[array_rand($res)];
                    for($i=1;$i <count($matches);$i++){
                        $p = "$".$i;
                        $status = str_replace($p,$matches[$i],$status);
                    }
                    break;                    
                }                
            }
            if(empty($status)){
                continue;
            }
            //idなどを変換
            $status = $this->convertText($status, $tweet);
            //フッターを追加
            $status .= $this->_footer;

            //リプライ相手、リプライ元を付与
            $rep = array();
            $rep["status"] = "@".$tweet["user"]["screen_name"]." ".$status;
            $rep["in_reply_to_status_id"] = $tweet["id_str"];      
            //応急処置
            if(!stristr($status,"[[END]]")){
                $replyTweets[] = $rep;
            }
        }                        
        return $replyTweets;    
    }        
    
    //ログの順番を並び替える
    function rotateData($file){
        $tweetsData = file_get_contents($file);
        $tweets = explode("\n", $tweetsData);
        $tweets_ = array();
        for($i=0;$i<count($tweets) - 1;$i++){
            $tweets_[$i] = $tweets[$i+1];
        }
        $tweets_[] = $tweets[0];
        $tweetsData_ = "";
        foreach($tweets_ as $t){
            $tweetsData_ .= $t."\n";
        }
        $tweetsData_ = trim($tweetsData_);        
        $fp = fopen($file, 'w');
        fputs($fp, $tweetsData_);
        fclose($fp);            
    }
    
    //つぶやきの中から$minute分以内のものと、最後にリプライしたもの以降のものだけを返す
    function getRecentTweets($tweets,$minute){    
        $tweets2 = array();
        $now = strtotime("now");
        $limittime = $now - $minute * 70; //取りこぼしを防ぐために10秒多めにカウントしてる    
        foreach($tweets as $tweet){
            $time = strtotime($tweet["created_at"]);    
            if($limittime <= $time){                    
                $tweets2[] = $tweet;                
            }else{
                break;                
            }
        }    
        return $tweets2;    
    }
    
    //取得したつぶやきを条件で絞る
    function selectTweets($tweets){    
        $tweets2 = array();
        foreach($tweets as $tweet){
            //自分自身のつぶやきを除外する
            if($this->_screen_name == $tweet["user"]["screen_name"]){
                continue;
            }                        
            //RT, QTを除外する
            if(strpos($tweet["text"],"RT") != FALSE || strpos($tweet["text"],"QT") != FALSE){
                continue;
            }                        
            $tweets2[] = $tweet;                                        
        }    
        return $tweets2;    
    }                            
    
    //文章を変換する
    function convertText($text, $reply = FALSE){        
        $text = str_replace("{year}",date("Y"),$text);
        $text = str_replace("{month}",date("n"),$text);
        $text = str_replace("{day}",date("j"),$text);
        $text = str_replace("{hour}",date("G"),$text);
        $text = str_replace("{minute}",date("i"),$text);
        $text = str_replace("{second}",date("s"),$text);    
              
        //タイムラインからランダムに最近発言した人のデータを取る
        if(strpos($text,"{timeline_id}") !== FALSE){
            $randomTweet = $this->getRandomTweet();
            $text = str_replace("{timeline_id}", $randomTweet["user"]["screen_name"],$text);
        }
        if(strpos($text, "{timeline_name}") !== FALSE){
            $randomTweet = $this->getRandomTweet();
            $text = str_replace("{timeline_name}",$randomTweet["user"]["name"],$text);
        }

        //使うファイルによって違うもの
        //リプライの場合は相手のid、そうでなければfollowしているidからランダム
        if(strpos($text,"{id}") !== FALSE){
            if(!empty($reply)){
                $text = str_replace("{id}",$reply["user"]["screen_name"],$text);                
            }else{
                $randomTweet = $this->getRandomTweet();
                $text = str_replace("{id}",$randomTweet["user"]["screen_name"],$text);        
            }
        }
        if(strpos($text,"{name}") !== FALSE){
            if(!empty($reply)){
                $text = str_replace("{name}",$reply["user"]["name"],$text);                
            }else{
                $randomTweet = $this->getRandomTweet();
                $text = str_replace("{name}",$randomTweet["user"]["name"],$text);        
            }
        }
                
        //リプライをくれた相手のtweetを引用する
        if(strpos($text,"{tweet}") !== FALSE && !empty($reply)){
            $tweet = preg_replace("@\.?\@[a-zA-Z0-9-_]+\s@u","",$reply["text"]); //@リプライを消す        
            $text = str_replace("{tweet}",$tweet,$text);
        }            
                
        return $text;
    }    

    //タイムラインの最近30件の呟きからランダムに一つを取得
    function getRandomTweet($num = 30){
        $response = $this->getFriendsTimeline(NULL, $num);         
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }else{           
            for($i=0; $i < $num;$i++){             
                $randomTweet = $response[array_rand($response)];
                if($randomTweet["user"]["screen_name"] != $this->_screen_name){
                    return $randomTweet;                
                }
            }
        }
        return false;
    }
    
    //結果を表示する
    function showResult($response, $status = NULL){    
        if(empty($response["errors"])){
            $message = "Twitterへの投稿に成功しました。<br />";
            $message .= "@<a href='http://twitter.com/".$response["user"]["screen_name"]."' target='_blank'>".$response["user"]["screen_name"]."</a>";
            $message .= "に投稿したメッセージ：".$response["text"];
            $message .= " <a href='http://twitter.com/".$response["user"]["screen_name"]."/status/".$response["id_str"]."' target='_blank'>http://twitter.com/".$response["user"]["screen_name"]."/status/".$response["id_str"]."</a><br /><br />";
            echo $message;
            return array("result"=> $message);
        }else{
            $message = "「".$status."」を投稿しようとしましたが失敗しました。<br />";
            echo $message;
            echo $response["errors"][0]["message"];               
            echo "<br /><br />";
            return array("error" => $message);
        }
    }


//============================================================
//基本的なAPIを叩くための関数
//============================================================
    function _setData($url, $value = array()){
		$this->OAuth_Consumer_build();//ここでHTTP_OAuth_Consumerを作り直し
        return json_decode($this->consumer->sendRequest($url, $value, "POST")->getBody(), true);
    }    

    function _getData($url){
		$this->OAuth_Consumer_build();//ここでHTTP_OAuth_Consumerを作り直し
        return json_decode($this->consumer->sendRequest($url, array(), "GET")->getBody(), true);
    }    

	function OAuth_Consumer_build(){
        $this->consumer = new HTTP_OAuth_Consumer($this->_consumer_key, $this->_consumer_secret);    
        $http_request = new HTTP_Request2();  
        $http_request->setConfig('ssl_verify_peer', false);  
        $consumer_request = new HTTP_OAuth_Consumer_Request;  
        $consumer_request->accept($http_request);  
        $this->consumer->accept($consumer_request);  
        $this->consumer->setToken($this->_access_token);  
        $this->consumer->setTokenSecret($this->_access_token_secret);
		return;                
	}

    function setUpdate($value){        
        $url = "http://api.twitter.com/1.1/statuses/update.json";
        return $this->_setData($url,$value);
    }            

    function getReplies($since_id = NULL){
        $url = "http://api.twitter.com/1.1/statuses/mentions_timeline.json?";        
        if ($since_id) {
            $url .= 'since_id=' . $since_id ."&";
        }
        $url .= "count=100";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }        

    function getFriendsTimeline($since_id = 0, $num = 100){
        $url = "https://api.twitter.com/1.1/statuses/home_timeline.json?";
        if ($since_id) {
            $url .= 'since_id=' . $since_id ."&";
        }        
        $url .= "count=" .$num ;
		
        $response = $this->_getData($url);
		if($response["errors"]){
            echo $response["errors"][0]["message"];               
		} else{
	        echo "error Array（配列）を文字変換（？）しようとしている?===";
		}

		/*
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }               */    
        return $response;
    }

    function followUser($id)
    {    
        $url = "https://api.twitter.com/1.1/friendships/create.json";
        $value = array("user_id"=>$id, "follow"=>"true");
        return $this->_setData($url,$value);
    }
    
    function getFriends($id = null)
    {
        $url = "https://api.twitter.com/1.1/friends/ids.json";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }    

    function getFollowers()
    {
        $url = "https://api.twitter.com/1.1/followers/ids.json";
        $response = $this->_getData($url);
        if($response["errors"]){
            echo $response["errors"][0]["message"];               
        }                   
        return $response;
    }
        
    function checkApi($resources = "statuses"){
        $url = "https://api.twitter.com/1.1/application/rate_limit_status.json";
        if ($resources) {
            $url .= '?resources=' . $resources;
        }
        $response = $this->_getData($url);    
        var_dump($response);
    }
	
	
//---------------------------------------------------------------------------------------------------------------
	function oAuthRequestImage($url, $method = NULL ,$args = array()) {
        $req = $this->_setData($url, $args);
    	return $req;
  	}
	  
	//プロフィール画像をアップロードするメソッド
	function ImageRequest($method = "POST", $opt = array()) {
		$req = $this->OAuthRequestImage("http://api.twitter.com/1.1/account/update_profile_image.json", $method, $opt);
		if($req){$result = $req;} else {$result = null;}
		return $result;
	}
	
	//機嫌値によってプロフィール画像を変更するメソッド
	function ProfileImage() {
		$no = round(($this->myBot->emotion->mood + 15) / 6);
		$no = 5;
		$img_src = "./image/".$no.".png";  // 画像ファイルの指定
        $imgbinary = fread(fopen($img_src, "r"), filesize($img_src)); // バイナリデータを読み込み
        $image = base64_encode($imgbinary); // base64エンコード
		//base64_encode
        //$image="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";
		$opt = array();
		$opt['image'] = "@".$image.";type=image/png;filename=".$no.".png";
		
		$req = $this->ImageRequest("POST", $opt);
		//var_dump($req);
	}
	
//---------------------------------------------------------------------------------------------------------------
	
	//
	//パターン辞書をかいしてリブライを返す
	//
	function emotion($cron = 2, $user){
		$this->myBot = new Bot();
		
        //タイムラインを取得
        $timeline = $this->getFriendsTimeline($this->_latestReplyTimeline,100);       
        $timeline2 = $this->getRecentTweets($timeline, $cron);   
        $timeline2 = $this->selectTweets($timeline2);
        $timeline2 = array_reverse($timeline2);        


		//最後に取得した発言のIDを取得する
		$since_id = $this->myBot->ReadData("Since");
		//リプライ済みユーザーを格納する配列の初期化
		$replied_users = array();
		//ボット相手に返信する上限回数
		$reply_limit = 3;
		//無視するユーザーIDの一覧を取得する
		$pass_list = $this->myBot->ReadData("Pass");
		
		//
		//タイムラインの処理
		foreach($timeline2 as $key=>$Timeline ) {
			$txt = null;
			//発言のIDの取得
			$sid = $Timeline["id"];
			//var_dump($Timeline);
					
			//ユーザーのスクリーン名の取得
			$screen_name = $Timeline["user"]["screen_name"];
			
			//送信元の取得
			$source = $Timeline["source"];
			//ユーザーIDの取得
			$uid = $Timeline["user"]["id"];
			
			//発言本文の余分なスペースを消し、半角カナを全角カナ、全角英数を半角英数に変換
			$text = mb_convert_kana(trim($Timeline["text"]), "rnKHV", "utf-8");
			//ボット自身の発言、RT、QTに反応しないようにする
			if($screen_name == $user || preg_match("/(R|Q)T( |:)/", $text)) {
				var_dump($screen_name."=".$user."continue== 自身の発言、RT、QTに反応しないよう======");
				continue;
			}
			
			//同じ相手でリプライ済みなら返信しないようにする
			//if(in_array($screen_name, $replied_users)) {continue;}
						
			//Webからの投稿以外なら返信カウンタをチェックする
			if(!stristr($source, 'web')) {
				//返信カウンタファイルの読み込み
				$reply_cnt_file = $this->myBot->ReadData($uid."Count");
				$reply_cnt = $reply_cnt_file[0];
				if(!$reply_cnt) {$reply_cnt = 0;}
				//上限値に達していたら
				if($reply_cnt >= $reply_limit) {
					//返信カウンタファイルを削除して、返信処理をスキップする
					unlink($reply_cnt_file);
					continue;
				}
			}

			//無視するユーザーIDが一致したら、返信処理をスキップする
			foreach($pass_list as $p) {if($p == $uid) {continue 2;}}
		
			
			//取得したテキストを表示コマンドプロンプトでの出力確認用
			if(DEBUG_MODE) {var_dump($text);}
			var_dump($screen_name."==".$user."=====================================================");
			$input = $screen_name;
			//相互フォローしているユーザーの発言、またはボット宛てのリプライなら
		 	if(stristr($input, "@".$user) || !strstr($input, "@")) {
		
				//現在の機嫌値をファイルから読み込んでセットする
				//if(MOOD_MODE){$myBot->emotion->User_mood($uid); }
				var_dump($input."=".$user."フォローしているユーザーの発言、またはボット宛てのリプライなら==");
						
				//送信する文字列の取得
				//引数$userにはユーザー名を渡す
				$txt=$this->myBot->Conversation($text);
				//$txt=$myBot->Conversation($text,$user);
				var_dump($txt."=引数userにはユーザー名を渡す==");
				
				//コマンドプロンプトでの出力確認用
				$text = $this->myBot->ResponderName()."(".$this->myBot->emotion->mood.") -> ".$txt;
				var_dump($text."=出力確認用==");
				
				//$txtが空でなかったら送信する
				if($txt){
					var_dump($txt."=が空でなかったら送信する==");
					                //idなどの変換
                	$status = $this->convertText($txt);    
                	//フッターを追加
                	$status .= $this->_footer;                       
                	$this->showResult($this->setUpdate(array("status"=>$status)), $status);            
					
					
					//$this->myBot->Post("@".$screen_name." ".$txt, $sid);
					//返信済みユーザーを配列に記憶する
					$replied_users[] = $screen_name;
		
					//返信カウンタを+1して保存する
					$reply_cnt++;
					$this->myBot->WriteData($screen_name."Count", $reply_cnt);
				}
			}
		}
		//最後に取得した発言のIDをファイルに記録する
		$this->myBot->WriteData("Since", $sid);

		//30分(1800秒)更新がない返信カウンタファイルを削除する
		$this->myBot->DeleteFile("Count", 1800);
	}



	function Mentions($cron = 2, $user){
		$this->myBot = new Bot();

        //タイムラインを取得
        $timeline = $this->getFriendsTimeline($this->_latestReplyTimeline,100);       
        $timeline2 = $this->getRecentTweets($timeline, $cron);   
        $timeline2 = $this->selectTweets($timeline2);
        $timeline2 = array_reverse($timeline2);        


		//フォロー・リムーブ処理
		//最後に取得したリプライのIDを取得する
		$since_id = $this->myBot->ReadData("Mentions");
		//リプライ済みユーザーを格納する配列の初期化
		$replied_users = array();
		//ボット相手に返信する上限回数
		$reply_limit = 3;
		//無視するユーザーIDの一覧を取得する
		$pass_list = $this->myBot->ReadData("Pass");

        var_dump("=======================================================");

		//ボット宛てのリプライ処理
		//タイムラインの処理
		foreach($timeline2 as $key=>$Timeline ) {
			$txt = null;
			//発言のIDの取得
			$sid = $Timeline["id"];
			//var_dump($Timeline);
					
			//ユーザーのスクリーン名の取得
			$screen_name = $Timeline["user"]["screen_name"];
			
			//送信元の取得
			$source = $Timeline["source"];
			//ユーザーIDの取得
			$uid = $Timeline["user"]["id"];
			
			//発言本文の余分なスペースを消し、半角カナを全角カナ、全角英数を半角英数に変換
			$text = mb_convert_kana(trim($Timeline["text"]), "rnKHV", "utf-8");
			//ボット自身の発言、RT、QTに反応しないようにする
			if($screen_name == $user || preg_match("/(R|Q)T( |:)/", $text)) {
				var_dump($screen_name."=".$user."continue== 自身の発言、RT、QTに反応しないよう======");
				continue;
			}
			
			//同じ相手でリプライ済みなら返信しないようにする
			//if(in_array($screen_name, $replied_users)) {continue;}
						
			//Webからの投稿以外なら返信カウンタをチェックする
			if(!stristr($source, 'web')) {
				//返信カウンタファイルの読み込み
				$reply_cnt_file = $this->myBot->ReadData($uid."Count");
				$reply_cnt = $reply_cnt_file[0];
				if(!$reply_cnt) {$reply_cnt = 0;}
				//上限値に達していたら
				if($reply_cnt >= $reply_limit) {
					//返信カウンタファイルを削除して、返信処理をスキップする
					unlink($reply_cnt_file);
					continue;
				}
			}

			//無視するユーザーIDが一致したら、返信処理をスキップする
			foreach($pass_list as $p) {if($p == $uid) {continue 2;}}
		
			
			//取得したテキストを表示コマンドプロンプトでの出力確認用
			if(DEBUG_MODE) {var_dump($text);}
			var_dump($screen_name."==".$user."=====================================================");
			$input = $screen_name;
			//相互フォローしているユーザーの発言、またはボット宛てのリプライなら
		 	if(stristr($input, "@".$user) || !strstr($input, "@")) {
		
				//現在の機嫌値をファイルから読み込んでセットする
				//if(MOOD_MODE){$myBot->emotion->User_mood($uid); }
				var_dump($input."=".$user."フォローしているユーザーの発言、またはボット宛てのリプライなら==");
						
				//送信する文字列の取得
				//引数$userにはユーザー名を渡す
				$txt=$this->myBot->Conversation($text);
				//$txt=$myBot->Conversation($text,$user);
				var_dump($txt."=引数userにはユーザー名を渡す==");
				
				//コマンドプロンプトでの出力確認用
				$text = $this->myBot->ResponderName()."(".$this->myBot->emotion->mood.") -> ".$txt;
				var_dump($text."=出力確認用==");
				
				//$txtが空でなかったら送信する
				if($txt){
					var_dump($txt."=が空でなかったら送信する==");
					                //idなどの変換
                	$status = $this->convertText($txt);    
                	//フッターを追加
                	$status .= $this->_footer;                       
                	$this->showResult($this->setUpdate(array("status"=>$status)), $status);            
					
					
					//$this->myBot->Post("@".$screen_name." ".$txt, $sid);
					//返信済みユーザーを配列に記憶する
					$replied_users[] = $screen_name;
		
					//返信カウンタを+1して保存する
					$reply_cnt++;
					$this->myBot->WriteData($screen_name."Count", $reply_cnt);
				}
			}
		}
			
		$this->ProfileImage();
			
		//最後に取得した発言のIDをファイルに記録する
		$this->myBot->WriteData("Since", $sid);

		//30分(1800秒)更新がない返信カウンタファイルを削除する
		$this->myBot->DeleteFile("Count", 1800);
	}
	
	
	
	function StudyBot($cron = 2, $user){
		$this->myBot = new Bot();

		//タイムラインを取得
        $timeline = $this->getFriendsTimeline($this->_latestReplyTimeline,100);       
        $timeline2 = $this->getRecentTweets($timeline, $cron);   
        $timeline2 = $this->selectTweets($timeline2);
        $timeline2 = array_reverse($timeline2);        


		//フォロー・リムーブ処理
		//最後に取得したリプライのIDを取得する
		$since_id = $this->myBot->ReadData("Mentions");
		//リプライ済みユーザーを格納する配列の初期化
		$replied_users = array();
		//ボット相手に返信する上限回数
		$reply_limit = 3;
		//無視するユーザーIDの一覧を取得する
		$pass_list = $this->myBot->ReadData("Pass");

		var_dump($timeline2."===================================");

		//ボット宛てのリプライ処理
		//タイムラインの処理
		foreach($timeline2 as $key=>$Timeline ) {
			$txt = null;
			//発言のIDの取得
			$sid = $Timeline["id"];
			//var_dump($Timeline);
					
			//ユーザーのスクリーン名の取得
			$screen_name = $Timeline["user"]["screen_name"];
			
			//送信元の取得
			$source = $Timeline["source"];
			//ユーザーIDの取得
			$uid = $Timeline["user"]["id"];
			
			//発言本文の余分なスペースを消し、半角カナを全角カナ、全角英数を半角英数に変換
			$text = mb_convert_kana(trim($Timeline["text"]), "rnKHV", "utf-8");
			//ボット自身の発言、RT、QTに反応しないようにする
			if($screen_name == $user || preg_match("/(R|Q)T( |:)/", $text)) {
				var_dump($screen_name."=".$user."continue== 自身の発言、RT、QTに反応しないよう======");
				continue;
			}
			
			//同じ相手でリプライ済みなら返信しないようにする
			if(in_array($screen_name, $replied_users)) {continue;}
						
			//Webからの投稿以外なら返信カウンタをチェックする
			if(!stristr($source, 'web')) {
				//返信カウンタファイルの読み込み
				$reply_cnt_file = $this->myBot->ReadData($uid."Count");
				$reply_cnt = $reply_cnt_file[0];
				if(!$reply_cnt) {$reply_cnt = 0;}
				//上限値に達していたら
				if($reply_cnt >= $reply_limit) {
			  		var_dump($reply_cnt .">=". $reply_limit."=reply_cnt_file返信カウンタファイルを削除して、返信処理をスキップする");
					//返信カウンタファイルを削除して、返信処理をスキップする
  				    unset($reply_cnt_file);
					//unlink($reply_cnt_file);
					continue;
					 
				}
			}

			//無視するユーザーIDが一致したら、返信処理をスキップする
			foreach($pass_list as $p) {if($p == $uid) {continue 2;}}
		
			
			//取得したテキストを表示コマンドプロンプトでの出力確認用
			if(DEBUG_MODE) {var_dump($text);}
			  var_dump($screen_name."==".$user."====!===========");
			  $input = $screen_name;
			  //相互フォローしているユーザーの発言、またはボット宛てのリプライなら
		 	  if(stristr($input, "@".$user) || !strstr($input, "@")) {
		
				//現在の機嫌値をファイルから読み込んでセットする
				//if(MOOD_MODE){
				$this->myBot->emotion->User_mood($user);
				//}
				var_dump($input."=".$user."フォローしているユーザーの発言、またはボット宛てのリプライなら==");
						
				//送信する文字列の取得
				//引数$userにはユーザー名を渡す
				$txt=$this->myBot->Conversation($text);
				//$txt=$this->myBot->Conversation($text,$user);
				var_dump($txt."=を".$user."に渡す==");

				//コマンドプロンプトでの出力確認用
				$text = $this->myBot->ResponderName()."(".$this->myBot->emotion->mood.") -> ".$txt;
				var_dump($text."=出力確認用==");
				
				//$txtが空でなかったら送信する
				if($txt){
					var_dump($txt."=が空でなかったら送信する==");
					                //idなどの変換
                	$status = $this->convertText($txt);    
                	//フッターを追加
                	$status .= $this->_footer;                       
                	$this->showResult($this->setUpdate(array("status"=>$status)), $status);            
					
					//$this->myBot->Post("@".$screen_name." ".$txt, $sid);
					//返信済みユーザーを配列に記憶する
					$replied_users[] = $screen_name;
		
					//返信カウンタを+1して保存する
					$reply_cnt++;
					$this->myBot->WriteData($screen_name."Count", $reply_cnt);
			  }
			}
		}
			
		$this->ProfileImage();
			
		//最後に取得した発言のIDをファイルに記録する
		$this->myBot->WriteData("Since", $sid);

		//30分(1800秒)更新がない返信カウンタファイルを削除する
		$this->myBot->DeleteFile("Count", 1800);
	}
	
	
	
	    
}
?>