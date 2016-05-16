<?php
include_once '../shared/sowrapper-3.0.14.php';
$sodw = new soDeferredDeviceWrapper("172.17.0.13", "/soap3/api.wsdl", "Admin", "SevOne", 0, 0);
$sodw->qLogLevel(SODW_WARN | SODW_ERROR);

$battleTag = "Casyl";
$battleTagCode = "1342";

$hCurl = curl_init();
curl_setopt($hCurl, CURLOPT_URL, "http://us.battle.net/api/d3/profile/{$battleTag}-{$battleTagCode}/");
curl_setopt($hCurl, CURLOPT_RETURNTRANSFER, 1);
$output = curl_exec($hCurl);
curl_close($hCurl);

print_r($output);

$jOutput = json_decode($output);
//print_r($jOutput);

print_r("--------------------\n");
$sodw->soInit();
$sodw->setDeviceName("$battleTag-$battleTagCode");
foreach ($jOutput->heroes as $hkey => $hval) { 
  foreach ($hval as $dhkey => $dhval) {
    $newHero["$battleTag-$battleTagCode"][$hval->name][$dhkey] = $hval->$dhkey;
  }
  $newHero["$battleTag-$battleTagCode"][$hval->name]['soType'] = 'D3Char';
}
foreach ($jOutput->kills as $hkey => $hval) {
  $newHero["$battleTag-$battleTagCode"]['kills'][$hkey] = $hval;
  $newHero["$battleTag-$battleTagCode"]['kills']['soType'] = 'D3Kills';
}

foreach ($newHero as $battleTag => $bData) {
  foreach ($bData as $okey => $oval) {
    foreach ($oval as $ikey => $ival) {
      if ($ival ===  TRUE) $ival = 1;
      if ($ival ===  FALSE) $ival = 0;
      if ($ikey != 'soType' && is_integer($ival)) {
	$sodw->attachIndicatorToObject($okey,  $okey, $oval['soType'], array('name' => $ikey,  'value' => $ival,  'type' => 'GAUGE',  'unit' => 'number'));
      }
    }
  }
}
$t = $sodw->getInternalData();
if ($sodw->preVerify()) $sodw->pushData();
print_r($t);
die;
//print_r($newHero);

die;


foreach ($newHero as $battleTag) {
  foreach ($battleTag as $battleTagCode) {
    foreach ($battleTagCode as $hkey => $hval) {
      foreach ($hval as $ikey => $ival) {	
	    print_r($hval);
      }
    }
  }
}

die;


?>