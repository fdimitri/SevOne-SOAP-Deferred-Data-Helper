<?php
$startTime = microtime(TRUE);
$rHTML = 0; $showItypes = 0;
if (!strcmp($argv[1], '-www')) {
	$rhtml = 0;
	$devid = 912;
	$showItypes = 1;
}

if (!$devid && !isset($_GET['id'])) {
	echo "Please specy id=";
}
if (!isset($devid)) $devid = intval($_GET['id']);
//echo "id={$devid}";
if (!$devid) {
	echo "Invalid id option";
	die;
}
if (isset($_GET['show_itypes']) && $_GET['show_itypes'] == 1) {
	$showItypes = 1;
}
if ($rhtml || (isset($_GET['rhtml']) && $_GET['rhtml'] == 1)) {
	echo '
	<style type="text/css">
	div {
			border-radius:4px;
			border: 1px solid black;
			cursor: pointer;
	}
	.pt {
		margin-bottom:16px;
		font-size: 9pt;
		width: 90%;
		border-collapse:collapse;
		margin-left: auto; margin-right: auto;
	}
	.pt > tbody > tr {
	}
	.pt > tbody > tr > td {
		border:1px solid black;
		padding:4px;
		padding-left:8px;
		padding-right:8px;
		
	}
	.pt > tbody > tr:nth-child(4n+0) {
		background-color: rgba(224,224,255,0.8);
	}
	caption {
		border: 1px solid black;
		font-size:10pt;
		text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
		background-color: rgba(200,200,240,0.8);
	}
	.pt > tbody > tr > th { 
		font-size:10pt;
		text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
	}
	.ct {
		width: 100%;
		border-collapse: collapse;
	}
	.ct > tbody > tr > td {
		border: 1px solid black;
	}
	.ct > tbody > tr:nth-child(even) {
		background-color: rgba(224,224,255,0.5);
	}
	</style>
<link href="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/themes/start/jquery-ui.css" type="text/css" rel="Stylesheet" />
<script src="http://ajax.googleapis.com/ajax/libs/jquery/1.10.2/jquery.min.js" ></script>
<script src="http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.2/jquery-ui.min.js"></script>
	<script>
$(document).ready(function() {
	$(".pt > tbody > tr:nth-child(2n+0)").click(function() {
		var nitem = $(this).next("tr");
		console.log("Clicked tr");
		$(nitem).slideToggle();
	});
	$(".pt > tbody > tr > tx").click(function() {
		var nitem = $(this).closest("tr").next("tr");
		$(nitem).slideToggle();
		console.log("Clicked td");
	});
	$("#showAll").click(function() {
		console.log("showAll");
		$(".ityperow").show();
//		$(".pt > tbody > tr:nth-child(2n+1)").show();
	});
	$("#hideAll").click(function() {
		console.log("hideAll");
		$(".ityperow").hide();
//		$(".pt > tbody > tr:nth-child(2n+1)").hide();
	});
	$("#toggleAll").click(function() {
		console.log("toggleAll");
		$(".ityperow").toggle();
//		$(".pt > tbody > tr:nth-child(2n+1)").toggle();
	});

});
	</script>
	<div id="showAll">Show All</div><div id="hideAll">Hide All</div><div id="toggleAll">Toggle All</div>
	';
	$rHTML = 1;
}
else {
	echo "<pre>";
}
ini_set("soap.wsdl_cache_enabled", 1);
$soapIp = "172.17.0.13";
$soapUrl = "http://{$soapIp}/soap3/api.wsdl";
$objCache = array();
$osCache = array();
$indCache = array();
$iotCache = array();

$client = new SoapClient(
$soapUrl,
array(
// Let's debug in case we hit a snag.
'trace' => 1
)
);
if( !$client ) {
	if (!$rHTML)echo "!!! Could not connect to SOAP server at '{$soapUrl}'.\n";
	exit( 1 );
}

