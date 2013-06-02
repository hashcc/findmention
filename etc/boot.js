$(function(){
  // はてなスター数を設置/並替
  setStar();

  // 並び順変更
  $( 'input[name="sort"]:radio' ).change(function(){
    sortComment($(this).val());
  });
}); 

function setStar(){
  var query = "";
  var arr = {};

  for (var name in urls){
    var query = query + "&n["+name+"]="+urls[name];
    // URIの上限を超えない程度にクエリを詰めてリクエスト
    if (getByte(query) > 1700){
      getStar(query);
      query = ""; // reset
    }
  }
  // 残ったやつもリクエスト
  getStar(query);
}

function getStar(query){
  $.ajax({
    type: "GET",
    scriptCharset: 'utf-8',
    url: "proxy.php",
    data: query.substr(1),
    success: function(data){
      for (var i in data){
        $("span."+i).attr("title", data[i]);
        if (data[i] > 0){
          $("span."+i).html("★"+data[i]);
        }
      }
      sortComment("popular");
    }
  });
}

function sortComment(type){
  if (type == "popular"){
    $("div#mention_hatena div.comment").tsort('span.favorite',{attr:"title", order:"desc"});
    $("div#mention_twitter div.comment").tsort('span.favorite',{attr:"title", order:"desc"});
  } else if (type == "latest"){
    $("div#mention_hatena div.comment").tsort('span.timestamp',{attr:"title", order:"desc"});
    $("div#mention_twitter div.comment").tsort('span.timestamp',{attr:"title", order:"desc"});
  }
}

function getByte(text){
  count = 0;
  for (i=0; i<text.length; i++){
    n = escape(text.charAt(i));
    if (n.length < 4) count++; else count+=2;
  }
  return count;
}