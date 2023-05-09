#!/usr/bin/php

<?php

function get_last_video($channel_id) {
    $xml_string = file("https://invidious.projectsegfau.lt/feed/channel/$channel_id");
    $xml_array = json_decode(json_encode(simplexml_load_string(implode("", $xml_string))), true);
    return array(
	"id" => $xml_array['entry'][0]['id'],
	"title" => $xml_array['entry'][0]['title'],
	"desc" => $xml_array['entry'][0]['content']['div']['p'],
	"img" => $xml_array['entry'][0]['content']['div']['a']['img']['@attributes']['src']
    );
}

function is_already_posted($username, $movieid) {
    $data = array(
        'jsonrpc' => '2.0',
        'method' => 'condenser_api.get_content',
        'params' => array(
            $username,
            $movieid
        ),
        'id' => 1
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://blurt-rpc.saboin.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, TRUE)['result']['author'];
    if($data==trim($username)) {
	return true;
    } else {
	return false;
    }
}

function create_post($nick, $permlink, $category, $title, $body, $json, $posting) {

    $node[0] = 'https://blurt-rpc.saboin.com';
    srand(microtime(true)*1000000);
    $random_node = $node[rand(0, count($node)-1)];
    $var = shell_exec("echo \"var blurt = require('@blurtfoundation/blurtjs'); blurt.api.setOptions({url: '$random_node'}); body=\`$body\`; blurt.broadcast.comment('$posting', '', '$category', '$nick', '$permlink', '$title', body, '$json', function(err, result) { console.log(err, result); });\" | nodejs");
    return $var;
}



$db = new SQLite3('database.sqlite3');
$query = "SELECT * FROM crossposter WHERE enable=1";
$result = $db->query($query);
$rows = array();
while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
    $rows[] = $row;
}

for($i=0; $i<=count($rows)-1; $i++) {
	$username = $rows[$i]['blurt_username'];
	$password = $rows[$i]['blurt_password'];
	$moviedata = get_last_video($rows[0]['yt_channelid']);
	$title = $moviedata['title'];
	$movieid = str_replace('yt:video:', '', $moviedata['id']);
	$permlink = strtolower($movieid);
	$body = 'https://www.youtube.com/watch?v='.$movieid.'<br><br>'.nl2br($moviedata['desc']);
	$img = $moviedata['img'];
	$json_metadata = json_encode(array());
	if(!is_already_posted($username, $movieid)) {
		echo create_post($username, $permlink, 'video', $title, $body, $json_metadata, $password);
	}
}