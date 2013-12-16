<?php


//ユーティリティ

class Util {

	//文字コードをSJISに変換して出力するメソッド
	function Debug_print($text) {
		print mb_convert_encoding($text, "SJIS", "ASCII,JIS,UTF-8,EUC-JP,SJIS")."\n";
	}

	//配列からランダムに1つ取り出すメソッド
	function Select_Random($ary) {
		return $ary[rand(0, count($ary) - 1)];
	}

}


//Webリクエスト

class Web_API {

	var $res;	//取得結果を格納する変数
	var $name;	//オブジェクト名を格納する変数

	function Web_API($name) {
		$this->name=$name;
	}

	//Web APIのリクエストURLを生成するメソッド
	function Request($url, $params) {

		//パラメーターと値をURLエンコードする
		$encoded_params = array();
		foreach($params as $k => $v) {
			$encoded_params[] = urlencode($k).'='.urlencode($v);
		}
		//パラメーターを「&」で連結する
		$req = $url."?".join('&', $encoded_params);
		//Load_fileメソッドの実行結果を返す
		return $this->res = $this->Load_file($req);
	}

	//リクエストを送信して結果を取得するメソッド
	function Load_file($req) {
		return simplexml_load_file($req);
	}

}

//クラスの定義(Web_APIクラスを継承)
class Get_content extends Web_API {

	//リクエストを送信して結果を取得するメソッド
	function Load_file($req) {
		return file_get_contents($req);
	}

}


?>
