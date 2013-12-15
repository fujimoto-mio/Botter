<?php

//辞書クラス

//定数の定義
define("PATTERN_DIC", "./dic/PatternDic2.txt");	//パターン辞書のファイル名


//Dictionaryクラスの定義
class Dictionary {

	//メンバ変数
	var $pattern=array(); //ファイルから読み込んだテキストを格納する変数

	//コンストラクタ(初期化用メソッド)
	function Dictionary() {
		$this->PatternLoad();
	}


	//パターン辞書ファイルを読み込むメソッド
	function PatternLoad() {
		//パターン辞書ファイルを読み込む
		$dic = PATTERN_DIC;
		if(!file_exists($dic)) {
			die("ファイルが開けません。");
		}
		$file = file($dic);

		//パターン辞書ファイルを連想配列(ハッシュ)に展開する
		foreach($file as $line) {
			//1行ずつ読み込んで処理
			//タブで分割したテキストのそれぞれを$key、$valに代入
			list($key, $val) = split("\t", rtrim($line, "\n"));
			//連想配列(ハッシュ)に要素を格納する
			$ptn['pattern'] = $key;
			$ptn['phrases'] = $val;
			//PatternItemオブジェクトの生成
			$patternitem = new PatternItem($ptn['pattern'], $ptn['phrases']);
			//PatternItemオブジェクトのハッシュに格納する
			array_push($this->pattern, $patternitem);
		}
	}

	//パターン辞書(ハッシュ)にアクセスするためのメソッド
	function Pattern() {
		return $this->pattern;
	}

}


define("SEPARATOR", "/^((-?\d+)##)?(.*)$/");

//PatternItemクラスの定義
class PatternItem {

	//メンバ変数
	var $pattern;		//パターンマッチ文字列を格納する変数
	var $modify;		//機嫌変動値を格納する変数
	var $phrases = array();	//応答例を格納する変数

	//コンストラクタ(初期化用メソッド)
	function PatternItem($pattern, $phrases) {
		//$patternから機嫌変動値とパターンマッチ文字列を取り出す
		preg_match(SEPARATOR, $pattern, $regex);
		//機嫌変動値を変数に格納する
		$this->modify = intval($regex[2]);
		//パターンマッチ文字列を変数に格納する
		$this->pattern = $regex[3];
		//応答例を連想配列に格納する
		foreach(split("\|", $phrases) as $phrase) {
			preg_match(SEPARATOR, $phrase, $regex);
			$ph['need'] = intval($regex[2]);
			$ph['phrase'] = $regex[3];
			array_push($this->phrases, $ph);
		}
	}

	//パターンマッチを行うメソッド
	function Match($str) {
		return preg_match("/".$this->pattern."/", $str);
	}

	//現在の機嫌値($mood)によって応答例を選択するメソッド
	function Choice($mood) {
		//応答例の候補を配列に格納する
		$choice = array();
		foreach($this->phrases as $p) {
			if($this->Check($p['need'], $mood)) {
				array_push($choice, $p['phrase']);
			}
		}

		//候補からランダムに1つ選んだ応答例を返す
		return empty($choice)? null : $choice[rand(0, count($choice) - 1)];
	}

	//応答例が必要機嫌値の条件を満たしているかをチェックするメソッド
	function Check($need, $mood) {
		if($need == 0) return TRUE;
		if(($need < $mood + 5) && ($need > $mood - 5)) {
			return TRUE;
		} else {
			return FALSE;
		}

	}

	//とりあえず未使用
	function Suitable($need, $mood) {
		if($need == 0) return TRUE;
		if($need > 0) {
			return $mood > $need;
		} else {
			return $mood < $need;
		}
	}

}


?>
