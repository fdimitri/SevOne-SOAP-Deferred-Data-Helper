<?php
if (!isset($_GET['id'])) {
	echo "Please specy id=";
}
$devid = intval($_GET['id']);
//echo "id={$devid}";
if (!$devid) {
	echo "Invalid id option";
	$devid=3431;
}
$rHTML = 0;
if (isset($_GET['rhtml']) && $_GET['rhtml'] == 1) {
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
	echo "<table><tr><th>Enabled</th><th>Hidden</th><th>Deleted</th><th>OS</th><th>Object Name</th><th>Object Desc</th><th>Object Type</th></tr>";
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
try {
	$SHOW_ALL = 0;
	$devices = $client->core_getDevices();
	if( !$devices ) {
		if (!$rHTML) echo "!!! Could not get any device information.\n";
		exit( 1 );
	}
	$devices = array($client->core_getDeviceById($devid));
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

		foreach( $plugins as $pluginString ) {
			if ($pluginString != 'COC') {
				//echo "   Plugin: {$pluginString}\n";
				$pluginFunctionPrefix = $client->helper_getPluginFunctionPrefix( $pluginString );
				$functionName = "{$pluginFunctionPrefix}getObjectsByDeviceId";
				if ($rHTML) {
					echo "<tr><td colspan=7 style='background-color: #000;color:#88f;text-shadow:1px 1px 3px rgba(255,255,255,0.8);'>{$pluginString}</td></tr>";
				}
				try {
					$objects = $client->$functionName($device->id);
					foreach ($objects as $object) {
						$ofunctionName = "{$pluginFunctionPrefix}getObjectTypeById";
						//$otype = $client->$ofunctionName($object->objectTypeId);
						$otype = getObjectType($client, $ofunctionName,$object->objectTypeId);
						//$os = $client->core_getOperatingSystemById($otype->osId);
						$os = getOs($client, $otype->osId);
//						$mystr = "{$device->name},{$os->name},{$object->name},{$object->description},{$otype->name}";
						$mystr = "{$object->isEnabled}:::{$object->isHidden}:::{$object->isDeleted}:::{$os->name}:::{$object->name}:::{$object->description}:::{$otype->name}";
						if ($rHTML) {
							//$mystr = "{$device->name}:::{$os->name}:::{$object->name}:::{$object->description}:::{$otype->name}:::{$otype->isEnabled}";
							$trStyle = '';
							if ($object->isDeleted) {
								$trStyle = 'background-color:rgba(255,0,0,1.0);';
							}
							$mystr = '<tr class = "' . $trStyle . '"><td>' . str_replace(':::','</td><td>', $mystr) . '</td></tr>'; 
						}
						else {
							$mystr = str_replace(':::',',',$mystr);
						}
						echo $mystr . "\n";
						//print_r($object);
					}
				} catch (Exception $e) {
					//echo "Exception! {$e}\n";
				}
			}
		}
		$stats[$device->name]['avg_ipero'] = round($stats[$device->name]['indicators'] / $stats[$device->name]['objects'],  2);
		$skey = $device->name;
		$sval = $stats[$skey];
		//print_r($objCache);
		//print_r($stats);
		//echo "{$skey}, {$sval['id']}, {$sval['ip']}, {$sval['objects']}, {$sval['indicators']}, {$sval['avg_ipero']}\n";
		//print_r($stats);
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

