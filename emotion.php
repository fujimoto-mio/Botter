<?php
//Dictionaryクラスの読み込み
require_once("dictionary.php");

//定数の定義
define("MODE_MIN", "-15");	//機嫌値下限
define("MODE_MAX", "15");	//機嫌値上限
define("MODE_RECOVERY", "0.5");	//機嫌値の回復する度合い


//Emotionクラスの定義
class Emotion {

	//メンバ変数
	var $dictionary;	//パターン辞書オブジェクトを格納する変数
	var $mood;		//現在の機嫌値を格納する変数
	var $uid;		//ユーザーIDを格納する変数


	//コンストラクタ(初期化用メソッド)
	function Emotion($dictionary) {

		//Dictionaryオブジェクトの生成
		$this->dictionary = new Dictionary();

		//パターン辞書オブジェクトを格納する
		$this->dictionary = $dictionary;
		//現在の機嫌値を読み込む
		$this->mood = $this->Load_mood("");
	}

	function User_mood($uid) {
		$this->uid = $uid;
		$this->mood = $this->Load_mood($this->uid);

	}

	//会話によって機嫌値を変動されるメソッド
	function Update($input) {
		//パターン辞書の要素を繰り返し処理する バカ|馬鹿 かわいい|可愛い  めぐ
		//var_dump($this->dictionary->Pattern() );
		foreach($this->dictionary->Pattern() as $PatternItem ) {
		  	//パターンマッチを行う
         	$tmp = preg_match("/".$PatternItem->pattern."/", $input);
			//var_dump($tmp);
			if($tmp) {
				//マッチしたらAdjust_moodメソッドで機嫌値を変動させる
				//var_dump($PatternItem->modify."===機嫌値です。");
				$this->Adjust_mood($PatternItem->modify);
				break;
			}
		}
		echo $this->mood ."===機嫌を徐々に平静な状態====";

		//機嫌を徐々に平静な状態(機嫌値0)に回復させる処理
		if($this->mood < 0) {
			//0以下なら+0.5ずつ0に近づける
			$this->mood += MODE_RECOVERY;
		} elseif($this->mood > 0) {
			//0以上なら-0.5ずつ0に近づける
			$this->mood -= MODE_RECOVERY;
		}

		//現在の機嫌値を保存する
		$this->Save_mood($this->mood, $this->uid);
	}

	//機嫌値を変動させるメソッド
	function Adjust_mood($val) {
		//機嫌変動値($val)によって機嫌値を変動させる
		$this->mood += $val;
		//機嫌値が上限、下限を超えないようにする処理
		if($this->mood > MODE_MAX) {
			$this->mood = MODE_MAX;
		} elseif($this->mood < MODE_MIN) {
			$this->mood = MODE_MIN;
		}
	}


	//$uidを引数として渡せるように変更する
	//$uidを元にユーザー別の機嫌値ファイルを読み込み、保存する
	function Load_mood($uid) {
		$dat = "./dat/".$uid."_mood.dat";
		if(!file_exists($dat)) {
			touch($dat);
			chmod($dat, 0666);
			return null;
		}
		$fdat = fopen($dat, 'r');
		$mood = fgets($fdat);
		fclose($fdat);
		return 	$mood;
	}
	//機嫌値(mood)をファイルに書き込むメソッド
	function Save_mood($data,$uid) {
		$dat = "./dat/".$uid."_mood.dat";
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


	//現在の機嫌値を取得するメソッド
	function Mood() {
		return $this->mood;
	}




}


?>
