<?php

require_once("./lib/twitteroauth/twitteroauth/twitteroauth.php");

class findMention{

function __construct(){
  if (!isset($_GET["title"])) die("タイトルが指定されていません");
  if (!isset($_GET["url"])) die("URLが指定されていません");
  
  // 変数
  $this->title = $_GET["title"];
  $this->url = $_GET["url"];
  $this->id = md5($this->title.$this->url);
  // キャッシュ時間（秒）
  $this->cacheTime =600; // sec
  
  require_once "key.php";
}

function getHatenaBookmark(){
  $entries = null;
  $counts = array();
  
  $url = "http://b.hatena.ne.jp/entry/jsonlite/?url=". urlencode($this->url);
  $data = file_get_contents($url);
  if ($data == false) return $this->errorRegist("hatena", "connectError"); // 通信失敗
  if ($data == "null") return $this->errorRegist("hatena", "noResult"); // 0件
  $data = json_decode($data);
  
  // いったん配列に格納
  foreach ($data->bookmarks as $entry){
    $username = h($entry->user);
    $uid = "user_".$username;
    $counts += array($uid => "");
    if (($entry->comment != "") && ($entry->comment != $this->title)){
      $entries[$uid]["type"] = "hatenabookmark";
      $entries[$uid]["user"]["nick"] = $username;
      $entries[$uid]["user"]["url"] = "http://b.hatena.ne.jp/".$username."/";
      $entries[$uid]["user"]["img"] = "http://www.st-hatena.com/users/".substr($username, 0, 2)."/". $username."/profile_l.gif";
      $entries[$uid]["comment"]["rawcontent"] = $entry->comment;
      $entries[$uid]["comment"]["timestamp"] = str_replace("/", "-", $entry->timestamp);
      $entries[$uid]["comment"]["url"] = "http://b.hatena.ne.jp/".$username."/".substr($entry->timestamp, 0, 4).substr($entry->timestamp, 5, 2).substr($entry->timestamp, 8, 2)."#bookmark-".$data->eid;
    }
  }
  
  // メッセージ部分のうち、要らないところを削った上で設定する簡単なお仕事
  $entries = $this->setComments($entries);
  
  // 変数設定
  $this->mentions["hatena"] = $entries;
  $this->mentions["_count"]["hatena"] = $counts;
}

function getTweets(){
  $entries = null;
  $counts = array();
  
  // データ取得
  $twObj = new TwitterOAuth($this->consumer_key, $this->consumer_secret, $this->access_token, $this->access_tokensecret);
  $data = $twObj->OAuthRequest("https://api.twitter.com/1.1/search/tweets.json", "GET", array('q' =>$this->title, 'count' =>'100', 'include_entities' =>'true'));
  if ($data == "" || $data == false || $data == null) return $this->errorRegist("twitter", "connectError"); // 通信失敗
  
  //$data = file_get_contents("sample2.json");
  $data = json_decode($data);
  if (count($data->statuses) == 0) return $this->errorRegist("twitter", "noResult"); // 0件
  
  // いったん配列に格納
  foreach ($data->statuses as $entry){
    $username = h($entry->id_str);
    $uid = "user_".$username;
    $entries[$uid]["type"] = "twitter";
    $entries[$uid]["user"]["nick"] = $entry->user->screen_name;
    $entries[$uid]["user"]["name"] = $entry->user->name;
    $entries[$uid]["user"]["url"] = "http://twitter.com/".$entry->user->screen_name;
    $entries[$uid]["user"]["img"] = $entry->user->profile_image_url;
    $entries[$uid]["comment"]["rawcontent"] = $entry->text;
    $entries[$uid]["comment"]["timestamp"] = shiftTime($entry->created_at);
    $entries[$uid]["comment"]["url"] = "http://twitter.com/".$entry->user->screen_name."/status/".$username;
    $entries[$uid]["comment"]["shorturls"] = $entry->entities->urls;
    $entries[$uid]["comment"]["reaction"]["retweet"] = $entry->retweet_count;
    $entries[$uid]["comment"]["reaction"]["favorite"] = $entry->favorite_count;
    $counts += array($uid => "");
  }
  
  // メッセージ部分のうち、要らないところを削った上で設定する簡単なお仕事
  $entries = $this->setComments($entries);
  
 //debug($entries);
  
  // 変数設定
  $this->mentions["twitter"] = $entries;
  $this->mentions["_count"]["twitter"] = $counts;
}

function setComments($comments){
  foreach ($comments as $uid => $value){
    // 一旦、要素をセット
    $comments[$uid] = $value;
    $comments[$uid]["comment"]["content"] = $comments[$uid]["comment"]["rawcontent"];
    // ツイートはまず短縮URLを展開
    if ($comments[$uid]["type"] == "twitter"){
      $comments[$uid]["comment"]["content"] = $this->cleanTweet($comments[$uid]);
      unset($comments[$uid]["comment"]["shorturls"]);
    }
    // 記事タイトルからゴミを削除
    $comments[$uid]["comment"]["content"] = $this->cleanComment($comments[$uid]["comment"]["content"]);
    // テキスト内容が空になってたら要素ごと削除
    if ($comments[$uid]["comment"]["content"] == ""){
      unset($comments[$uid]);
      continue;
    }
  }
  
  return $comments;
}

function cleanComment($comment){
  // 囲い&接続系 / 囲い系 / 接続系
  $pattern = "\s{0,2}(\/|／|\:|：|>|＞|→|｜|\||\-){0,2}\s{0,2}(「|『|“|”|\"|\'){0,2}".preg_quote($this->title, "/")."(」|』|“|”|\"|\'){0,2}\s{0,2}";
  // 二重短縮URLはタイトルと一緒の場合は消す展開しちゃう
  $url = "http:\/\/(t\.co|bit\.ly|htn\.to|tinyurl\.com|fb\.me|buff\.ly|ow\.ly|dlvr\.it|j\.mp|wp\.me|goo\.gl|flip\.it)\/[0-9a-zA-Z]+";
  $patterns = array($pattern."\s?".$url, $pattern);
  foreach ($patterns as $pattern){
    $comment = preg_replace("/".$pattern."/", "", $comment);
  }
  
  // URL削除（変なクエリが付いている時から）
  $comment = preg_replace("/".preg_quote($this->url, "/")."\?utm_[a-zA-Z0-9%&\._=]+/", "", $comment);
  // URL削除
  $comment = str_replace($this->url, "", $comment);
  // [***] タグっぽいもの削除
  $comment = preg_replace("/\[[^\]]+\]/", "", $comment);
  // 改行無効
  $comment = preg_replace("/\n/", "", $comment);
  // 無言リツイートまるごと削除
  $comment = preg_replace("/^RT\s?@.+$/", "", $comment);
  // 全角空白削除
  $comment = trim($comment);
  $comment = preg_replace("/^[ 　]+/u", "", $comment);
  $comment = preg_replace("/[ 　]+$/u", "", $comment);
  // 最後に先頭または行末に残っている取りカスを削除
  $comment = preg_replace("/^\s?(\/|／|\:|：|>|＞|→|｜|\||\-)\s?/", "", $comment);
  $comment = preg_replace("/\s?(\/|／|\:|：|>|＞|→|｜|\||\-)\s?$/", "", $comment);
  
  return $comment;
  
}

function cleanTweet($tweet_container){
  $tweet = $tweet_container["comment"]["content"];
  $urls = $tweet_container["comment"]["shorturls"];
  
  // URL展開
  if (isset($urls)){
    foreach ($urls as $url){
      $tweet = str_replace($url->url, $url->expanded_url, $tweet);
    }
  }
  
  // パターン削除
  $patterns = array(
    "[rR]ead(ing)?\s?(\-|\:|：|\.+)?", // NewsStorm削除
    "by\shttp:\/\/bit\.ly\/NewsStorm\s?(#fb)?", // NewsStorm削除
    "Now\s?Browsing\s?(\-|\:|：|\.+)?", // Chrome拡張削除
    "\#([zZ]enback|SmartNews)", // ハッシュタグ削除
    "Gunosy\s+News", // Gunosy
    "via\s+http:\/\/gunosy\.com", // Gunosy
    "http:\/\/gunosy\.com\/g\/[a-zA-Z0-9]+", // Gunosy
    "\/via\s+vingow\.com", // Vingow
    "Read Item By GoogleReader", // Google Reader削除
    "@[a-zA-Z0-9_]+さんから", // ツイートボタンのメンション削除
    "\(via @pocket\)", // Pocket独自メンション削除
    "via @[a-zA-Z0-9_]+" // via系メンション削除
  );
  
  foreach ($patterns as $pattern){
    $tweet = preg_replace("/".$pattern."/", "", $tweet);
  }
  
  return $tweet;
}

function isCacheLatest(){
  $last_modified = strtotime($this->readData("datetime"));
  $now = time();
  if ($last_modified == null) return false;
  
  if (($now - $last_modified) > $this->cacheTime){
    return false;
  } else{
    return true;
  }
}

function sortArray($arr){
  if ($this->sort == "favorite"){
    // スター順に並べ替え
    uasort($arr, function($a, $b) {
      return $a["comment"]["reaction"]["favorite"] < $b["comment"]["reaction"]["favorite"];
    });
  } elseif ($this->sort == "timestamp"){
    // 時系列順に並べ替え
    uasort($arr, function($a, $b) {
      return strtotime($a["comment"]["timestamp"]) < strtotime($b["comment"]["timestamp"]);
    });
  }
  return $arr;
}

function mergeCache(){
  // 新規取得でない＆取得分がエラーでない場合にキャッシュに取得分のうち新規分を追加
  if (($this->cache != null) && ($this->mentions != null)){
    $this->mentions = array_merge_recursive_overwrite($this->cache, $this->mentions);
  }
}

function showComment($type){
  $comment = null;
  
  if ($type == "hatena"){
    if (count($this->mentions["hatena"]) > 0){
      foreach ($this->mentions["hatena"] as $entry){
        $comment  .= '<div class="comment">';
        $comment  .= '<div class="icon"><img src="'. h($entry["user"]["img"]) .'" width="32" height="32" alt="" /></div>';
        $comment  .= '<div class="user"><span class="nick"><a href="'. h($entry["user"]["url"]) .'">'. h($entry["user"]["nick"]) .'</a></span>'.
                               '<span class="favorite '.h($entry["user"]["nick"]).'"></span>';
        $comment .= '<span class="timestamp" title="'.strtotime($entry["comment"]["timestamp"]).'">'.
                               '<a href="'.h($entry["comment"]["url"]).'" title="'.h($entry["comment"]["timestamp"]).'">'.
                               h($this->getRelativeTime($entry["comment"]["timestamp"])) .'</a></span>';
        $comment .= '<p class="content">'. mb_convert_kana($this->markup($entry["comment"]["content"]),"a") .'</p></div>';
        $comment  .= '</div>';
      }
    } else{
        $comment  .= '<div class="error">コメントはありませんでした</div>';
    }
  } elseif ($type == "twitter"){
    if (count($this->mentions["twitter"]) > 0){
      foreach ($this->mentions["twitter"] as $entry){
        //debug($entry);
        $fav = h($entry["comment"]["reaction"]["retweet"]);
        $comment  .= '<div class="comment">';
        $comment  .= '<div class="icon"><img src="'. h($entry["user"]["img"]) .'" width="32" height="32" alt="" /></div>';
        $comment  .= '<div class="user"><span class="name"><a href="'. h($entry["user"]["url"]) .'">'.
                               h($entry["user"]["name"]).'</a></span>'.
                               '<span class="nick">@'. h($entry["user"]["nick"]) .'</span>'.
                               '<span class="favorite" title="'.$fav.'">'.(($fav!=0) ? '<img src="./etc/retweet.png" width="16" height="10" alt="RT" />'.h($fav): "").'</span>';
        $comment .= '<span class="timestamp" title="'.strtotime($entry["comment"]["timestamp"]).'"><a href="'.h($entry["comment"]["url"]).'" title="'.h($entry["comment"]["timestamp"]).'">'.
                               h($this->getRelativeTime($entry["comment"]["timestamp"])) .'</a></span>';
        $comment .= '<p class="content">'. $this->markup($entry["comment"]["content"]) .'</p></div>';
        $comment  .= '</div>';
      }
    } else{
      $comment  .= '<div class="error">コメントはありませんでした</div>';
    }
  }
  echo $comment;
}

function showCommentCount($service){
  $comment = count($this->mentions[$service]);
  $all = count($this->mentions["_count"][$service]);
  
  if ($all == 0 && $comment == 0) return "コメントなし";
  if ($comment == 0) return "コメントなし（全<span class='count {$service}_all'>{$all}</span>件）";
  return "コメント<span class='count {$service}_commentonly'>{$comment}</span>件（全<span class='count {$service}_all'>{$all}</span>件）";
}

function showCommentUser(){
  $s = null;
  
  if (count($this->mentions["_count"]["hatena"]) > 0){
    // ユーザ一覧をゲット
    foreach ($this->mentions["hatena"] as $entry){
      $s .= '"'.urlencode($entry["user"]["nick"]).'":"'.urlencode($entry["comment"]["url"]).'",';
    }
    $s = "{".substr($s, 0, -1)."}";
  } else{
    $s = '""';
  }
  return "var urls = $s;";
}

function markup($s){
  // リプライ先は弱めに表示
  $s = preg_replace("/(RT\s?@.+)$/", '<span class="reply">\1</span>', $s);
  // URLをリンクに
  $s = preg_replace( "/(https?:\/\/[\-\_\.\!~\*a-zA-Z0-9;\/\?\:@&=\+\$\,%\#]+)/", '<a href="\1">\1</a>', $s);
  // idコール
  $s = preg_replace("/id\:([a-zA-Z0-9_\-]+)/", '<a href="http://b.hatena.ne.jp/\1/">id:\1</a>', $s);
  // twitterのメンションをリンクに
  $s = preg_replace("/@([a-zA-Z0-9_]+)/", '<a href="http://twitter.com/\1">@\1</a>', $s);
  // ハッシュタグをリンクに
  $s = preg_replace("/(#[a-zA-Z0-9]+)/", '<span class="hashtag"><a href="https://twitter.com/search?q=\1">\1</a></span>', $s);
  // 引用は引用に
  $s = preg_replace("/^(「|『|“|”|\"|\')(.+)(」|『|“|”|\"|\')$/", '<q>\2</q>', $s);
  return $s;
}


function getRelativeTime($time){ // $time = 2012-01-01 12:34:56
  $time = strtotime($time); // convert to unixtime;
  $now = time();
  $rtime = $now - $time;
  
  if ($rtime < 60){
    return "たった今";
  } elseif ($rtime < 60*60){
    return floor($rtime/60)."分前";
  } elseif ($rtime < 60*60*24){
    return floor($rtime/(60*60))."時間前";
  } elseif ($rtime < 60*60*24*3){
    return floor($rtime/(60*60*24))."日前";
  } else{
    return date("n月j日", $time);
  }
}

// 短縮URLを展開
function getOriginalUrl($short_url){
  $header_data = get_headers($short_url, true);
  if (isset($header_data["Location"])){
    $original_url = $header_data["Location"];
    if(is_array($original_url)){
      return end($original_url);
    }
  } else{
    return $short_url;
  }
}

function errorRegist($service, $error_type){
  if ($service == "hatena"){
    $service_ja = "はてなブックマーク";
  } elseif ($service == "twitter"){
    $service_ja = "twitter";
  }
  
  if ($error_type == "connectError"){
    $error = $service_ja."のデータが取得出来ませんでした。";
  } else{
    $error = $service_ja."での言及は現在のところありません";
  }
  
  $this->error[$service] = $error;
  
  return null;
}

function connectDatabase(){
  try{
    $this->db = new PDO( 'sqlite:./data.db');
    $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  } catch( PDOException $e) {
    // DBアクセス時にエラーとなった時
    die("Connection failed :". $e->getMessage());
  }
}

function createTable(){
  /* 初期テーブル作成 */
  $sql  = "CREATE TABLE if not exists mentiondb(
      id text PRIMARY KEY,
      data text,
      url text NOT NULL,
      title text,
      datetime text NOT NULL
    )"; // text型以外使わない、SQLに型検証は求めない
  if (!$this->db->query($sql)) die("creating table failed"); 
}

