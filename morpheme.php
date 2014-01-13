<?php

define("YAHOO_API_ID", "dj0zaiZpPXRTbmJhQU1YQ1hwRSZzPWNvbnN1bWVyc2VjcmV0Jng9YWI-");	//Yahoo APIのアプリケーションID


//Yahoo_morphクラスの定義
class Yahoo_morph {

	var $xml;	//XMLオブジェクトを格納する変数

	function Request($text) {

		$text = rtrim($text, "\n");

		//API用パラメーター
		$params = array(
			'appid' => YAHOO_API_ID,
			'sentence' => $text,
			'results' => 'ma',
		);

		$url = "http://jlp.yahooapis.jp/MAService/V1/parse";
		//APIリクエスト
		$api = new Web_API("Yahoo_Morph");
		
		$this->xml = $api->Request($url, $params);
        if(DEBUG_MODE){foreach ($this->xml->ma_result->word_list->word as $cur){var_dump("object=".$cur->surface); }}	
		return $this->xml->ma_result->word_list->word;
	}


	//形態素の総数を返すメソッド
	function Total_count() {
		return $this->xml->ma_result->total_count;
	}

	//フィルタにマッチした形態素数を返すメソッド
	function Filtered_count() {
		return $this->xml->ma_result->filtered_count;
	}

	//形態素(配列)を返すメソッド
	function Words() {
		return $this->xml->ma_result->word_list->word;
	}

	//XMLオブジェクトを返すメソッド
	function Response() {
		return $this->xml;
	}

}


?>

