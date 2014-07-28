<?php
namespace Http;

class Http
{

	private static $down_servers = array();

	public static function _init()
	{
		\Config::load('http', true);
	}

	public static function get($server, $url, $conn_timeout = null, $trans_timeout = null, $get_res = true)
	{
		// パラメタチェック
		if ( ! $server)
		{
			trigger_error('required parameter $server not passed', E_USER_WARNING);
			return false;
		}
		if ( ! $url )
		{
			trigger_error('required parameter $url not passed', E_USER_WARNING);
			return false;
		}

		// サーバーダウンを検出済みであれば終了
		if (isset(static::$down_servers[$server]))
		{
			return false;
		}

		// 接続タイムアウトの設定
		is_numeric($conn_timeout) or $conn_timeout = \Config::get('http.conn.timeout');

		// 通信タイムアウトの設定
		is_numeric($trans_timeout) or $trans_timeout = \Config::get('http.trans.timeout');

		$trans_timeout_int = intval($trans_timeout);
		$trans_timeout_dec = intval(($trans_timeout - $trans_timeout_int) * 1000000);

		// 接続
		if( ! $s = fsockopen($server, 80, $errno, $errstr, $conn_timeout))
		{
			//ダウンしたサーバーを記録
			static::$down_servers[$server] = true;
			return false;
		}

		$request = "GET {$url} HTTP/1.0\r\n";
		$request .= "Host: {$server}\r\n";
		$request .= "Connection: Close\r\n\r\n";

		fwrite($s, $request);

		// 通信タイムアウトは第2引数（秒）と第3引数（マイクロ秒）の和
		stream_set_timeout($s, $trans_timeout_int, $trans_timeout_dec);

		// レスポンスを期待しない場合はここで終了
		if ( ! $get_res)
		{
			fclose($s);
			return true;
		}

		// レスポンスを期待する場合は継続
		$buffer = '';
		while ( !feof($s) ) {
			$buffer .= fread($s, 8192);

			//データ読込みタイムアウト判定
			$info = stream_get_meta_data($s);
			if ($info['timed_out'])
			{
				break;
			}
		}
		fclose($s);

		// ヘッダとコンテンツを分割
		list($header, $body) = explode("\r\n\r\n", $buffer, 2) + array(null, null);

		if (strpos($header, '200 OK') === false)
		{
			// ダウンしたサーバーを記録
			static::$down_servers[$server] = true;
			trigger_error('response code not 200 for request['.$server.' '.$url.']', E_USER_WARNING);
			return false;
		}

		return $body;
	}

}