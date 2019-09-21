<?php
/**
 * Copyright 2016 LINE Corporation
 *
 * LINE Corporation licenses this file to you under the Apache License,
 * version 2.0 (the "License"); you may not use this file except in compliance
 * with the License. You may obtain a copy of the License at:
 *
 *   https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS, WITHOUT
 * WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. See the
 * License for the specific language governing permissions and limitations
 * under the License.
 */

require_once('./LINEBotTiny.php');
require_once __DIR__ . '/vendor/autoload.php';
use  josegonzalez\Dotenv\Loader as Dotenv;
    //初期化
    //.envの保存場所指定（カレントに設定）

/**
$dotenv = new Dotenv\Dotenv(__DIR__ . '/..');
$dotenv->load(__DIR__ . '/..');
    $dotenv = new Dotenv\Dotenv(__DIR__);
    $dotenv->load();

**/
$appDir = __DIR__;
  Dotenv::load([
      'filepath' =>  $appDir . '/.env',
      'toEnv' => true
  ]);

//LINE developerアクセス
$channelAccessToken = $_ENV['ChannelAccessToken'] ;
$channelSecret = $_ENV['ChannelSecret'];
$dbName = $_ENV['MYSQL_DBNAME'];
$dbHost = $_ENV['MYSQL_HOST'];
$client = new LINEBotTiny($channelAccessToken, $channelSecret);
//ラーメン屋情報提供のためデータベース接続
$dsn = "mysql:dbname=$dbName;host=$dbHost;charset=utf8mb4";
$user = $_ENV['MYSQL_USER'];
$pass = $_ENV['MYSQL_PASS'];
//ユーザーのメッセージタイプ毎のレスポンス
foreach ($client->parseEvents() as $event) {
	switch ($event['type']) {
		case 'message':
			$message = $event['message'];
			switch ($message['type']) {
				//メッセージタイプがテキストの場合
				case 'text':
					$res_name = $message['text'];
					$res_name = '%' . $res_name . '%';
					try {
						$dbh = new PDO ($dsn, $user, $pass);
						$sql = "SELECT * FROM shops WHERE station1 LIKE ? AND deleted_at is NULL ORDER BY likes_count DESC";
						$stmt = $dbh->prepare($sql);
						$stmt->bindValue(1, $res_name);//送信された内容で検索
						$stmt->execute();
						$results = $stmt->fetchAll();
					} catch (EXCEption $e) {
						echo $e->getMessage();
					}
					$i = 1;
					if ($results) {
						foreach ($results as $result) {
							${'res' . $i} = "\ $result[point] ♡ $result[likes_count]/ \r\n $result[name] \r\n $result[URL]\r\n";
							$i++;
						}
					} else {
						$comment1 = array('未開の地', '知らんなー', '初耳ガクッ', 'それは臭うな');
						$comment2 = array('調査して共有するぞよ', '教えてくれてくれてありがとな', 'ちょっと行ってくる','ハヤシ先生に聞いてみよ');
						$key1 = array_rand($comment1);
						$key2 = array_rand($comment2);
						$res1 = $comment1[$key1];
						$res2 = $comment2[$key2];
						$res3 = null;
					}
					$client->replyMessage([
						'replyToken' => $event['replyToken'],
						'messages' => [
							[
								'type' => 'text',
								'text' => "$res1 \r\n $res2 \r\n $res3"
							]
						]
					]);
					break;
				//メッセージタイプがスタンプの場合
				case 'sticker':
					$client->replyMessage([
						'replyToken' => $event['replyToken'],
						'messages' => [
							[
								'type' => 'sticker',
								'packageId' => 11537,
								'stickerId' => 52002739,
							]
						]
					]);
					break;
				//メッセージタイプが位置情報の場合
				case 'location':
					// 受信した位置情報からの情報
					$lat = $message['latitude'];
					$lon = $message['longitude'];
					// ぐるなびapiURL
					$uri = 'https://api.gnavi.co.jp/RestSearchAPI/v3/';
					// ぐるなびアクセスキー
					$gnaviaccesskey = '630813f39d7d4043d69a42f220ddc04e';
					// ラーメン屋さんを意味するぐるなびのコード(小業態マスタ取得APIをコールして調査)
					$category_s1 = 'RSFST08008';
					// つけ麺屋さんを意味するぐるなびのコード(小業態マスタ取得APIをコールして調査)
					$category_s2 = 'RSFST08008';
					// 3件抽出
					$hit_per_page = 3;
					//範囲
					$range = 3;
					//URL組み立て
					$url  = $uri . '?keyid=' . $gnaviaccesskey . '&latitude=' . $lat . '&longitude=' . $lon . '&range=' . $range . '&category_s=' . $category_s1 .'&category_s=' . $category_s2 . '&hit_per_page=' . $hit_per_page ;
					//ぐるなびapiの情報取得
					$conn = curl_init();
					curl_setopt($conn, CURLOPT_URL, $url);
					curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
					$res = curl_exec($conn);
					$obj = json_decode($res);
					curl_close($conn);
					// 店舗情報を取得
					$columns = array();
					foreach ($obj->rest as $restaurant) {
						$columns[] = array(
						'thumbnailImageUrl'=>$restaurant->image_url->shop_image1,
						'title'=>$restaurant->name,
						'text'=>$restaurant->address,
						'actions'=>array(
							array(
								'type'=>'uri',
								'label'=>'詳細を見る',
								'uri'=>$restaurant->url
							)
						)
						);
					}
					if ($columns !== null) {
						$client->replyMessage([
							'replyToken' => $event['replyToken'],
							'messages' => [
								[
									'type' => 'template',
									'altText' => '周辺のラーメン屋情報',
									'template' => [
										'type' => 'carousel',
										'columns' => [
											[
												//'thumbnailImageUrl' =>$columns[0][thumbnailImageUrl] , //ぐるなびimageに
												'imageBackgroundColor' => '#FFFFFF',
												'title' => $columns[0][title],
												'text' => $columns[0][text],//リンクにしたい
												//'defaultAction' => [
												//'type' => 'uri',
												//'label' =>' View detail',
												//'uri' => 'http://example.com/page/123', //ぐるなびuri
											//],
												'actions' => [
													[
														'type' => 'uri',
														'label' => 'ぐるなびサイトへ',
														'uri'=>$columns[0]['actions'][0]['uri'],
													]
												]
											],
											[
												//'thumbnailImageUrl' =>$columns[0][thumbnailImageUrl] , //ぐるなびimageに
												'imageBackgroundColor' => '#FFFFFF',
												'title' => $columns[1][title],
												'text' => $columns[1][text],//リンクにしたい
												//'defaultAction' => [
												//'type' => 'uri',
												//'label' =>' View detail',
												//'uri' => 'http://example.com/page/123', //ぐるなびuri
											//],
												'actions' => [
													[
														'type' => 'uri',
														'label' => 'ぐるなびサイトへ',
														'uri' => $columns[1]['actions'][0]['uri'],
													]
												]
											],
											[
												//'thumbnailImageUrl' =>$columns[0][thumbnailImageUrl] , //ぐるなびimageに
												'imageBackgroundColor' => '#FFFFFF',
												'title' => $columns[2][title],
												'text' => $columns[2][text],//リンクにしたい
												//'defaultAction' => [
												//'type' => 'uri',
												//'label' =>' View detail',
												//'uri' => 'http://example.com/page/123', //ぐるなびuri
											//],
												'actions' => [
													[
														'type' => 'uri',
														'label' => 'ぐるなびサイトへ',
														'uri' => $columns[2]['actions'][0]['uri'],
													]
												]
											]
										]
									]
								]
							]
						]);
						break;
					} else {
						$client->replyMessage([
							'replyToken' => $event['replyToken'],
							'messages' => [
								[
								'type' => 'text',
								'text' => '残念ですが近くにラーメン屋が見つかりませんでした。'
								]
							]
						]);
						break;
					}
		}
		break;
		default:
			error_log('Unsupported event type:' . $event['type']);
			break;
	}
};
?>
