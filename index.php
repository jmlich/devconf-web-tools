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
require_once('xml.php');
require_once('api_places.php');

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
  unset($out['hash']);
  $out['hash'] = sha1(json_encode($out));

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
  'places' => get_places_info('en'),
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
if (isset($_REQUEST['ts'])) {
  header('content-type: application/json');
  echo $now;
  exit();
}

if (isset($_REQUEST['json_ts'])) {
  header('content-type: application/json');
  echo $now;
  exit();
}


echo "<br/> &mdash; <br/>";

echo "<pre>".print_r($all_data, true)."</pre>";

echo "<br/> &mdash; <br/>";
$rooms = array_unique($rooms);
echo "<pre>",print_r($rooms, true)."</pre>";

?>