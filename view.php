<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="description" content="記事「<?php echo h($mentions->title); ?>」へのはてなブックマークやtwitterでの言及内容をまとめました" />
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<title>この記事への言及一覧</title>
<link type="text/css" rel="stylesheet" href="./etc/style.css" />
<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1/jquery.min.js"></script>
<script type="text/javascript"><?php echo $mentions->showCommentUser(); ?></script>
<script type="text/javascript" src="./etc/jquery.tinysort.min.js"></script>
<script type="text/javascript" src="./etc/jquery.tinysort.charorder.min.js"></script>
<script type="text/javascript" src="./etc/boot.js"></script>
<link rel="alternate" type="application/rss+xml" title="RSS" href="./?output=rss" />
</head>
<body>
<h1>この記事への言及一覧</h1>

<p class="mentions_count_sort">
<input type="radio" name="sort" id="popular" value="popular" checked="checked" /><label for="popular">人気順</label><input type="radio" name="sort" id="latest" value="latest" /><label for="latest">新着順</label>
</p>

<div class="mentions_content" id="mention_hatena">
<h2><a href="http://b.hatena.ne.jp/entry/<?php echo h(str_replace('http://', "", $mentions->url)); ?>"><img src="./etc/logo.hatenabookmark.min.png" width="32" height="32" alt="ロゴ" />はてなブックマーク</a></h2>
<div class="comments"><?php $mentions->showComment("hatena"); ?></div>
<div class="status"><a target="_blank" class="button" href="http://b.hatena.ne.jp/my/add.confirm?url=<?php echo urlencode($mentions->url); ?>"><img src="./etc/button.hatenabookmark.png" width="107" height="20" alt="ブックマーク登録" /></a><?php echo $mentions->showCommentCount("hatena"); ?></div>
</div>

<div class="mentions_content" id="mention_twitter">
<h2><a href="https://twitter.com/search?q=<?php echo urlencode($mentions->title); ?>"><img src="./etc/logo.twitter.min.png" width="32" height="32" alt="ロゴ" />Twitter</a></h2>
<div class="comments"><?php $mentions->showComment("twitter"); ?></div>
<div class="status"><a target="_blank" class="button" href="https://twitter.com/intent/tweet?original_referer=<?php echo urlencode($mentions->url); ?>&amp;text=<?php echo urlencode($mentions->title); ?>&amp;tw_p=tweetbutton&amp;url=<?php echo urlencode($mentions->url); ?>"><img src="./etc/button.twitter.png" width="73" height="20" alt="ツイート" /></a><?php echo $mentions->showCommentCount("twitter"); ?></div>
</div>

</body>
</html>