function readData($type){
  $sql = "select ".$type." from mentiondb where id = :id";
  $statement = $this->db->prepare($sql);
  $statement->bindParam(":id", $this->id);
  if ($statement->execute()){
    $result = $statement->fetchAll();
    if (count($result) != 0){
      $result = ($type == "data") ? unserialize($result[0][$type]) : $result[0][$type];
    } else{
      $result = null;
    }
  }
  return $result;
}

function changeData(){
  if (isset($this->cache)){
    $this->putData("update");
  } else{
    $this->putData("create");
  }
}

function putData($type){
  if ($type == "create"){
    $sql = "insert into mentiondb (id, data, url, title, datetime) values(:id, :data, :url, :title, :datetime)";
  } elseif ($type == "update"){
    $sql = "update mentiondb SET data = :data, url = :url, title = :title, datetime = :datetime WHERE id = :id";
  }
  
  $statement = $this->db->prepare($sql);
  $statement->bindValue(":id", $this->id);
  $statement->bindValue(":data", serialize($this->mentions));
  $statement->bindValue(":url", $this->url);
  $statement->bindValue(":title", $this->title);
  $statement->bindValue(":datetime", date("Y-m-d H:i:s"));
  $statement->execute() or die("Writing data failed");
}

function disconnectDatabase(){
  unset($this->db);
}

}

