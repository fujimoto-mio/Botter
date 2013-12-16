<?php
//Dictionaryクラスの読み込み
require_once("dictionary.php");
//応答クラス


//Responderクラスの定義
class Responder {

	//メンバ変数
	var $name;	//オブジェクト名を格納する変数
	var $dictionary; //Dictionaryオブジェクトを格納する変数
	var $pattern = array();


	//コンストラクタ(初期化用メソッド)
	//Dictionaryオブジェクトを渡せるように変更
	function Responder($name, $dictionary) {
		$this->name = $name;
		$this->dictionary = $dictionary;
	}

	//受け取った文字列をそのまま返すメソッド
	//機嫌値($mood)を渡せるように変更
	function Response($text,$mood) {
		return $text;
	}

	//名前を返すメソッド
	function Name() {
		return $this->name;
	}
}

//TimeResponderクラスの定義(Responderクラスを継承)
class TimeResponder extends Responder {


	//現在時によって指定の送信する言葉をセットするメソッド
	function Response() {

		$hour = date("G");

		switch($hour) {
			case 6:
				$text = 'おはよう！今日もがんばろう！';
				break;
			case 13:
				$text = 'お昼、何食べた？';
				break;
			case 17:
				$text = '仕事終わったー';
				break;
			case 21:
				$text = 'おやすみなさい。';
				break;
			default:
				$text = '';

		}
		return $text;
	}
}

//RandomResponderクラスの定義(Responderクラスを継承)
class RandomResponder extends Responder {

	//メンバ変数
	var $text;	//テキストを格納する変数
	

	//コンストラクタ(初期化用メソッド)
	function RandomResponder($name) {
		$this->name = $name;
		//乱数の生成
		$no = rand(1, 3);
		//乱数に応じた辞書ファイルを読み込む
		$dic = "./dic/RandomDic".$no.".txt";
		if(!file_exists($dic)) {
			die("ファイルが開けません2。");
		}
		$this->text = file($dic);
	}


	//読み込んだファイルからランダムに文字列を取り出すメソッド
	function Response() {
		$res=$this->text[rand(0, count($this->text) - 1)];
		return rtrim($res, "\n"); //改行コードを取り除く
	}
}

class WhatResponder extends Responder {

	//受け取った文字列に「って何?'」を付けて返すメソッド
	function Response($text) {
		return $text.'って何?';
	}

}

//GreetingResponderクラスの定義(Responderクラスを継承)
class GreetingResponder extends Responder {


	//ツイート、リプライに挨拶文が含まれていたら、対応する挨拶を返すメソッド
	function Response($text) {
		if(preg_match("/おは(よ)?(う|ー|～)/", $text)) {$txt = "おはようございます";}
		if(preg_match("/こんにち(は|わ)/", $text)) {$txt = "こんにちは";}
		if(preg_match("/こんばん(は|わ)/", $text)) {$txt = "こんばんは";}
		return $txt;
	}

}


//PatternResponderクラスの定義(Responderクラスを継承)
class PatternResponder extends Responder {

	var $pattern = array();

	//コンストラクタ(初期化用メソッド)
	function PatternResponder($name) {
		$this->name = $name;
		$dic = "./dic/PatternDic1.txt";
		if(!file_exists($dic)) {
			die("ファイルが開けません1。");
		}
		$file = file($dic);
	

		foreach ($file as $line) {
			list($key, $val) = split("\t", chop($line));
			$ptn['pattern'] = $key;
			$ptn['phrases'] = $val;
			array_push($this->pattern, $ptn);
			var_dump($line."=");
		}
	}
	//パターン辞書を元に応答メッセージを作るメソッド
	function Response($text/*, $mood*/) {

		//パターン辞書の先頭行から順にパターンマッチを行う
		foreach($this->pattern as $key =>$val) {
			$ptn = $val['pattern'];
var_dump($ptn."=======");
			if(preg_match("/".$ptn."/", $text)){
				$phrases = split("\|", $val['phrases']);
				$res = $phrases[rand(0, count($phrases) -1)];
var_dump($res."=======");
				return preg_replace("/%match%/", $ptn, $res);
			}
		}
/*		foreach($this->dictionary->Pattern() as $ptn_item) {
			if($ptn = $ptn_item->Match($text)) {
				$res = $ptn_item->Choice($mood);
				if($res==null) {next;}
var_dump($ptn."==答例に「%match%/」という文字列があったら、マッチした====");
				//応答例に「%match%/」という文字列があったら、マッチした文字列と置き換える
				return preg_replace("/%match%/", $ptn, $res);
			}
		}
*/
	}


}

?>
