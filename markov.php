<?php

define("END_MARK", "%END%");	//文書の終わりを表すマークの設定
define("CAHIN_MAX", 30);	//文書生成時の最大連鎖数

//辞書フォル名の設定
define("MARKOV_DIC", "./dic/markovdic.dat");
define("STARTS_DIC", "./dic/starts.dat");

//デバッグモードのON/OFF(1:ON 0:OFF)
define("DEBUG_MODE", "1");


class Markov {

	var $dic;
	var $starts;


	function Markov() {
		//ハッシュ初期化
		$this->dic = array();
		$this->starts = array();
	}


	//マルコフ辞書を作成するメソッド
	//引数$words形態素解析の結果
	function Add_Sentence($words) {
		if(DEBUG_MODE){var_dump("function Add_Sentence==============");var_dump($words);}
		if(count($words) < 3) {return;}

		//単語を配列に格納
		$w = array();
		foreach($words as $k => $v) {
			array_push($w, chop($v->surface));
		}

		//文頭の2単語で初期化
		$prifix1 = array_shift($w);
		$prifix2 = array_shift($w);

		//文頭をハッシュstartsに登録
		//(文頭となっている文脈を蓄積する) 
		$this->Add_Start($prifix1);
		//すべての単語をテーブル(辞書)に登録
		foreach($w as $k => $v) {
			$suffix = $v;
			$this->Add_Suffix($prifix1, $prifix2, $suffix);
			$prifix1 = $prifix2;
			$prifix2 = $suffix;
		}
		
		//最後に終了のマークを付ける
		$this->Add_Suffix($prifix1, $prifix2, END_MARK);

	}

	//マルコフ連鎖で文章を生成するメソッド
	function Generate($keyword) {
		if(empty($this->dic)) {return NULL;}
		$words = array();
		//最初のプリフィクスを選択する
		$prifix1 = ($this->dic[$keyword]) ? $keyword : $this->Select_Random(array_keys($this->starts));
		$prifix2 = $this->Select_Random(array_keys($this->dic[$prifix1]));
		array_push($words, $prifix1, $prifix2);
		$loop = 1;
		while($loop <= CAHIN_MAX) {
			//最初のサフィクスをランダムに選択
			$suffix = $this->Select_Random($this->dic[$prifix1][$prifix2]);
			//END_MARKが出たら終了
			if($suffix == END_MARK) {break;}
			//単語だったら$wordsに追加
			array_push($words, $suffix);
			//プリフィクス、サフィクスをスライドする
			$prifix1 = $prifix2;
			$prifix2 = $suffix;
			$loop += 1;
		}
		//デバッグモードのON/OFF(1:ON 0:OFF)
		if(DEBUG_MODE){var_dump("マルコフ連鎖ー実行Generate");}
		//$words格納された単語を1つにつなげて文章を生成する
		return join("", $words);
	}


	//辞書($dic)にサフィクスを追加するメソッド
	private function Add_Suffix($prifix1, $prifix2, $suffix) {
		if(DEBUG_MODE){var_dump("##".$prifix1."##". $prifix2."##".$suffix);}
		if(!isset($this->dic[$prifix1])) {$this->dic[$prifix1] = array();}
		if(!isset($this->dic[$prifix1][$prifix2])) {$this->dic[$prifix1][$prifix2] = array();}
//		if(!$this->dic[$prifix1]) {$this->dic[$prifix1] = array();}
//		if(!$this->dic[$prifix1][$prifix2]) {$this->dic[$prifix1][$prifix2] = array();}
		array_push($this->dic[$prifix1][$prifix2], $suffix);

	}

	//文書の先頭の単語をハッシュ$startsに登録するメソッド
	private function Add_Start($prifix1) {
		$this->starts=array();
		if(!isset($this->starts[$prifix1])) {$this->starts[$prifix1] = 0;}
		if(DEBUG_MODE){var_dump("!!".$prifix1);}
		$this->starts[$prifix1] += 1;
	}


	//配列の中からランダムに1つの要素を返すメソッド
	function Select_Random($ary) {
		return $ary[rand(0, count($ary) - 1)];
	}

	//マルコフ辞書を保存するメソッド
	function Save() {
		$fname = MARKOV_DIC;
		$fp = fopen($fname, 'w');
		if($fp != NULL) {
			//シリアル化して保存
			fputs($fp, serialize($this->dic));
			fclose($fp);
		}

		$fname = STARTS_DIC;
		$fp = fopen($fname, 'w');
		if($fp != NULL) {
			//シリアル化して保存
			fputs($fp, serialize($this->starts));
			fclose($fp);
		}
	}

	//マルコフ辞書を読み込むメソッド
	function Load() {
		$file = file(MARKOV_DIC);
		if($file) {
			//逆シリアル化
			$data = json_decode($file[0]);
			$this->dic = $data;
			if(DEBUG_MODE){var_dump($data);var_dump("data---------------");}
		}
		$file = file(STARTS_DIC);
		if($file) {
			//逆シリアル化
			$data = base64_decode(html_entity_decode($file[0], ENT_QUOTES));
			//$data = unserialize($file[0]);
			$this->starts = $data;
		}

	}



}


?>
