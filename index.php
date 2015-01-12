<?php
/*
if (isset($_REQUEST['json_ts'])) {
  $now = file_exists('data_ts.json') ? file_get_contents('data_ts.json') : time();
  echo $now;
} else {
  echo file_get_contents('data.json');
}
exit();
*/
///////////////////////////////////////////////////////////////////////////////////////////////////////////

date_default_timezone_set ('Europe/Prague');
require('xml.php');

$SCHEDORG_URL = "http://developerconference2015.sched.org/api/";
$SCHEDORG_RONLY_API_KEY = "a4248e8ab815813c08ee1a979389da38";

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

$rooms = array();
function room_short($name) {
  $patterns = array(
    '/Lab room /',
    '/Lecture room /',
    '/Workshop room L1 - B410/',
    '/Workshop room L2 - C525/',
    '/Workshop room L3 - C511/',
    '/Workshops – A113/',
    '/Workshops – A112/',
    '/Workshops – E105/',
  );
  $replacements = array(
    '',
    '',
    'L1',
    'L2',
    'L3',
    'A113',
    'A112',
    'E105',
  );
  global $rooms;
  array_push($rooms,  $name);

  return  preg_replace($patterns, $replacements, $name);
}


function room_color($short) {
 $array = array(
  'D105' => '#ff0000',
  'D0206' => '#00ff00',
  'D0207' => '#0000ff',
  'A112' => '#ffff00', 
  'A113' => '#00ffff',
  'E104' => '#9999ff',
  'E105' => '#ff9999',
  'E112' => '#99ff99',
  '' => '#ffffff',
 );

  if (!array_key_exists($short, $array)) {
    $short = '';
  }
  return $array[$short];

}

$sessions_d = json_decode($sessions_json, true);
$users = json_decode($users_json, true);

$i=0;

$sessions = array();

$days = array();


foreach ($sessions_d as $idx => $item) {
 // echo "<pre>".print_r($item, true)."</pre>";


  $venue = isset($item['venue']) ? $item['venue'] : '';
  $out = array();
  $out['type'] = talk_type($venue);
  $event_start = parseTime($item['event_start']);
  $out['event_start'] = $event_start;
  $out['event_start_rfc'] = date('r', $event_start);
  $out['event_end']   = parseTime($item['event_end']);
  $out['event_end_rfc'] = date('r', $out['event_end']);
  $out['room'] = $venue;
  $out['room_short'] = room_short($venue);
  $out['room_color'] = room_color($out['room_short']);
  $out['topic'] = sanitizeTopic($item['name']);

  $out['speakers'] = isset($item['speakers']) ? explode(",", $item['speakers']) : array();
  foreach ($out['speakers'] as $i => $s) { $out['speakers'][$i] = trim($s); }
  $out['description'] = isset($item['description']) ? strip_tags($item['description']) : 'N/A';
  $out['tags'] = array( );
  if (isset($item['event_type'])) { array_push($out['tags'], $item['event_type']); }
  $out['location'] = '';
  $out['lang'] = 'EN';

  array_push($sessions, $out);
  array_push($days, mktime(0,0,0, date('n', $event_start), date('j', $event_start), date('Y', $event_start)));
//  if ($i++ > 30)  exit();
}
$days = array_values(array_unique($days));
sort($days);

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
  $out['avatar'] = 'http://devconf.cz/wall/img/avatar3.png';
  array_push($rss, $out);
//  echo "<pre>".print_r($item, true)."</pre>";
}


function rss_sort_function($a, $b) {
    if ($a['time'] == $b['time']) {
        return 0;
    }
    return ($a['time'] > $b['time']) ? -1 : 1;
}


