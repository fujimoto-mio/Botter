<?php
//Utilファイルの読み込み
require_once("util.php");

//Reponderクラスの読み込み
require_once("responder.php");

//Dictionaryクラスの読み込み
require_once("dictionary.php");

//emotionクラスの読み込み
require_once("emotion.php");

//morphemeクラスの読み込み
require_once("morpheme.php");

//Botクラスの定義
class Bot {
	//メンバ変数
	var $user;	//ユーザー名を格納する変数
	var $Obj;	//OAuthオブジェクトを格納する変数
	var $responder;	//Responderオブジェクトを格納する変数
	var $words;

	var $rand_responder;	//RandomResponderオブジェクトを格納する変数
	var $time_responder;	//TimeResponderオブジェクトを格納する変数
	var $what_responder;	//WhatResponderオブジェクトを格納する変数
	var $greet_responder;	//GreetingResponderオブジェクトを格納する変数
	var $pattern_responder;	//PatternResponderオブジェクトを格納する変数
	var $templ_responder;	//TemplateResponderオブジェクトを格納する変数
	var $markov_responder;	//MarkovResponderオブジェクトを格納する変数
	
	var $dic;	//Dictionaryオブジェクトを格納する変数
	var $emotion;	//Emotionオブジェクトを格納する変数

    
	//コンストラクタ(初期化用メソッド)
	function Bot(){

		//Dictionaryオブジェクトの生成
		$this->dic = new Dictionary();

		//Emotionオブジェクトの生成
		$this->emotion = new Emotion($this->dic);

		//Responderオブジェクトを生成する際にDictionaryオブジェクトを渡す
        $this->yahoo_morph = new Yahoo_morph();
		
		//Responderオブジェクトの生成
		$this->null_responder = new Responder('Null', $this->dic);
				
		//RandomResponderオブジェクトの生成
		$this->rand_responder = new RandomResponder('Random', $this->dic);
		//TimeResponderオブジェクトの生成
		$this->time_responder = new TimeResponder('Time', $this->dic);
		//WhatResponderオブジェクトの生成
		$this->what_responder = new WhatResponder('What', $this->dic);
		//GreetingResponderオブジェクトの生成
    	$this->greet_responder = new GreetingResponder('Greeting', $this->dic);
		//PatternResponderオブジェクトの生成
    	$this->pattern_responder = new PatternResponder('Pattern');

		//TemplateResponderオブジェクトの生成
		$this->template_responde = new TemplateResponder('Template', $this->dic);
    	//MarkovResponderオブジェクトの生成
		$this->markov_responder = new MarkovResponder('Markov', $this->dic);
	}
	
	//テキストをResponderオブジェクトに渡すメソッド
	function Speaks($input) {
		//2つのResponderオブジェクトをランダムに切り返る
		$this->responder = rand(1, 2) - 1 == 0 ? $this->time_responder : $this->rand_responder;
		return $this->responder->Response($input);
	}

	//テキストをResponderオブジェクトに渡すメソッド(リプライ用)
	function Conversation($input) {

		//Responderをランダムに切り替える
		$sel = rand(1, 100); //乱数生成
/*
		//乱数の値によってResponderを切り替える
		if($sel > 0 && $sel < 10) {
			echo "pattern";
			$this->responder = $this->pattern_responder;
		} elseif($sel >= 10 && $sel < 20) {
			echo "template";
			$this->responder = $this->template_responde;
		} elseif($sel >= 20 && $sel <= 70) {
			echo "markov";
			$this->responder = $this->markov_responder;
		}elseif($sel >= 70 && $sel < 80) {
			echo "rand";
			$this->responder = $this->rand_responder;
		} else {
			echo "null";
			$this->responder = $this->null_responder;
		}
*/
		$this->responder = $this->markov_responder;
		//宛先のユーザ名(@xxxx)を消す
		$input = trim(preg_replace("/@[a-zA-Z0-9_]+/", "", $input));

		//パターンマッチを行い感情を変動させる
		$this->emotion->Update($input);
		
			
		//形態素解析の結果を取得
		$words = $this->yahoo_morph->Request($input);
		
		//Studyメソッドにテキストを渡し学習する
		//引数$wordsで形態素解析の結果を渡せるように変更
		$this->dic->Study($input, $words);

		return $this->responder->Response($input, $this->emotion->mood, $words);
	}


	//Responderオブジェクトの名前を返すメソッド
	function ResponderName() {
		return $this->responder->Name();
	}

	//ファイルに記録したデータを読み込むメソッド
	function ReadData($type) {
		$dat = "./dat/".$this->user."_".$type.".dat";
		if(!file_exists($dat)) {
			touch($dat);
			chmod($dat, 0666);
			return null;
		}
		return file($dat);
	}

  	//ファイルにデータを書き込むメソッド
	function WriteData($type, $data) {
		$dat = "./dat/".$this->user."_".$type.".dat";
		if(!file_exists($dat)) {
			touch($dat);
			chmod($dat, 0666);
		}
		$fdat = fopen($dat, 'w');
		flock($fdat, LOCK_EX);
		fputs($fdat, $data);
		flock($fdat, LOCK_UN);
		fclose($fdat);
	}

	//$sec秒更新のないファイルを削除するメソッド
	function DeleteFile($type, $sec) {
		$dat = glob("../../dat/".$this->user."_*".$type.".dat");
		foreach($dat as $k => $v) {
			if(filectime($v) < time() - $sec) {
				unlink($v);
			}
		}
	}

	//フォロー、リムーブするメソッド
	function Follow($uid, $flg = true) {
		//ユーザーID($uid)をリクエストパラメータにセット
		$opt = array();
		$opt['id'] = $uid;
		//$flgが「true」ならフォロー、「False」ならリムーブ
		$req = $this->Request("friendships/".($flg?"create":"destroy").".json", "POST", $opt);
		//PHP配列に変換
		$result = json_decode($req);
		return $result;
	}
	
	//DictionaryオブジェクトのSaveメソッドにアクセスするためのメソッド
	function Save() {
		$this->dic->Save();
	}
	
	


}


?>
