<?php

$q = null;
$s = null;

foreach($_GET["n"] as $user => $url){
  $q .= "&uri=".urlencode($url);
  $users[] = $user;
}

$u = "http://s.hatena.com/entry.json?".substr($q, 1);
$data = file_get_contents($u);
$data = json_decode($data);

for ($i=0; $i<count($data->entries); $i++){
  $user = $users[$i];
  $s .= '"'.$user.'": '.count($data->entries[$i]->stars).',';
}

$s = substr($s, 0, -1);
$s = "{".$s."}";
header("Content-type: application/json; charset=UTF-8;\n\n");
print_r($s);

?>