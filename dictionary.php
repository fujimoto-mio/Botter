<?php

//辞書クラス

//Markovクラスの読み込み
require_once("markov.php");

//定数の定義
define("PATTERN_DIC", "./dic/PatternDic2.txt");	//パターン辞書のファイル名
define("RANDOM_DIC", "./dic/RandomDic1.txt");	//ランダム辞書のファイル名
define("TAMPLATE_DIC", "./dic/TemplateDic1.txt");//テンプレート辞書のファイル名
define("PROHIBIT_DIC", "./dic/ProhibitDic.txt");//禁止語辞書のファイル名



//Dictionaryクラスの定義
class Dictionary {

	//メンバ変数
	var $pattern = array(); //パターン辞書を格納する変数
	var $random = array(); //ランダム辞書を格納する変数
	var $template = array();	//テンプレート辞書を格納する変数
	var $markov;	//マルコフオブジェクトを格納する変数
	var $prohibit = array(); //禁止語辞書を格納する変数

	//コンストラクタ(初期化用メソッド)
	function Dictionary() {
		$this->PatternLoad();
		$this->RandomLoad();
		$this->TemplateLoad();
		$this->MarkovLoad();	//マルコフ辞書読み込み
		$this->ProhibitLoad();	//禁止語辞書読み込み
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


	//ランダム辞書ファイルを読み込むメソッド
	function RandomLoad() {
		$dic = RANDOM_DIC;
		if(!file_exists($dic)) {
			die("ファイルが開けません。");
		}
		$file = file($dic);
		//ランダム辞書を連想配列(ハッシュ)に格納する
		foreach($file as $line) {
			$l = rtrim($line,"\n");
			if(empty($l)) {continue;}
			array_push($this->random, $l);
		}
	}

	//テンプレート辞書ファイルを読み込むメソッド
	function TemplateLoad() {
		$dic = TAMPLATE_DIC;
		if(!file_exists($dic)) {
			die("ファイルが開けません。");
		}
		$file = file($dic);
		foreach($file as $line) {
			//1行ずつ読み込んで処理
			//タブで分割したテキストのそれぞれを$key、$valに代入
			list($key, $val) = split("\t", rtrim($line, "\n"));
			//連想配列(ハッシュ)に要素を格納する
			if(!$this->template[$key]) {$this->template[$key]=array();}
			array_push($this->template[$key], $val);
		}
	}


	//マルコフ辞書ファイルを読み込むメソッド
	function MarkovLoad() {
		$this->markov = new Markov();
		$this->markov->Load();
	}

	//禁止語辞書ファイルを読み込むメソッド
	function ProhibitLoad() {
		$dic = PROHIBIT_DIC;
		if(!file_exists($dic)) {
			die("ファイルが開けません。");
		}
		$file = file($dic);
		//禁止語辞書を連想配列(ハッシュ)に格納する
		foreach($file as $line) {
			$l = rtrim($line, "\n");
			if(empty($l)) {continue;}
			array_push($this->prohibit, $l);
		}
	}


	//学習メソッドを呼び出すメソッド
	function Study($text, $words) {
		//禁止語チェック
		foreach($this->prohibit as $v) {
			$ptn = "/".$v."/";
			//禁止語が含まれていたら学習しない
			if(preg_match($ptn, $text)) {
				return;
			}
		}

		$this->Study_Random($text);
		$this->Study_Pattern($text,$words);
		$this->Study_Template($words); 
		$this->Study_Markov($words); 
	}


	//ランダム辞書の学習メソッド
	function Study_Random($text) {
		//引数のテキストと同じ内容が辞書内にあるかどうかをチェック
		if(array_search($text,$this->random) !== FALSE) {return;}
		//なかったランダム辞書のハッシュに追加する
		array_push($this->random, $text);
	}


	//パターン辞書の学習メソッド
	function Study_Pattern($text, $words) {
		foreach ($words as $k => $v) {
			//名詞でなかったら処理しない
			if(!(preg_match("/名詞/", $v->pos))) {continue;}
			//キーワードの重複チェック
			foreach($this->pattern as $ptn_item) {
				$s = preg_match("/".$v->surface."/", $ptn_item->pattern);
				$r = preg_match("/".$v->reading."/", $ptn_item->pattern);
				if($s == 1 || $r == 1) {
					//重複ありならAdd_pheaseメソッドを実行する
					$p = $ptn_item->Add_phease($text);
					continue 2;
				} 
			}

			//重複なしならキーワードと応答例を辞書に追加する
			//読み仮名が同じでなかったら
			if($v->surface != $v->reading) {
				//読み仮名もキーワードとして登録する
				$key = $v->surface."|".$v->reading;
			} else $key = $v->surface;
			$patternitem = new PatternItem($key, $text);
			//PatternItemオブジェクトのハッシュに格納する
			array_push($this->pattern, $patternitem);
		}
	}


	//テンプレート辞書の学習メソッド
	function Study_Template($words) {
		$template = "";
		$count = 0;
		foreach($words as $k => $v) {
			$surface = $v->surface;
			//単語が名詞だったら
			if(preg_match("/名詞/",$v->pos)) {
				$surface = "%noun%";	//単語を%noun%に置き換える
				$count += 1;		//空欄の数をカウント
			}
			$template = $template.$surface; //単語を連結
		}
		if($count == 0) {return;} //空欄が1つもないなら登録しない
		if(!$this->template[$count]) {$this->template[$count] = array();}
		//テンプレートの重複チェック
		if(array_search($template,$this->template[$count]) !== FALSE) {return;}
		//重複がなかったら追加
		array_push($this->template[$count], $template);
	}

	//マルコフ辞書の学習(作成)メソッドを呼び出すメソッド
	function Study_Markov($words) {
		$this->markov->Add_Sentence($words);
	}


	//辞書のハッシュをファイルに保存する
	function Save(){

		//ランダム辞書の保存
		$dat = RANDOM_DIC;
		if(!file_exists($dat)) {
			die("ファイルが開けません。");
		}
		$fdat = fopen($dat, 'w');
		flock($fdat, LOCK_EX);
		foreach($this->random as $line) {
			fputs($fdat, $line."\n");
		}
		flock($fdat, LOCK_UN);
		fclose($fdat);

		//パターン辞書の保存
		$dat = PATTERN_DIC;
		if(!file_exists($dat)) {
			die("ファイルが開けません。");
		}
		$fdat = fopen($dat, 'w');
		flock($fdat, LOCK_EX);

		foreach($this->pattern as $ptn_item) {
			fputs($fdat, $ptn_item->Make_line()."\n");
		}

		flock($fdat, LOCK_UN);
		fclose($fdat);

		//テンプレート辞書の保存
		$dat = TAMPLATE_DIC;
		if(!file_exists($dat)) {
			die("ファイルが開けません。");
		}


		//テンプレート辞書の保存
		$fdat = fopen($dat, 'w');
		flock($fdat, LOCK_EX);
		foreach($this->template as $key1 => $val1) {
			foreach($val1 as $key2 => $val2) {
				fputs($fdat, $key1."\t".$val2."\n");
			}
		}
		flock($fdat, LOCK_UN);
		fclose($fdat);

		//マルコフ辞書の保存
		$this->markov->Save();

	}



	//パターン辞書(ハッシュ)にアクセスするためのメソッド
	function Pattern() {
		return $this->pattern;
	}

	//ランダム辞書(ハッシュ)アクセスするためのメソッド
	function Random() {
		return $this->random;
	}

	//テンプレート辞書(ハッシュ)アクセスするためのメソッド
	function Template() {
		return $this->template;
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
		if($need == 0) {return TRUE;}
		if(($need < $mood + 5) && ($need > $mood - 5)){
			return TRUE;
		} else	{
			return FALSE;
		}

	}

	//応答例の重複をチェックするメソッド
	function Add_phease($text) {
		//一致する応答例がなかったら応答例にテキストを追加する
		foreach($this->phrases as $p) {
			if($p[phrase] == $text) {return;}
		}
		$ph['need'] = 0;
		$ph['phrase'] = $text;
		array_push($this->phrases, $ph);
	}


	//パターン辞書のハッシュからファイル1行分のデータを生成する
	function Make_line() {
		$ph = array();
		$pattern = $this->modify."##".$this->pattern;
		foreach($this->phrases as $p) {
			$phrases = $p[need]."##".$p[phrase];
			array_push($ph, $phrases);
		}
		return $pattern."\t".join("|", $ph);
	}



}


?>