try {
	$result = $client->authenticate( 'USERNAME', 'PASSWORD');
	if( !$result ) {
		if (!$rHTML) echo "!!! Could not authenticate with the server.\n";
		exit( 1 );
	} else {
		$result = $client->getAuthenticatedUid();
	}
} catch( Exception $e ) {
	echo "Exception:\n";
	print_r( $e );
	exit( 1 );
}
try {
	$SHOW_ALL = 0;
	$devices = $client->core_getDevices();
	if( !$devices ) {
		if (!$rHTML) echo "!!! Could not get any device information.\n";
		exit( 1 );
	}
	$devices = array($client->core_getDeviceById($devid));
	echo "<table>";
	foreach( $devices as $device ) {
		$stats[$device->name]['id'] = $device->id;
		$stats[$device->name]['ip'] = $device->ip;
		$stats[$device->name]['objects'] = $device->elementCount;
		$stats[$device->name]['indicators'] = 0;
		$plugins = $client->core_getEnabledPluginsByDeviceId( $device->id );
		if( !$plugins ) {
			if (!$rHTML) echo "!!! Could not get any plugins for this device.\n";
			continue;
		}
		//$os = $client->core_getOperatingSystemById($device->osId);
		//print_r($os);
		$objs = $client->plugin_snmp_getObjectsByDeviceId($device->id);
		foreach ($objs as $obj) {
			$inds = $client->plugin_snmp_getIndicatorsByObject($device->id, $obj->name);
			echo "<tr><td>{$obj->name}</td></tr>\n";
			foreach ($inds as $i) {
				echo "<tr><td></td><td>{$i->id}</td><td>{$i->indicatorType}</td><td>{$i->datatableColumn}</td><td>{$i->oidExpression}</td></tr>\n";
//				print_r($i);
			}
		}
		//die;
		$stats[$device->name]['avg_ipero'] = round($stats[$device->name]['indicators'] / $stats[$device->name]['objects'],  2);
		$skey = $device->name;
		$sval = $stats[$skey];
		/*print_r($objCache);
		print_r($indCache);
		print_r($iotCache);*/
		//print_r($stats);
		//echo "{$skey}, {$sval['id']}, {$sval['ip']}, {$sval['objects']}, {$sval['indicators']}, {$sval['avg_ipero']}\n";
		//print_r($stats);
	}
	echo "</table>";
} catch( Exception $e ) {
	echo "Exception:\n";
	print_r( $e );
	exit( 1 );
}
if ($rHTML) {
	echo "</table>";
	echo "<table><caption>Object Type Cache Hits</caption>";
	foreach ($objCache as $pFunc) {
		foreach ($pFunc as $o) {
			echo "<tr><td>{$o['r']->name}</td><td>{$o['cc']}</td></tr>";
		}
	}
	echo "</table>";
	echo "<table><caption>OS Cache Hits</caption>";
	foreach ($osCache as $o) {
		echo "<tr><td>{$o['r']->name}</td><td>{$o['cc']}</td></tr>";
	}
	echo "</table>";

	echo "<table><caption>Indicator Types By Object Type Cache Hits</caption>";
	foreach ($iotCache as $pkey => $pFunc) {
		foreach ($pFunc as $key => $o) {
			echo "<tr><td>{$objCache[$pkey][$key]['r']->name}</td><td>{$o['cc']}</td></tr>";
//			echo "<tr><td>{$key}</td><td>{$o['cc']}</td></tr>";
		}
	}
	echo "</table>";
	echo "<table><caption>Indicator Type Cache Hits</caption>";

	foreach ($indCache as $pkey => $pFunc) {
		foreach ($pFunc as $key => $o) {
			echo "<tr><td>{$o['r']->name}</td><td>{$o['cc']}</td></tr>";
		}
	}
	echo "</table>";
	$endTime = microtime(TRUE);
	$runTime = sprintf("%.4f", ($endTime - $startTime) / 1.0);
	echo "Total runtime: {$runTime}s\n";
//	echo "<pre>";print_r($objCache);print_r($iotCache);

}
else echo "</pre>";

function stripFunc($ofunc) {
	preg_match("/plugin_[^_]+/", $ofunc, $match);
	return($match[0]);
}

function getObjectType($client, $ofunc, $id) {
	global $objCache;
	$func = stripFunc($ofunc);
	if (isset($objCache[$func][$id]['r'])) {
		$objCache[$func][$id]['cc'] += 1;
		return ($objCache[$func][$id]['r']);
	}
	if (!$client) return(NULL);
	$objCache[$func][$id]['cc'] = 0;
	$objCache[$func][$id]['r'] = $client->$ofunc($id);
	return ($objCache[$func][$id]['r']);
}


function getIndicatorTypesFromObjectType($client, $ofunc, $id) {
	global $iotCache, $indCache;
	$func = stripFunc($ofunc);
	if (isset($iotCache[$func][$id]['r'])) {
		$iotCache[$func][$id]['cc'] += 1;
		return ($iotCache[$func][$id]['r']);
	}
	if (!$client) return(NULL);
	$iotCache[$func][$id]['cc'] = 0;
	$a = $client->$ofunc($id);
	$iotCache[$func][$id]['r'] = $a;
	foreach ($a as $i) {
		if (!isset($indCache[$func][$i->id])) {
			$indCache[$func][$i->id]['cc'] = 0;
			$indCache[$func][$i->id]['r'] = $i;
		}
	}
	return ($iotCache[$func][$id]['r']);
}


function getOs($client, $id) {
	global $osCache;
	if (isset($osCache[$id]['r'])) {
		$osCache[$id]['cc'] += 1;
		return ($osCache[$id]['r']);
	}
	if (!$client) return(NULL);
	$osCache[$id]['r'] = $client->core_getOperatingSystemById($id);
	$osCache[$id]['cc'] = 0;
	return ($osCache[$id]['r']);
}
/*foreach ($stats as $skey => $sval) {
	echo "{$skey}, {$sval['id']}, {$sval['ip']}, {$sval['objects']}, {$sval['indicators']}, {$sval['avg_ipero']}\n";
}*/

?>

