<?php

require("logic.php");

// インスタンス生成、DB接続
$mentions = new findMention;
$mentions->connectDatabase();
$mentions->createTable();

// キャッシュ呼び出し
$mentions->cache = $mentions->readData("data");

// キャッシュが最近のでなければ取得・更新
if ($mentions->isCacheLatest() == false){
  // サーバからデータ取得
  $mentions->getTweets();
  $mentions->getHatenaBookmark();
  // キャッシュと新規取得分を合算
  $mentions->mergeCache();
  // DB上書き
  $mentions->changeData();
} else{
  // キャッシュを最新版として表示
  $mentions->mentions = $find->cache;
}

require("view.php");

$mentions->disconnectDatabase();

?>