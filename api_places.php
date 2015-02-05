<?php

/*
function get_places_info($lang) {
  return array(
    array(
      'name' => 'Místo konání',
      'description' => 'Areál FIT VUT v Brně',
      'icon' => 'http://pcmlich.fit.vutbr.cz/openalt/icons/openalt40.png',
      'lat' => 49.226211,
      'lon' => 16.596886,
    ),
    array(
      'name' => 'Restaurace',
      'description' => '',
      'icon' => 'http://pcmlich.fit.vutbr.cz/openalt/icons/openalt40.png',
//      'icon' => 'http://pcmlich.fit.vutbr.cz/openalt/icons/bar-24.png',
      'lat' => 49.190030,
      'lon' => 16.606148,
    ),
    array(
      'name' => 'Industra',
      'description' => '',
      'icon' => 'http://pcmlich.fit.vutbr.cz/openalt/icons/openalt40.png',
//      'icon' => 'http://pcmlich.fit.vutbr.cz/openalt/icons/bar-24.png',
      'lat' => 49.182489, 
      'lon' => 16.626991,
    ),
  );
}
*/

function get_places_info($lang) {
  require_once('xml.php');
  //$kml = file_get_contents('../../devconfmap/static/RedHatDeveloperConference.kml');
  $kml = file_get_contents('RedHatDeveloperConference.kml');
  
  $default_icon =  'http://www.devconf.cz/devconfmap/static/icons/marker-icon.png';
  
  $kml = xmlstr2array($kml);
  $icons = array();  
  foreach ($kml['kml']['Document']['Style'] as $style) {
    if (isset($style['IconStyle']['Icon']['href'])) {
      $href = $style['IconStyle']['Icon']['href'];
      if (!file_exists("../../devconfmap/".$href)) {
        continue;
      }
      if (preg_match("/static\/icons\/(.+).png/", $href, $match)) {
        if (strpos($href, "http") === false) {
          $href = "http://www.devconf.cz/devconfmap/".$href;
        }
        $icons['#'.$match[1]] = $href;
      }
    }
  }
  $places = array();
  foreach ($kml['kml']['Document']['Placemark'] as $placemark) {
    list($lon, $lat, $alt) = explode(",", $placemark['Point']['coordinates']);
    $placeIcon =  (isset($icons[$placemark['styleUrl']])) ? $icons[$placemark['styleUrl']] : $default_icon;
    array_push($places, array(
      'name' => $placemark['name'],
      'description' => (isset($placemark['description']) && is_string($placemark['description'])) ? strip_tags($placemark['description'], '<br><p><div><a>') : '',
      'icon' => $placeIcon,
      'lat' => $lat,
      'lon' => $lon,
    ));
  }
  return $places;
}

?>