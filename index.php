<?php
date_default_timezone_set ('Europe/Prague');
require('xml.php');

$SCHEDORG_URL = "http://developerconference2014.sched.org/api/";
$SCHEDORG_RONLY_API_KEY = "d0cfd220bb1bd8ed848b718def0a35f8";

$params = array(
  'api_key' => $SCHEDORG_RONLY_API_KEY,
  'format' => 'json',
);



if ((!file_exists('_user.json')) || (filectime('_users.json')+30*60 < time())) {
//  echo "older than 30 minutes <br/>\n";
  $sessions_json = download_data($SCHEDORG_URL . "session/list", $params);
  $users_json = download_data($SCHEDORG_URL . "user/list", $params);
  $rss_xml = file_get_contents("http://www.devconf.cz/main-page-news.xml");


  $sessions_json_old = file_get_contents('_sessions.json');
  $users_json_old = file_get_contents('_users.json');
  $rss_xml_old = file_get_contents("_rss.xml");

  file_put_contents('_users.json', $users_json);
  file_put_contents('_sessions.json', $sessions_json);
  file_put_contents('_rss.xml', $rss_xml);
}

$sessions_json = file_get_contents('_sessions.json');
$users_json = file_get_contents('_users.json');
$rss_xml = file_get_contents("_rss.xml");
$now = file_exists('data_ts.json') ? file_get_contents('data_ts.json') : time();


