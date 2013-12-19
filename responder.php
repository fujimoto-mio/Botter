<?php
//Dictionaryクラスの読み込み
require_once("dictionary.php");
require_once("util.php");
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
			die("ファイルが開けません。");
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
			die("ファイルが開けません。");
		}
		$file = file($dic);
	
		echo $dic." ***fileOpen PatternResponder***";
		foreach ($file as $line) {
			list($key, $val) = split("\t", chop($line));
			$ptn['pattern'] = $key;
			$ptn['phrases'] = $val;
			array_push($this->pattern, $ptn);
			//var_dump($line."=");
		}
	}
	
	//パターン辞書を元に応答メッセージを作るメソッド
	function Response($text/*, $mood*/) {

		//パターン辞書の先頭行から順にパターンマッチを行う
		foreach($this->pattern as $key =>$val) {
			$ptn = $val['pattern'];
//var_dump($ptn."=======");
			if(preg_match("/".$ptn."/", $text)){
				$phrases = split("\|", $val['phrases']);
				$res = $phrases[rand(0, count($phrases) -1)];
var_dump($res."match=======");
				return preg_replace("/%match%/", $ptn, $res);
			}
		}

	}
}



//TemplateResponderクラスの定義(Responderクラスを継承)
class TemplateResponder extends Responder {


	//テンプレート辞書を元に応答メッセージを作るメソッド
	//引数$wordsに形態素解析の結果を渡す
	function Response($text, $mood, $words) {
		//文章に含まれるキーワード(名詞)を配列に格納
		$keywords = array();
		foreach($words as $k => $v) {
			if(preg_match("/名詞/", $v->pos)) {
				array_push($keywords, $v->surface);
			}
		}
		$count = count($keywords);	//キーワードの数を数える
		//辞書に使えるテンプレートがあったら
		if($count > 0 && $templates = $this->dictionary->template[$count]) {
			//キーワード数にマッチするテンプレートを辞書からランダムに選択する
			$template = $templates[rand(0, count($templates) - 1)];
			///「%noun%/」をキーワードに置き換える
			foreach($keywords as $v) {
				$templ = preg_replace("/%noun%/", $v, $template, 1);
				$template = $templ;
			}
			return $template;
		}
		//テンプレートがなかったら、ランダム辞書から応答例を持ってくる
//		if(USE_RANDOM_DIC) {return Util::Select_random($this->dictionary->random);}

	}
}


//MarkovResponderクラスの定義(Responderクラスを継承)
class MarkovResponder extends Responder {
	function Response($text, $mood, $words) {
		$this->util = new Util();

		$keywords=array();
		//キーワード(名詞)の抽出
		foreach($words as $v) {
			if(preg_match("/名詞/", $v->pos)) {
				array_push($keywords, $v->surface);
			}
		}
		//キーワードから文章を生成し表示
		if(count($keywords)) {
			$keyword = $keywords[rand(0, count($keywords) - 1)];
			$res = $this->dictionary->markov->Generate(chop($keyword));
			if($res) {return $res;}
		}
		//応答例がなかったら、ランダム辞書から応答例を持ってくる
		//if(USE_RANDOM_DIC) {
		return $this->util->Select_random($this->dictionary->random);
	}

}
?>