function http_build_queries($queries){
  $i = 0;
  $constructs = null;
  
  foreach ($queries  as $query){
    if ($i != 0){
      $constructs .= "&";
    }
    $constructs .= "uri=".urlencode($query);
    $i++;
  }
  return $constructs;
}

// array_merge_recursiveがarray_mergeのキー上書き動作ではなく挿入動作をするので
// 上書き動作をするarray_merge_recursiveを定義
function array_merge_recursive_overwrite($arr1, $arr2){
  foreach($arr2 as $key=>$value){
    if (!is_array($arr1)){
      $arr1 = array($arr1);
    }
    if (is_array($value) && isset($arr1[$key])){
      $arr1[$key] = array_merge_recursive_overwrite($arr1[$key], $value);
    } else{
      $arr1[$key] = $value;
    }
  }
  return $arr1;
}

function trimText($t){
  $t = preg_replace("/\s?\(via\s@Pocket\)\shttp:\/\/t\.co\/[a-zA-Z0-9]+\s?/", "", $t);
  return $t;
}

function shiftTime($t){
  $strtotime = strtotime($t); // convert to Unix Timestamp
  $datetime = date('Y-m-d H:i:s', $strtotime); //20xx-01-01-00:00:00
 return $datetime;
}

function h($u){
  return htmlspecialchars($u);
}