function download_data($url, $params) {
  $ch = curl_init(); 
  if (!$ch) {
    die("curl error\n");
  }

  curl_setopt($ch, CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
  curl_setopt($ch, CURLOPT_URL, $url );
  curl_setopt($ch, CURLOPT_HEADER, false);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

  $server_output = curl_exec ($ch);

  curl_close ($ch);

  return $server_output;
}

//$data = download_data($SCHEDORG_URL . "sessions/list", $params);


function parseTime($t) {
  if (preg_match("/(\d\d\d\d)-(\d\d)-(\d\d) (\d\d):(\d\d):(\d\d)/", $t, $m)) {

//    $move_event = 1391727600 - time() + 1689*3600 + 10*60; // like 9am -10 min
    $move_event = 0;
    return mktime($m[4], $m[5], $m[6], $m[2], $m[3], $m[1]) - $move_event;
  }
  return 0;
}

function sanitizeTopic($t) {
  return (strpos($t, ' - ') !== false) ? substr($t, 0 , strpos($t, ' - ')) : $t;
}

function talk_type($name) {
  if (preg_match('/Lab room /', $name)) {
    return 'Lab';
  }
  if (preg_match('/Workshop room /', $name)) {
    return 'Workshop';
  }

//  if (preg_match('/Lecture room /', $name)) 
    return 'Talk';
}

function room_short($name) {
  $patterns = array(
    '/Lab room /',
    '/Lecture room /',
    '/Workshop room /',
  );
  $replacements = array(
    '',
    '',
    '',
  );
  return  preg_replace($patterns, $replacements, $name);
}


function room_color($short) {
 $array = array(
  'D1' => '#fce94f',
  'D2' => '#fcaf3e',
  'D3' => '#8ae234',
  'L1' => '#729fcf', 
  'L2' => '#ef2929',
  'L3' => '#ad7fa8'
 );
  return $array[$short];

}

$sessions_d = json_decode($sessions_json, true);
$users = json_decode($users_json, true);

$i=0;

$sessions = array();

$days = array();


foreach ($sessions_d as $idx => $item) {
 // echo "<pre>".print_r($item, true)."</pre>";
  $out = array();
  $out['type'] = talk_type($item['venue']);
  $event_start = parseTime($item['event_start']);
  $out['event_start'] = $event_start;
  $out['event_start_rfc'] = date('r', $event_start);
  $out['event_end']   = parseTime($item['event_end']);
  $out['event_end_rfc'] = date('r', $out['event_end']);
  $out['room'] = $item['venue'];
  $out['room_short'] = room_short($item['venue']);
  $out['room_color'] = room_color($out['room_short']);
  $out['topic'] = sanitizeTopic($item['name']);

  $out['speakers'] = isset($item['speakers']) ? explode(",", $item['speakers']) : array();
  foreach ($out['speakers'] as $i => $s) { $out['speakers'][$i] = trim($s); }
  $out['description'] = isset($item['description']) ? strip_tags($item['description']) : 'N/A';
  $out['tags'] = array( );
  if (isset($item['event_type'])) { array_push($out['tags'], $item['event_type']); }
  $out['location'] = 'MUNI';
  $out['lang'] = 'EN';

  array_push($sessions, $out);
  array_push($days, mktime(0,0,0, date('n', $event_start), date('j', $event_start), date('Y', $event_start)));
//  if ($i++ > 30)  exit();
}
$days = array_values(array_unique($days));

function session_sortfn($a, $b) {
    if ($a['event_start'] == $b['event_start']) {
        if ($a['room_short'] == $b['room_short']) {
          return 0;
        }
        return ($a['room_short'] < $b['room_short']) ? -1 : 1;
    }
    return ($a['event_start'] < $b['event_start']) ? -1 : 1;
}

usort($sessions, "session_sortfn");

$rss_data = xmlstr2array($rss_xml);

$rss =array();


foreach ($rss_data['rss']['channel']['item'] as $item) {
  $out = array();
  $out['title'] = $item['title'];
  $out['link']  = $item['link'];
  $out['description'] = strip_tags($item['description']);
  $out['time'] = strtotime($item['pubDate']);
  array_push($rss, $out);
//  echo "<pre>".print_r($item, true)."</pre>";
}
//echo "<pre>".print_r($rss, true)."</pre>";
//exit();

//////////////////////////////////////////////////////////////////////////////////


$all_data = array(
  'sessions' => $sessions,
  'users' => $users,
  'days' => $days,
  'rss' => $rss,
);



$all_data['about'][0]['title'] = 'About';
$all_data['about'][0]['text'] = "Developer Conference is a yearly conference for all Linux and JBoss Developers, Admins and Linux users organized by Red Hat Czech Republic, the Fedora and JBoss Community. 2013 DevConf had over 60 talks in 3 different tracks and additional to that 3 more rooms with workshops and hackfests. With around 600 participants DevConf.cz is one of the biggest events about free software in the Czech Republic.";
$all_data['about'][1]['title'] = 'Venue';
$all_data['about'][1]['text'] = "
Brno is by population the second largest also the largest city of Moravia and historic former capital of Moravia. Additionally, it is also the center of the judiciary,  located there are the seats of the Constitutional Court, Supreme Court, Supreme Administrative Court and Supreme Public Prosecutor's Office. Besides, it is very important administrative center, because the state authorities with national enforcement powers and other important institutions reside there. Brno is also one of the most important university cities of the Czech Republic. It is home of 13 universities and colleges, which are attended by more than 90 000 students. The biggest one is Masaryk University, with more then 30.000 students. It is Faculty of Informatics of this University that hosts DevConf.cz.<br/>
Please note that the Faculty is currently under reconstruction. The new entrance is on the back part of the building, closer to the conference rooms.<br/><br/>
Address of the faculty is: Masarykova univerzita Brno - Fakulta informatiky Botanická 554/68a, 602 00 Brno Czech Republic +420 549 491 810 The faculty on <a href=\"geo:49.21,16.599201\">openstreetmap</a> and on <a href=\"http://maps.google.com/maps?f=q&hl=en&geocode=&q=Fakulta+Informatiky+Masarykovy+Univerzity,+Brno&sll=37.0625,-95.677068&sspn=37.598824,91.054688&ie=UTF8&cd=1&ll=49.209944,16.598947&spn=0.007584,0.02223&z=16&iwloc=addr\">googlemaps</a>.";


$all_data['about'][2]['title'] ='Accommodation';
$all_data['about'][2]['text']  = "For conference participants, we ensured  hotel accommodation in Avanti hotel ****, which is located a 5-10 minute walk from the venue. You are going to stay in comfortable double-bed rooms with breakfast and free parking in front of the hotel. A special rate for conference attendees is 1400 CZK (€55/$72) for a double-bed room per night. There is free wi-fi, restaurant services, cafe, bowling, and wellness services. <br/><br/>
Avanti hotel<br/><br/>
The booking for DevConf 2014 will start soon.<br/><br/>
Other recommended hotels in Brno are:<br/><br/>
    Continental ****<br/>
    Vista Hotel ***<br/>
    A-Sport Hotel *** (very close to the Red Hat office)
";



$old_data = json_decode(file_get_contents('data.json'), true);

$all_data['checksum'] = sha1(json_encode($all_data));
$all_data['timestamp'] = $now;


if (($old_data['checksum'] != $all_data['checksum']) || isset($_REQUEST['force_update'])) {
  $now = time(); // data are new, update timestamp

  $all_data['timestamp'] = $now; 
  file_put_contents('data.json', json_encode($all_data));
  file_put_contents('data_ts.json', $now);

}

if (isset($_REQUEST['json'])) {
  echo json_encode($all_data);
  exit();
}
if (isset($_REQUEST['json_ts'])) {
  echo $now;
  exit();
}


echo "<br/> &mdash; <br/>";

echo "<pre>".print_r($all_data, true)."</pre>";

echo "<br/> &mdash; <br/>";

?>