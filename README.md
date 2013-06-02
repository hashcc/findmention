# これは何？
- あるURLに対するはてなブックマークとTwitterのコメント情報を拾ってきて表示するウェブプログラムです。
- 純粋なコメント部分だけを抽出するため、タイトルや単純なリツイートなどは削除するのが特長です。
- API制限回避と高速化のため、コメント情報はキャッシュしており、10分以内の同一URLへのアクセスであればキャッシュを使います。

# どんな感じ？
こんな感じです。
http://git.openvista.jp/findmention/index.php?title=%E3%80%8C%E7%96%91%E3%82%8F%E3%81%AA%E3%81%84%E3%80%8D%E3%81%A8%E3%81%84%E3%81%86%E6%80%A0%E6%83%B0%E3%81%AB%E3%81%A4%E3%81%84%E3%81%A6%20-%20%EF%BC%92%EF%BC%94%E6%99%82%E9%96%93%E6%AE%8B%E5%BF%B5%E5%96%B6%E6%A5%AD&url=http://lkhjkljkljdkljl.hatenablog.com/entry/2013/06/01/232610

# どうやって使うの？
- 上記URLのように、表示したいURLとタイトルをRESTで指定します。自分のサーバーでホストし、自分のブログのコメント欄のあたりでiframeタグで呼び出して使うことを想定しています。
- Twitterに関しては、Twitter API 1.1を介してデータを取得しており、[Twitter Developers](https://dev.twitter.com/apps) からAPI利用キーを取得する必要があります。[Twitterクライアントアプリを登録する - Inhale n' Exhale](http://h2plus.biz/hiromitsu/entry/578) を参考に取得して、key.sample.phpに記入し、key.phpにファイル名を変更してください。

# ライセンス
MITライセンス

# 利用ライブラリ
- [Twitter oAuth](https://github.com/abraham/twitteroauth) by [Abraham Williams](http://abrah.am)