function debug($s, $t="Debug"){
  echo "<div style='background-color: #ccc; color: black; padding: 10px; margin: 0px 10px; font-weight: bold;'>{$t}</div>";
  echo "<pre style='background-color: black; color: white; padding: 10px; margin: 0px 10px 20px 10px; overflow: auto;'>";
  print_r($s);
  echo "</pre>";
}

/*

*** 封印されたアルゴリズム ***

// 最長文字列部分列の検索
function get_longest_common_subsequence($string_1, $string_2){
  $string_1_length = strlen($string_1);
  $string_2_length = strlen($string_2);
  $return = "";
  
  if ($string_1_length === 0 || $string_2_length === 0){
    // No similarities
    return $return;
  }
  
  $longest_common_subsequence = array();
  
  // Initialize the CSL array to assume there are no similarities
  for ($i = 0; $i < $string_1_length; $i++){
    $longest_common_subsequence[$i] = array();
    for ($j = 0; $j < $string_2_length; $j++){
      $longest_common_subsequence[$i][$j] = 0;
    }
  }
  
  $largest_size = 0;
  
  for ($i = 0; $i < $string_1_length; $i++){
    for ($j = 0; $j < $string_2_length; $j++){
      // Check every combination of characters
      if ($string_1[$i] === $string_2[$j]){
        // These are the same in both strings
        if ($i === 0 || $j === 0){
          // It's the first character, so it's clearly only 1 character long
          $longest_common_subsequence[$i][$j] = 1;
        } else{
          // It's one character longer than the string from the previous character
          $longest_common_subsequence[$i][$j] = $longest_common_subsequence[$i - 1][$j - 1] + 1;
        }
        
        if ($longest_common_subsequence[$i][$j] > $largest_size){
          // Remember this as the largest
          $largest_size = $longest_common_subsequence[$i][$j];
          // Wipe any previous results
          $return       = "";
          // And then fall through to remember this new value
        }
        
        if ($longest_common_subsequence[$i][$j] === $largest_size){
          // Remember the largest string(s)
          $return = substr($string_1, $i - $largest_size + 1, $largest_size);
        }
      }
      // Else, $CSL should be set to 0, which it was already initialized to
    }
  }
  // Return the list of matches
  return $return;
}

function getLCS(){
  /*
  // 共通部分を削除
  //   n個の値を持つ配列 $arr[0] に対して、
  //   $arr[0][n]:$arr[0][n+1]の最長共通文字列部分を$n回検索することで共通部分を同定
  
  // 前準備：$arr[0]作成
  foreach ($tweets as $tweet){
    $arr[0][] = $tweet["comment"]["content"];
  }
  
  // 共通部分探索
  $max = (count($arr[0]) >= 5) ? 5 : count($arr[0]);
  if ($max > 1){
    for ($j=0; $j<$max-1; $j++){
      $k = -$j+($max-1);
      for ($i=0; $i<$k; $i++){
        $arr[$j+1][] = $this->get_longest_common_subsequence($arr[$j][$i], $arr[$j][$i+1]);
      }
    }
    $common = $arr[$max-1][0];
  } else{
    $common = "";
  }
}

function getHatenaStar2Bookmark(){
  
  // コメントが多い場合、すべてのコメントへのスターをサーバサイド側で
  // GETリクエストで受け取るのが実行時間的に現実的でないため
  // → クライアントサイドで処理
  
  // スター取得
  $i=0;
  foreach ($bookmarks as $bookmark){
    $star_queries[] = $bookmark["comment"]["url"];
    // URLの長さ制限上、20URLずつに分割
    if ($i % 20 == 0 && $i != 0){
      $star_url = "http://s.hatena.com/entry.json?".http_build_queries($star_queries);
      $stars = json_decode(file_get_contents($star_url));
      // スター数をカウントとして配列に追加
      foreach ($stars->entries as $star){
        $star_db[] = count($star->stars);
      }
      unset($star_queries);
    }
    $i++;
  }
  
  if (count($bookmarks) ==count($star_db)){
    $i = 0;
    foreach ($bookmarks as $bookmark){
      $uid = $bookmark["user"]["nick"];
      $bookmarks[$uid]["comment"]["reaction"]["favorite"] = $star_db[$i];
      $i++;
    }
    
    // 配列並び替え
    $bookmarks = $this->sortArray($bookmarks);
  }
}

*/

?>