<?php
if (isset($_GET['dev'])) $devm = ($_GET['dev']);

//echo "id={$devid}";

$rHTML = 1;
if (isset($_GET['rhtml'])) $rHTML = $_GET['rhtml'];
if ($rHTML == 1) {
	echo '
	<style type="text/css">
	table {
		border-collapse:collapse;
	}
	tr {
	}
	td {
		border:1px solid black;
		padding:4px;
		padding-left:8px;
		padding-right:8px;
		
	}
	tr:nth-child(even) {
		background-color: rgba(224,224,255,0.8);
	}
	caption {
		text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
	}
	th { 
		text-shadow: 1px 1px 2px rgba(0,0,0,0.5);
	}
	</style>
	';
	$rHTML = 1;
}
else {
	echo "<pre>";
	echo "Enabled,Hidden,Deleted,OS,Object Name,Object Description,Object Type\n";
}
ini_set("soap.wsdl_cache_enabled", 1);
$soapIp = "pm1.srv.vm.frankd.lab";
$soapUrl = "http://{$soapIp}/soap3/api.wsdl";
$objCache = array();
$osCache = array();
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
echo "<table><tr><th>Device name</th><th>Device Id</th><th>Device IP</th><th>OS Name</th></tr>";
try {
	$SHOW_ALL = 0;
	$devices = $client->core_getDevices();
	if( !$devices ) {
		if (!$rHTML) echo "!!! Could not get any device information.\n";
		exit( 1 );
	}
	$devices = $client->core_getDevices();
	if (isset($devices) && count($devices)) foreach( $devices as $device ) {
		if ($devm && preg_match("/{$devm}/", $device->name)) {
			$stats[$device->name]['id'] = $device->id;
			$a =& $stats[$device->name];
			$stats[$device->name]['ip'] = $device->ip;
			$stats[$device->name]['objects'] = $device->elementCount;
			$os = getOs($client, $device->osId);
			$stats[$device->name]['os'] = $os->name;
			$n = $device->name;
			$mystr = "{$n}:::";
			$mystr .= "{$a['id']}:::";
			$mystr .= "{$a['ip']}:::";
			$mystr .= "{$a['os']}";
			$trStyle = "";
			$mystr = '<tr class = "' . $trStyle . '"><td>' . str_replace(':::','</td><td>', $mystr) . '</td></tr>'; 
			echo $mystr . "\n";
		}
	}
} catch( Exception $e ) {
	echo "Exception:\n";
	print_r( $e );
	exit( 1 );
}
if ($rHTML) {
	echo "</table>";
	echo "<table><caption>Object Cache Hits</caption>";
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

}
else echo "</pre>";

function getObjectType($client, $func, $id) {
	global $objCache;
	if (isset($objCache[$func][$id]['r'])) {
		$objCache[$func][$id]['cc'] += 1;
		return ($objCache[$func][$id]['r']);
	}
	if (!$client) return(NULL);
	$objCache[$func][$id]['cc'] = 0;
	$objCache[$func][$id]['r'] = $client->$func($id);
	return ($objCache[$func][$id]['r']);
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