usort($rss, "rss_sort_function");
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
$all_data['about'][0]['text'] = <<<EOF
<p>Developer Conference is a yearly conference for all Linux and JBoss Developers, Admins and Linux users organized by Red Hat Czech Republic, the Fedora and JBoss Community. 2014 DevConf had over 100 talks in 3 different tracks and additional to that 3 more rooms with workshops and hackfests. With around 1000 participants <a href="http://www.devconf.cz">DevConf.cz</a> is one of the biggest events about free software in the Czech Republic.</p>
<p>Developer Conference 2015 will start on Friday February 6h and last till Sunday February 8th. It will be hosted on <a href="http://devconf.cz/city-and-venue">Brno University of Technology - Faculty of Information Technology</a>.</p>
<p>You might check out photos, videos and blogposts about past conferences.</p>
<p><strong>The conference has always been with no admission! There is no registration.</strong></p>
EOF;
$all_data['about'][1]['title'] = 'Venue';
$all_data['about'][1]['text'] = <<<EOF

<p><a href="http://brno.cz/index.php?lan=en" target="_blank">Brno</a> is by population the second largest also the largest city of Moravia and historic former capital of Moravia. Additionally, it is also the center of the judiciary,&nbsp; located there are the seats of the Constitutional Court, Supreme Court, Supreme Administrative Court and Supreme Public Prosecutor&#39;s Office. Besides, it is very important administrative center, because the state authorities with national enforcement powers and other important institutions reside there. Brno is also one of the most important university cities of the Czech Republic. It is home of 13 universities and colleges, which are attended by more than 90 000 students. The oldest one is <a href="http://www.vutbr.cz/en/">Brno University of Technology</a>&nbsp;(est. 1899), with more then 24.000 students. It is <a href="http://www.fit.vutbr.cz/.en">Faculty of Information Technology</a>&nbsp;of this University that hosts DevConf.cz.</p>

<p>Address of the faculty is: Faculty of Information Technology, Brno University of Technology, Božetěchova 1/2, 612 66 Brno, Czech Republic.&nbsp;The faculty on&nbsp;<a href="http://www.openstreetmap.org/#map=18/49.22688/16.59696" target="_blank">openstreetmap</a> and on <a href="https://www.google.com/maps/place/Fakulta+informačních+technologií+VUT+v+Brně,+Vysoké+učení+technické+Brno,+Božetěchova+1%2F2,+612+00+Brno-Brno-Královo+Pole,+Česká+republika/@49.226544,16.597122,17z/data=%214m2%213m1%211s0x471294099dc06bbb:0xbfcf161b01a48b0d" target="_blank">googlemaps</a> and on <a href="geo:49.226544,16.597122">your device</a></p>


EOF;

$all_data['about'][2]['title'] ='Accommodation';
$all_data['about'][2]['text']  = <<<EOF

<p>For conference participants, we ensured&nbsp; hotel accommodation in <a href="http://www.brno-hotel-avanti.eu/">Avanti hotel</a> ****, which is located 5 tram stops from the venue (approx 6 minutes ride). You are going to stay in comfortable double-bed rooms with breakfast and <strong>free parking in front of the hotel</strong>. A special rate for conference attendees is 1500 CZK (~&euro;55/$74) for a double-bed room per night. There is free wi-fi, restaurant services, cafe, bowling, and wellness services.</p>

<p><img align="bottom" alt="Avanti hotel" border="0" height="150" name="Image1" src="data:image/jpeg;base64,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" width="305" /></p>

<p><strong>BOOKING IN AVANTI</strong>: Form will be available soon..</p>

<p>Note: Speakers that have an accepted talk or workshop (not a lightning talk) will have free accommodation in Avanti during conference days (from Thursday to Monday).</p>

<p>Other recommended hotels in Brno are:</p>

<ul>
    <li>
    <p style="margin-bottom: 0in"><a href="http://www.continentalbrno.cz/en/introduction.html">Continental</a> ****</p>
    </li>
    <li>
    <p style="margin-bottom: 0in"><a href="http://www.vista-hotel.cz/en/">Vista Hotel</a> ***</p>
    </li>
    <li>
    <p><a href="http://www.a-sporthotel.cz/hotel-brno-ubytovani/">A-Sport Hotel</a> *** (very close to the Red Hat office)</p>
    </li>
</ul>

<p style="margin-bottom: 0in; line-height: 100%">&nbsp;</p>

EOF;


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
  header('content-type: application/json');
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
$rooms = array_unique($rooms);
echo "<pre>",print_r($rooms, true)."</pre>";

?>