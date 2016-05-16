<?php
/* Sample usage:
$soTest = new soDeferredDeviceWrapper("sevone.server", "/soap3/api.wsdl", "username", "password", 0, 0);

if (!$soTest->soInit()) {
	echo "There was an error initializing the SevOne wrapper.\n";
	exit;
}

$soTest->setDeviceName("someaccessswitch.my.net");
$soTest->setScriptName("SO-TEST");
/* Do your data collection here -- here's some dummy data as an example */
$indicatorX = array("name" => "Indicator Name", "type" => 'GAUGE', "unit" => 'number', "value" => rand(10,100), "internal" => 0);
$indicatorY = array("name" => "Indicator Name", "type" => 'GAUGE', "unit" => 'number', "value" => rand(10,100), "internal" => 0);
$indicatorZ = array("name" => "Indicator Name", "type" => 'GAUGE', "unit" => 'number', "value" => rand(10,100), "internal" => 0);

/* Attach our data to Objects in the SevOne hierarchy */
$soTest->attachIndicatorToObject("Object Name", "Object Description", "ObjectType Name", $indicatorX);
$soTest->attachIndicatorToObject("Object Name", "Object Description", "ObjectType Name", $indicatorY);
$soTest->attachIndicatorToObject("Object Name", "Object Description", "ObjectType Name", $indicatorZ);


/* Create an array of indicators */
$indicators = array(
	array('name' => 'Ind1', 'type' => 'GAUGE', 'unit' => 'number', 'value' => rand(10,100)),
	array('name' => 'Ind2', 'type' => 'GAUGE', 'unit' => 'number', 'value' => rand(10,100)),
	array('name' => 'Ind3', 'type' => 'GAUGE', 'unit' => 'number', 'value' => rand(10,100)),
	array('name' => 'Ind4', 'type' => 'GAUGE', 'unit' => 'number', 'value' => rand(10,100)),
	array('name' => 'Ind5', 'type' => 'GAUGE', 'unit' => 'number', 'value' => rand(10,100)),
	);

/* And attach the entire array to an object! */
$soTest->attachIndicatorsToObject("Object2", "This is a second object", "Etherwebz", $indicators);	

$soTest->preVerify();
$soTest->pushData();

exit;

/* End Sample */


/*
05/14/14 - 3.0.1:
						* Basic functionality/caching

05/20/14 - 3.0.2 
						* Removed a bunch of incorrect print_r()s and changed them to logMsg(print_r(var, TRUE))

??/??/?? - 3.0.3 
						* Minor bugfixes apparently (no copies/repo entries exist)
						* Code commenting
						* Decouple soInit() connection, use soCreateConnection() to create the SOAP connection instead
						* Add getObjectsByDevice(), pushObjData()
						* Decouple pushData(), now calls pushObjData() per device for clarity

07/01/14 - 3.0.4: 
						* Allow switching of device within same instantion
						* Modify caching to include deviceId in array hash for intObjects[] and soObjects[] to accomodate this
						* Move some logic (connectPeer() to be called from soInit() OR soSetDeviceName())

07/03/14 - 3.0.5: 
						* Bugfixes for pushData()/insertDataRows(), some logic shuffling
						* Device agnosticism applied to rediscoverDevice()

07/31/14 - 3.0.6: 
						* Added makeIndicator() function
						* Added getInternalData() function to return object/indicator data before push for debugging purposes

08/05/14 - 3.0.7
						* Fixed some typos
						* Added some more logging for function entries

08/12/14 - 3.0.8
						* Fixed some major issues stopping peer mode from working
						* Other minor fixes
						* Removed this->client-> references to always use safer __call() with exception retry

08/13/14 - 3.0.9
						* Added deleteInternalObject() and updateObjectType()
						* Reordered sevException() && cException to properly be cException && sevException() -- which leads us to:
						* Fix check for sevException(TRUE) -- now checks for FALSE instead of ! (also matching 0), otherwise we fail with no exceptions!
						* Added option to auto-delete objects with types not matching what we were passed

08/14/14 - 3.0.10
						* We will now try to connect to multiple servers, specify multiples when calling 
							(ie instead of 'pm1.srv' array('pm1.srv', 'pm2.srv'))
						* We will now try to connect to the next HSA peer, if SevOne returns pm5 connect to pm6 -- if pm6, connect to pm5, etc
						* We don't really know which is the HSA peer, so this would have to be tweaked if used on the WiFi cluster (1a/1b instead)
						* Exception handling when instantiating SoapClient
						* sevException() can now reset exception, FALSE/no stack increments, TRUE queries, and any other value sets the exception counter
						* Juggled some connection code around

08/27/14 - 3.0.11
						* Added isValidIndicator(), removed more hacky tests
						* There are still some retry counters and static assignments randomly littered through, this should be cleaned up at some ponit
						* Fixed peer mode finally, pm3/pm4 pairs now work -- this part was broken before (would connect to peer, but tried pm7.srv/-1.srv
							bad parens
						* logMsg() checks logLevel bitmask before debug_backtrace(), not really necessary from a performance perspective but it is more
							correct
						* Incorrectly match pm(\d) instead of pm(\d+) fixed for peer mode
---

Atleast up to 3.0.14 exists with some fixes/improvements, where it goes.. nobody knows
*/

$soWrapperVersion = array('major' => 3, 'minor' => 0, 'revision' => 11, 'patch' => array(NULL), 'epoch' => 1409137627);

define("SODW_ALL",		 0xFFFF);
define("SODW_CACHE",	 0x0001);
define("SODW_WARN", 	 0x0002);
define("SODW_INFO", 	 0x0004);
define("SODW_ERROR",	 0x0008);
define("SODW_DEBUG",	 0x0010);
define("SODW_QUERY",	 0x0020);
define("SODW_SOAP", 	 0x0040);
define("SODW_FENTRY",  0x0080);
define("SODW_SODEBUG", 0x0100);


define("SO_NOEXIST",			0x00010000);
define("SO_MISMATCH",		0x00020000);
define("SO_FAILED",			0x00040000);
define("SO_CREATE",			0x00080000);
define("SO_GET",				0x00100000);
define("SO_UPDATE",			0x00200000);

define("SO_OBJECT",			0x00001000);
define("SO_DEVICE",			0x00002000);
define("SO_INDICATOR",		0x00004000);
define("SO_OBJECTTYPE",		0x00008000);
define("SO_INDICATORTYPE", 0x00000100);

define("SO_FAILED_OBJECTCREATE", SO_FAILED | SO_CREATE | SO_OBJECT);
define("SO_MISMATCH_OBJECTTYPE",	SO_MISMATCH | SO_OBJECTTYPE);
define("SO_FAILED_GETOBJECTTYPE", SO_FAILED | SO_GET | SO_OBJECTTYPE);
define("SO_NOEXIST_OT", SO_NOEXIST | SO_OBJECTTYPE);

class soDeferredDeviceWrapper {
	protected $soObjects, $soObjectTypes, $client;
	protected $options, $soIndicators, $soIndicatorTypes;
	protected $soapCounter, $intObjects;
	protected $sevExceptionCount;
	protected $soRediscoverCount = array();
	protected $deviceId;
	protected $deviceName;
	protected $scriptName = "UNSET";
	//protected $logLevel = 0xFFFFFFBE;
	protected $logLevel = 0xFFFFFFFF;
	//protected $logLevel = 0x00000040;
	protected $logKey = array(
		SODW_INFO  => "I",
		SODW_CACHE => "C",
		SODW_WARN  => "W",
		SODW_ERROR => "E",
		SODW_DEBUG => "D",
		SODW_QUERY => "Q",
		SODW_SOAP  => "S",
		SODW_FENTRY => "F",
		SODW_SODEBUG => "R",
	);
	
	public function __construct($server, $url, $login, $pass, $peerMode, $noInject) {
		$this->logMsg(SODW_FENTRY, "Called with  " . print_r($server, TRUE) . "{$url} {$login} {$pass} {$peerMode} {$noInject}");
		$this->options['max_exceptions'] = 12;
		$this->options['dont_inject'] = $noInject;
		$this->options['peer_mode'] = $peerMode;
		$this->options['server_hostname'] = $server;
		$this->options['server_url'] = $url;
		$this->options['login_username'] = $login;
		$this->options['login_password'] = $pass;
		$this->options['dont_cache_indicators'] = 1;
		$this->options['allow_cache'] = 1;
		$this->options['auto_delete_mismatch'] = 0;
		$this->qLogLevel(SODW_ALL & ~(SODW_DEBUG | SODW_SODEBUG));
	}
	
	public function qLogLevel($logLevel = FALSE) {
		if ($logLevel !== FALSE) $this->logLevel = $logLevel;
		return($this->logLevel);
	}
	
	public function getSoapCounter() {
		$this->logMsg(SODW_FENTRY, "Called");
		foreach ($this->soapCounter as $key => $val) {
			$ttotal = $tmax = $tmin = floatval(0.0);
			$tmin = 100.0;
			foreach ($val as $t) {
				$ttotal += $t;
				if ($t > $tmax) $tmax = $t;
				if ($t <= $tmin) $tmin = floatval($t);
			}
			if ($ttotal) $avgtime = floatval($ttotal )/ floatval(count($val));
			$nitem['avg'] = floatval($avgtime);
			$nitem['max'] = $tmax;
			$nitem['min'] = $tmin;
			$nitem['tot'] = $ttotal;
			$nitem['cnt'] = count($val);
			$items[$key] = $nitem;
		}
		return ($items);
	}
	
	public function setDeviceId($deviceId) {
		$this->logMsg(SODW_FENTRY, "Called with {$deviceId}");
		$this->deviceId = $deviceId;
		return(TRUE);
	}
	
	protected function connectPeer() {			
		$this->logMsg(SODW_FENTRY, "Called to connect!");
		$options = array('trace' => 1, 'connection_timeout'=>45, 'cache_wsdl'=>'1');
		if ($this->options['peer_mode']) {
			$cDev = $this->core_getDeviceById($this->deviceId);
			$cPeer = $this->core_getPeerById($cDev->peer);
			/* Edit these next few lines to fit your environment -- these should be configurable options*/	
			$newServer = "{$cPeer->name}.sevone.mydomain.lan";
			if (preg_match("/pm(?P<peer_num>\d+)/", $cPeer->name, $match)) {
				if ($match['peer_num'] % 2) {
					$otherServer = "pm" . ($match['peer_num'] + 1);
				}
				else {
					$otherServer = "pm" . ($match['peer_num'] - 1);
				}
				$otherServer .= ".sevone.mydomain.lan";
				$servers = array($newServer, $otherServer);
			}
			else {
				$servers = array($newServer);
			}
			$sWSDL = "/soap3/api.wsdl";
			$sAPI = "/soap3/api.php";
			$this->logMsg(SODW_INFO, "Connecting to {$newServer} or {$otherServer} (poller for {$this->deviceName})");
			$vclient = $this->soCreateConnection($servers, $options, $sWSDL, $sAPI);
			if (!$vclient) {
				$this->logMsg(SODW_ERROR, "Couldn't connect to peer SOAP server at {$soapUrl}, continuing on with initial SevOne server");
				$vclient = $client;
			}
			$this->client = $vclient;
		}
		else {
			$this->logMsg(SODW_FENTRY, "Peermode is not set, staying connected to primary");
		}
	}
	
	public function setDeviceName($deviceName, $findId = TRUE) {
		$this->logMsg(SODW_FENTRY, "Called to set to {$deviceName}");
		$this->deviceName = $deviceName;
		if ($findId && is_object($this->client)) {
			$cDevId = $this->core_getDeviceIdByName($this->deviceName);
			if ($cDevId) {
				$this->deviceId = $cDevId;
				$this->logMsg(SODW_INFO, "Device found, id: {$cDevId}, calling connectPeer()");
				$this->connectPeer();
			}
			else {
				$this->logMsg(SODW_ERROR, "Device not found: {$deviceName}");
				return(FALSE);
			}
		}
		$this->logMsg(SODW_INFO, "Enabling deferred data plugin for {$this->deviceId} {$this->deviceName}");
		$this->so_enablePluginForDevice($this->deviceId, 1);
		return(TRUE);
	}

	public function setScriptName($scriptName) {
		$this->logMsg(SODW_FENTRY, "Called to set to {$scriptName}");
		$this->scriptName = $scriptName;
		return(TRUE);
	}
	
	protected function sendMail($subject, $body) {
		$this->logMsg(SODW_FENTRY, "Called with {$subject} {$body}");
	}
	
	public function soRediscover() {
		$this->logMsg(SODW_FENTRY, "Called");
		if (!isset($this->soRediscoverCount[$this->deviceId]) || !($this->soRediscoverCount[$this->deviceId])) {
			$this->logMsg(SODW_INFO, "Rediscovering device {$this->deviceId}");
			$this->logMsg(SODW_INFO, "Calling core_rediscoverDevice {$this->deviceId}");
			if (!$this->core_rediscoverDevice($this->deviceId)) {
				$this->logMsg(SODW_ERROR, "Call to core_rediscoverDevice failed");
				return(FALSE);
			}
			$this->logMsg(SODW_INFO, "API Call to core_rediscoverDevice successful!");
			$this->soRediscoverCount[$this->deviceId] = 1;
			return(TRUE);
		}
		$this->logMsg(SODW_INFO, "Not rediscovering device again {$this->deviceId}");
		return(FALSE);
	}
	
	protected function logMsg($logLevel, $sMsg) {
		if (!($logLevel & $this->logLevel)) return(FALSE);
		$trace = debug_backtrace();
		$fCall = $trace[1];
		if (isset($fCall['function'])) {
			$fCall = $fCall['function'];
		}
		$tag = "(";
		foreach ($this->logKey as $key => $val) {
			if ($key & $logLevel) $tag .= $val;
		}
		$tag .= ")";
		echo "{$tag}\t{$fCall}():\t{$sMsg}\n";
	}
	
	public function attachIndicatorsToObject($objectName, $objectDesc, $objectTypeName, $indicator, $allowSumming = FALSE) {
		//$this->logMsg(SODW_FENTRY, "Called with {$objectName} {$objectDesc} {$objectTypeName} " . print_r($indicator, TRUE));
		$this->logMsg(SODW_FENTRY, "Called with {$objectName} {$objectDesc} {$objectTypeName} ");
		if (!is_array($indicator)) {
			$this->logMsg(SODW_ERROR, "Indicator variable passed to us was not an array!");
			$this->logMsg(SODW_ERROR, "Indicator dump: " . print_r($indicator, TRUE));
			return(FALSE);
		}
		if ($this->isValidIndicator($indicator)) {
			$this->logMsg(SODW_WARN, "Indicator variable passed to us was not an array of indicators but was probably a regular indicator -- use attachIndicatorToObject instead");
			return($this->attachIndicatorToObject($objectName, $objectDesc, $objectTypeName, $indicator, $allowSumming));
		}
		$c = count($indicator);
		$this->logMsg(SODW_FENTRY, "Called to attach an indicator to {$objectName} ({$objectDesc}) with type {$objectTypeName} and {$c} indicators");
		$r = 0;
		foreach ($indicator as $i) {
			if (!$this->isValidIndicator($i)) {
				$this->logMsg(SODW_WARN, "This indicator does not appear to be valid: " . print_r($i, TRUE));
				$this->logMsg(SODW_WARN, "Ignoring this data and continuing!");
				continue;
			}
			$this->attachIndicatorToObject($objectName, $objectDesc, $objectTypeName, $i, $allowSumming);
			$r++;
		}
		$this->logMsg(SODW_FENTRY, "Exiting, attached {$r} of {$c} indicators");
		return(TRUE);
	}
	
	public function isValidIndicator($ind) {
		/* Super basic test, does NOT test for type correctness */
		$this->logMsg(SODW_FENTRY, "Called to test an indicator");
		$indKeys = array('type', 'name', 'value', 'unit');
		if (!is_array($ind)) {
			$this->logMsg(SODW_ERROR, "This indicator isn't even an array: " . print_r($ind, TRUE));
			return(FALSE);
		}
		foreach ($ind as $i) {
			if (!is_array($i)) continue;
			/* Bail out if anything is an array -- we were probably passed an array of indicators from attachIndicatorsToObject() */
			return(FALSE);
		}
		$missingKeys = array();
		foreach ($indKeys as $testKey) {
			if (!isset($ind[$testKey])) {
				$missingKeys[] = $testKey;
			}
		}
		if (count($missingKeys)) {
			$this->logMsg(SODW_ERROR, "The array passed to us for test was missing the following keys: " . implode(' ', $missingKeys));
			$this->logMsg(SODW_ERROR, print_r($ind, TRUE));
			return(FALSE);
		}
		return(TRUE);
	}
			

	public function attachIndicatorToObject($objectName, $objectDesc, $objectTypeName, $indicator, $allowSumming = FALSE ) {
		//$this->logMsg(SODW_FENTRY, "Called with {$objectName} {$objectDesc} {$objectTypeName} " . print_r($indicator, TRUE));
		$this->logMsg(SODW_FENTRY, "Called with {$objectName} {$objectDesc} {$objectTypeName} ");
		if (!isset($this->intObjects[$this->deviceId][$objectName])) {
			$this->intObjects[$this->deviceId][$objectName]['desc'] = $objectDesc;
			$this->intObjects[$this->deviceId][$objectName]['type'] = $objectTypeName;
		}
		$this->intObjects[$this->deviceId][$objectName]['lastUpdate'] = time(NULL);
		if (!isset($this->intObjects[$this->deviceId][$objectName]['indicators'])) {
			$this->intObjects[$this->deviceId][$objectName]['indicators'][] = $indicator;
			$this->logMsg(SODW_INFO, "Adding {$indicator['name']} to {$objectName}");
			return(TRUE);
		}
		
		foreach ($this->intObjects[$this->deviceId][$objectName]['indicators'] as $i) {
			if ($i['name'] === $indicator['name']) {
				if ($allowSumming) {
					$i['value'] += $indicator['value'];
					$this->logMsg(SODW_INFO, "Summing value for {$objectName}->{$i['name']} (indicator added more than once and allowSumming on)");
					return(TRUE);
				}
				else {
					$this->logMsg(SODW_WARN, "Asked to add {$objectName}->{$i['name']}, but indicator exists and not called with allowSumming");
					return(FALSE);
				}
			}
		}
		$this->logMsg(SODW_INFO, "Adding {$indicator['name']} to {$objectName}");
		$this->intObjects[$this->deviceId][$objectName]['indicators'][] = $indicator;
		
		return(TRUE);
	}
	
	public function pushData() {
		$this->logMsg(SODW_FENTRY, "Called");
		if (!count($this->intObjects[$this->deviceId])) {
			$this->logMsg(SODW_ERROR, "No objects to insert");
			return(FALSE);
		}
		foreach($this->intObjects[$this->deviceId] as $okey => $oval) {
			$this->pushObjData($okey, $oval);
		}		
	}
	
	public function getInternalData($devid = NULL) {
		$this->logMsg(SODW_FENTRY, "Called with {$devid}");
		if (!$devid) $devid = $this->deviceId;
		if (!isset($this->intObjects[$devid]) || !count($this->intObjects[$devid])) {
			$this->logMsg(SODW_WARN, "Asked to return internal object/indicator data for {$devid} but there are none");
			return(FALSE);
		}
		return($this->intObjects[$this->deviceId]);
	}
	
	public function deleteInternalObject($objectName, $devid = FALSE) {
		if (!$devid) $devid = $this->deviceId;
		$this->logMsg(SODW_FENTRY, "Called with devid: {$devid} and objectName: {$objectName}");
		if (isset($this->intObjects[$devid][$objectName])) {
			unset($this->intObjects[$devid][$objectName]);
			return(TRUE);
		}
		$this->logMsg(SODW_WARN, "Called to delete object, but it didn't exist");
		return(FALSE);
	}
	
	public function updateObjectType($objectName, $objectType, $devid = FALSE) {
		if (!$devid) $devid = $this->deviceId;
		$this->logMsg(SODW_FENTRY, "Called with devid: {$devid} objectType: {$objectType} and objectName: {$objectName}");
		if (isset($this->intObjects[$devid][$objectName])) {
			$this->intObjects[$devid][$objectName]['type'] = $objectType;
			return(TRUE);
		}
		$this->logMsg(SODW_WARN, "Called to update object, but it didn't exist");
		return(FALSE);	
	}

	
	public function pushObjData($okey, $oval) {
		//$this->logMsg(SODW_FENTRY, "Called with okey: " . print_r($okey, TRUE) . " oval:" . print_r($oval, TRUE));
		$this->logMsg(SODW_FENTRY, "Called with okey: {$okey}");
		$object = $this->getObjectByNameAndType($okey, $oval['type']);
		if (!is_object($object)) {
			$this->logMsg(SODW_ERROR, "Unable to acquire object {$okey} -- {$object} returned to us, failing to push data for this object");
			return(FALSE);
		}
		$objectIndicators = $this->getIndicatorsByObjectName($okey, TRUE);
		if (!count($oval['indicators'])) {
			$this->logMsg(SODW_ERROR, "No internal indicators attached to {$okey} to push"); 
			return(FALSE);
		}
		if (!count($objectIndicators)) {
			$this->logMsg(SODW_ERROR, "There were no object indicators returned to us, deferred data may be disabled, this device may need rediscovery, or something else that's really really bad may be happening..");
			$this->logMsg(SODW_ERROR, print_r($objectIndicators, TRUE));
			$this->soRediscover();
			return(FALSE);
		}
		foreach ($oval['indicators'] as $i) {
			$newInds[$i['name']] = $i;
		}
		$time = array();
		foreach ($objectIndicators as $i) {
			if (isset($newInds[$i->indicatorType])) $data[$i->id] = $newInds[$i->indicatorType]['value'];
			else {
				$this->logMsg(SODW_WARN, "Object has indicator type {$i->indicatorType}, but no data given to us for it");
				$data[$i->id] = 0;
			}
			$time = $oval['lastUpdate'];
		}
		$this->logMsg(SODW_DEBUG, "newInds: " . print_r($newInds, TRUE) . "Data: " .  print_r($data, TRUE));
		//$res = $this->so_insertDataRow($this->deviceId, array_keys($data), array_values($data));
		$res = $this->so_insertDataRows($this->deviceId, array_keys($data), array($time), array(array_values($data)));
		$this->logMsg(SODW_DEBUG, "Calling insertDataRows with {$this->deviceId}, " . print_r(array_keys($data), TRUE), " , " . print_r($time, TRUE) . " , " . print_r(array_values($data), TRUE) . " --");
		$this->logMsg(SODW_INFO, "insertDataRows returned {$res}");
		unset($data); unset($object); unset($objectIndicators); unset($oval); unset($newInds);
	}

	public function preVerify($deviceId = FALSE) {
		$this->logMsg(SODW_FENTRY, "Called");
		$this->logMsg(SODW_DEBUG, print_r($this->intObjects, TRUE));
		if ($deviceId === FALSE) $deviceId = $this->deviceId;
		if (!(isset($this->intObjects[$deviceId]) && is_array($this->intObjects[$deviceId]))) {
			$this->logMsg(SODW_WARN, "There were no objects for {$deviceId}");
			return(FALSE);
		}
		foreach ($this->intObjects[$this->deviceId] as $lkey => $localObj) {
			$object = $this->getObjectByNameAndType($lkey, $localObj['type']);
			$objType = $this->getObjectTypeByName($localObj['type']);
			$dev = $this->core_getDeviceById($this->deviceId);
			if (!is_object($objType)) {
				$this->logMsg(SODW_ERROR, "There was no error trying to get an object type, failing verify");
				return(FALSE);
			}
			
			if (!$object) {
				$this->logMsg(SODW_ERROR, "Unable to acquire object {$lkey} -- this object will not have indicators inserted");
				continue;
			}

			//$this->logMsg(SODW_DEBUG, "Got object type back -- " . print_r($objType, TRUE));
			$indTypes = $this->getIndicatorTypesByObjectTypeName($localObj['type']);
			$this->logMsg(SODW_DEBUG, "Got indicator types back");
			if (!is_array($indTypes)) {
				$this->logMsg(SODW_INFO, "This object type has no indicator types");
			}
			/* Search for the indicator types */
			foreach ($localObj['indicators'] as &$i) {
				foreach ($indTypes as $t) {
					if ($t->name == $i['name']) $i['found'] = 1;
				}
			}
			/* Create the indicator types */
			foreach ($localObj['indicators'] as &$i) {
				if (!isset($i['found'])) {
					if ($this->createIndicatorType($objType, $i) === FALSE) {
						$this->logMsg(SODW_ERROR, "createIndicatorType for $objType {$i['name']} failed");
					}
				}
			}
		}
		return(TRUE);
	}
	public function createIndicatorType($objType, $indicator) {
		$this->logMsg(SODW_FENTRY, "Called to create an indicator {$indicator['name']} under {$objType->name}");
		$retryCount = 99;
		$indicatorTypes = array();
		while (!isset($indicatorTypes[$indicator['name']]) && $retryCount) {
			$id = $this->so_createIndicatorType($objType->id, $indicator['name']);
			$itypes = $this->so_getIndicatorTypesByObjectTypeId($objType->id);
			foreach ($itypes as $ica) {
				$indicatorTypes[$ica->name] = $ica;
			}
			if (isset($indicatorTypes[$indicator['name']])) $success = 1;
		}
		if ($success) {
			$this->logMsg(SODW_INFO, "We created a new indicator under {$objType->name} called {$indicator['name']} with id {$id}");
			if (isset($indicator['type'])) $this->so_setIndicatorTypeFormat($id, $indicator['type']);
			if (isset($indicator['units'])) $this->so_setIndicatorTypeUnits($id, $indicator["units"]);
			if (isset($indicator['maxValue'])) $this->so_setIndicatorTypeHasMaxValue($id, 1);
			return($id);
		}
		$this->logMsg(SODW_ERROR, "Unable to create new indicator type under {$objType->name} called {$indicator['name']}");
		return(FALSE);
	}
	
	public function soInit($deviceName = NULL) {
		$this->logMsg(SODW_FENTRY, "Called with {$deviceName}");
		$i = 10;
		$servers = $this->options['server_hostname'];
		if (!is_array($servers)) {
			if (strpos(',', $servers) !== FALSE) {
				$servers = explode(',', $servers);
			}
			else {
				$servers = array($servers);
			}
		}
		
		$sWSDL = $this->options['server_url'];
		$sAPI = '/soap3/api.php';
		$login = $this->options['login_username'];
		$pass = $this->options['login_password'];

		$options = array('trace' => 1, 'connection_timeout'=>45, 'cache_wsdl'=>'1');
		$client = $this->soCreateConnection($servers, $options, $sWSDL, $sAPI);
		if (!$client) {
			$this->logMsg(SODW_ERROR, "Could not connect to any SevOne server.");
			return(FALSE);
		}
		$this->client = $client;
		if (strlen($deviceName)) {
			$this->setDeviceName($deviceName, TRUE);
			$this->logMsg(SODW_INFO, "Enabling deferred data plugin for {$this->deviceId} {$this->deviceName}");
			$this->so_enablePluginForDevice($this->deviceId, 1);
			if (!$this->core_rediscoverDevice($this->deviceId)) {
				$this->logMsg(SODW_ERROR, "Call to core_rediscoverDevice failed");
			}
		}
		return(TRUE);
	}
	
	public function soCreateConnection($servers, $options, $sWSDL, $sAPI) {
		$this->logMsg(SODW_FENTRY, "Called with " . print_r($servers, TRUE) . " " . print_r($options, TRUE));
		
		if (!is_array($servers)) $servers = array($servers);
		foreach ($servers as $server) {
			$serverURL = "http://{$server}{$sWSDL}";
			$location = "http://{$server}{$sAPI}";
			try {
				$client = new SoapClient($serverURL, $options);
			} catch (Exception $e) {
				$this->logMsg(SODW_ERROR, "Got an exception trying to instantiate SOAP Client: " . $e->getMessage());
				continue;
			}
			if (!$client) {
				$this->logMsg(SODW_ERROR, "Unable to connect to {$server} " . print_r($location, TRUE));
				continue;
			}
			else {
				$this->logMsg(SODW_INFO, "Connected to {$server} " . print_r($location, TRUE));
				if (isset($location)) $client->__setLocation($location);
				do {
					$cException = 0;
					try {
						$result = $client->authenticate($this->options['login_username'], $this->options['login_password']);
						if (!$result) {
							$this->logMsg(SODW_INFO, "Couldn't authenticate with the server.");
							return(FALSE);
						} else {
							$result = $client->getAuthenticatedUid();
							$this->logMsg(SODW_INFO, "Successfully authenticated with UID {$result}");
						}
					} catch (Exception $e) {
						$this->logMsg(SODW_ERROR, "Got an exception trying to authenticate: " . $e->getMessage());
						$this->sendMail("{$this->scriptName}: {$this->deviceName} SevOne Exception Caught", "<pre>{$e}</pre>");
						$cException = 1;
					}
				} while ($cException && $this->sevException());
				if ($this->sevException(TRUE) !== FALSE) return($client);
				$this->logMsg(SODW_ERROR, "Exceeded exception limit trying to authenticate.. trying next server if available");
				$this->sevException(0);
			}
		}
		$this->logMsg(SODW_ERROR, "Unable to connect to or authenticate to any server in our list");
		return(FALSE);
	}

	public function getObjectsByDevice($options = NULL) {
		if (!is_array($options)) $options = array('force_refresh' => FALSE, 'cache_only' => FALSE, 'get_indicators' => TRUE, 'get_types' => TRUE);
		if ($options['cache_only'] && $this->options['allow_cache'] && isset($this->soObjects[$this->deviceId])) {
			return($this->soObjects[$this->deviceId]);
		}
		if ($this->options['allow_cache'] && !$options['force_refresh'] && isset($this->soObjects[$this->deviceId]) && count($this->soObjects[$this->deviceId])) {
			$soObjs = $this->soObjects[$this->deviceId];
		}
		else {
			$soObjs = $this->so_getObjectsByDeviceId($this->deviceId);
			if ($soObjs !== FALSE && ($soObjs === NULL || !count($soObjs))) {
				return(TRUE);
			}
		}
		if (isset($this->soObjects[$this->deviceId]) && count($soObjs) > count($this->soObjects[$this->deviceId])) {
			unset($this->soObjects[$this->deviceId]);
		}
		foreach ($soObjs as $soObj) {
			if (isset($this->soObjects[$this->deviceId][$soObj->name])) unset($this->soObjects[$this->deviceId][$soObj->name]);
			$this->soObjects[$this->deviceId][$soObj->name] = $soObj;
			if ($options['get_indicators']) $this->getIndicatorsByObjectName($soObj->name);
			if ($options['get_types']) $this->getObjectTypeById($soObj->objectTypeId);
		}
		return($this->soObjects[$this->deviceId]);
	}

	public function getObjectByNameAndType($objName, $objTypeName = NULL) {
		if ($this->options['allow_cache'] && isset($this->soObjects[$this->deviceId][$objName])) {
			$this->logMsg(SODW_CACHE, "Retrieved Object {$objName} from cache");
			return($this->soObjects[$this->deviceId][$objName]);
		}
		$soObj = $this->so_getObject($this->deviceId, $objName);
		if ($objTypeName) {
			$objType = $this->getObjectTypeByName($objTypeName);
			if (!is_object($objType)) {
				$this->logMsg(SODW_ERROR, "Couldn't get / create object type {$objTypeName}");
				return(SO_NOEXIST_OT);
			}
		}
		if ((!isset($soObj) || !$soObj || !is_object($soObj)) && isset($objTypeName)) {
			$soObjId = $this->so_createObject($this->deviceId, $objType->id, $objName);
			
			$this->so_setObjectIsEnabled($this->deviceId, $soObjId, 1);
			$this->so_setObjectIsHidden($this->deviceId, $soObjId, 0);

			$soObj = $this->so_getObject($this->deviceId, $objName);
		}
		if (!isset($soObj) || !$soObj || !is_object($soObj)) {
			$this->logMsg(SODW_ERROR, "Couldn't get / create object {$objName}");
			return(SO_FAILED_OBJECTCREATE);
		}
		$this->logMsg(SODW_QUERY, "Queried object data for {$objName} from SevOne");
		$this->logMsg(SODW_DEBUG, "soObj debug: " .  print_r($soObj, TRUE));
		$nObjt = $this->getObjectTypeById($soObj->objectTypeId);
		if (!is_object($nObjt)) {
			$this->logMsg(SODW_ERROR, "Unable to get Object Type by Id..");
			return(FALSE);
		}
		if ($objTypeName && strcmp($nObjt->name, $objTypeName)) {
			$this->logMsg(SODW_ERROR, "Object type mismatch, SevOne says {$nObjt->name} but we were told {$objTypeName}");

			if (!isset($this->options['auto_delete_mismatch'])) {
				return(SO_MISMATCH_OBJECTTYPE);
			}
			
			$this->so_deleteObject($this->deviceId, $soObj->id);
			/* We may need to delay this until rediscover, we may not be able to create a new object with the name of an existing
			  object even if it is deleted. Worst case scenario this will delay data injection for a polling cycle or two since
			  it should fail gracefully */

			$soObjId = $this->so_createObject($this->deviceId, $objType->id, $objName);
			if ($soObjId) {
				$this->so_setObjectIsEnabled($this->deviceId, $soObjId, 1);
				$this->so_setObjectIsHidden($this->deviceId, $soObjId, 0);
				$soObj = $this->so_getObject($this->deviceId, $objName);
			}
			else {
				$this->logMsg(SODW_DEBUG, "Attempt to create object with new objType ID failed with devId: {$this->deviceId} objTypeId: {$objType->id} name: {$objName}");
			}
			return(SO_MISMATCH_OBJECTTYPE);
		}
		$this->soObjects[$this->deviceId][$objName] = $soObj;
		$this->getIndicatorsByObjectName($objName);
		return($this->soObjects[$this->deviceId][$objName]);
	}

	public function getIndicatorsByObjectName($objName, $forceRefresh = TRUE) {
		$this->logMsg(SODW_FENTRY, "Called with {$objName} and {$forceRefresh}");
		if ($this->options['allow_cache'] && !$forceRefresh && isset($this->soIndicators[$this->deviceId][$objName])) {
			$this->logMsg(SODW_CACHE, "Retrieved indicators for {$objName} from cache");
			return($this->soIndicators[$this->deviceId][$objName]);
		}
		$this->logMsg(SODW_QUERY, "Queried indicators for {$objName} from SevOne");
		$this->soIndicators[$this->deviceId][$objName] = $this->so_getIndicatorsByObject($this->deviceId, $objName);
		return($this->soIndicators[$this->deviceId][$objName]);
	}
	
	public function getObjectTypeByName($objTypeName) {
		$this->logMsg(SODW_FENTRY, "Called with {$objTypeName}");
		if ($this->options['allow_cache'] && isset($this->soObjectTypes[$objTypeName])) {
			$this->logMsg(SODW_CACHE, "Retrieved data for Object Type {$objTypeName} from cache");
			$this->logMsg(SODW_DEBUG, "Object info (CA): " . print_r($this->soObjectTypes[$objTypeName], TRUE));
			return($this->soObjectTypes[$objTypeName]);
		}
		
		$trace = debug_backtrace();
		$fCall = $trace[1];
		if (isset($fCall['function'])) {
			$fCall = $fCall['function'];
		}
		$this->logMsg(SODW_DEBUG, "getObjectTypeByName(): from {$fCall}(): {$objTypeName}");

		$nObjt = $this->so_getObjectTypeByOsIdAndName(0, $objTypeName);
		$this->logMsg(SODW_DEBUG, "Object info (SO): " . print_r($nObjt, TRUE));
		if (!$nObjt) {
			$nObjtId = $this->so_createObjectType(0, $objTypeName); 
			$nObjt = $this->so_getObjectTypeById($nObjtId);
			if (!$nObjt) return(SO_FAILED_GETOBJECTTYPE);
		}
		$this->soObjectTypes[$objTypeName] = $nObjt;
		$this->getIndicatorTypesByObjectTypeName($objTypeName);
		$this->logMsg(SODW_QUERY, "Queried for Object Type {$objTypeName} from SevOne");
		return($this->soObjectTypes[$objTypeName]);
	}

	public function getObjectTypeById($objTypeId) {
		$this->logMsg(SODW_FENTRY, "Called with {$objTypeId}");
		if ($this->options['allow_cache'] && count($this->soObjectTypes)) foreach ($this->soObjectTypes as $key => $val) {
			/* Check to see if we're allowed to cache responses, if so.. walk the cache if it exists
				Note: We should probably just key on the ID to save iterating through an array, this is
				 a comparatively expensive way of doing this */
			if (!is_object($val)) {
				/* Validate that it's an object .. if for some reason it is not log a message and delete the item */
				$this->logMsg(SODW_ERROR, "objTypes {$key} is not an object");
				$this->logMsg(SODW_ERROR, print_r($val, TRUE));
				unset($this->soObjectTypes[$key]);
			}
			else if ($val->id == $objTypeId) {
				/* Return the cached response */
				$this->logMsg(SODW_CACHE, "Retrieved data for Object Type {$val->name} :: {$objTypeId} from cache");
				return($val);
			}
		}
		/* Abuse __call and function name rewrites to use the SOAP API to get the Object Type */
		$nObjt = $this->so_getObjectTypeById($objTypeId);
		if (!$nObjt || !is_object($nObjt)) {
			/* SOAP API did not give us a valid response */
			$this->logMsg(SODW_ERROR, "SevOne API did not return us an Object Type for {$objTypeId}");
			return(NULL);
		}
		/* Cache the response even if we don't allow caching (We could save RAM here by checking allow_cache again) */
		$this->soObjectTypes[$nObjt->name] = $nObjt;
		$this->getIndicatorTypesByObjectTypeName($nObjt->name);
		$this->logMsg(SODW_QUERY, "Queried for Object Type {$nObjt->name} :: {$objTypeId} from SevOne");
		/* Return the object type */
		return($this->soObjectTypes[$nObjt->name]);		
	}
	
	public function getIndicatorTypesByObjectTypeName($objTypeName, $forceRefresh = FALSE) {
		$this->logMsg(SODW_FENTRY, "Called with {$objTypeName} {$forceRefresh}");
		if ($this->options['allow_cache'] && !$forceRefresh && isset($this->soIndicatorTypes[$objTypeName]) && count($this->soIndicatorTypes[$objTypeName])) {
			return($this->soIndicatorTypes[$objTypeName]);
		}
		$trace = debug_backtrace();
		$fCall = $trace[1];
		if (isset($fCall['function'])) {
			$fCall = $fCall['function'];
		}
		if ($this->options['allow_cache']) $objType = $this->getObjectTypeByName($objTypeName);
		else $objType = $this->so_getObjectTypeByOsIdAndName(0, $objTypeName);
		if (!$objType || !is_object($objType)) {
			$this->logMsg(SODW_ERROR, "getObjectTypeByName returned NULL or invalid response");
			return(NULL);
		}
		$this->soIndicatorTypes[$objTypeName] = $this->so_getIndicatorTypesByObjectTypeId($objType->id);
		/*foreach ($this->soIndicatorTypes[$objTypeName] as $key => $val) {
			$this->logMsg(SODW_ERROR, "Changing type of " . $val->id . " to COUNTER64");
			$this->so_setIndicatorTypeFormat($val->id, 'COUNTER64');
		}*/
		
		return($this->soIndicatorTypes[$objTypeName]);
	}
	
	public function sevException($query = FALSE) {	
		$this->logMsg(SODW_FENTRY, "Called with $query");
		/* This function is called to see if we exceeded our exception limit */
		if ($query === TRUE) {
			/* If we're called with $query they just want to know how many exceptions have been encountered */
			return($this->sevExceptionCount);
		}
		if ($query === FALSE) {
			$this->sevExceptionCount++;
			if ($this->sevExceptionCount > $this->options['max_exceptions']) {
				/* We exceeded maximum exceptions */
				return(FALSE);
			}
			/* We have not exceeded maximum exceptions */
			return(TRUE);
		}
		$this->sevExceptionCount = $query;
	}
	
	public function makeIndicator($name, $value, $type = NULL, $unit = NULL) {
		$this->logMsg(SODW_FENTRY, "Called with {$name} {$value} {$type} {$unit}");
		if (!$type) {
			if (isset($this->options['makeIndicator']['defaultType'])) $type = $this->options['makeIndicator']['defaultType'];
			else {
				$this->logMsg(SODW_ERROR, "Indicator type not passed to us and no default set, aborting");
				return(FALSE);
			}
		}
		if (!$unit) {
			if (isset($this->options['makeIndicator']['defaultUnit'])) $unit = $this->options['makeIndicator']['defaultUnit'];
			else {
				$this->logMsg(SODW_ERROR, "Indicator unit not passed to us and no default set, aborting");
				return(FALSE);
			}
		}
		$r = array('name' => $name, 'type' => $type, 'unit' => $unit, 'value' => $value);
		return($r);
	}
	
	public function __call($method, $args) {
		if (strncmp("so_", $method, 3) && strncmp("core_", $method, 5)) {
			/* Check to see if we should rewrite this function and call something via SOAP, if not core_ or so_ .. invalid call */
			$this->logMsg(SODW_ERROR, "Undefined function {$method}");
			return(NULL);
		}
		if ($this->options['dont_inject'] && preg_match("/so_.*(create|delete|update|insert)/i", $method)) {
			/* Block all create/delete/update/insert SOAP functions if dont_inject option is set to avoid accidental modification */
			$this->logMsg(SODW_WARN, "dont_inject set, but {$method} called");
			return(NULL);
		}
		$trace = debug_backtrace();
		$fCall = $trace[2]['function'];
		do {
			/* Loop in case there's an exception.. retry in case there is */
			$cException = 0;
			try {
				$newmethod = str_replace("so_", "plugin_deferred_", $method);
				$this->logMsg(SODW_SOAP, "{$newmethod}() :: {$fCall}()");
				$startTime = microtime(TRUE);
				$a = call_user_func_array(array($this->client, $newmethod), $args);
				$endTime = microtime(TRUE);
				$totalTime = $endTime - $startTime;
				if (!isset($this->soapCounter[$newmethod])) $this->soapCounter[$newmethod]['count'] = 0;
				$this->soapCounter[$newmethod]['count']++;
				/* Store the time taken to execute this call */
				$this->soapCounter[$newmethod][] = $totalTime;
			} catch (Exception $e) {
				$this->logMsg(SODW_ERROR, "Exception: {$newmethod} from {$fCall}: " . $e->getMessage());
				$cException = 1;
			}
		} while ($cException && $this->sevException());
		$this->logMsg(SODW_SODEBUG, "Return from SevOne for {$fCall}: " . print_r($a, TRUE));
		$this->logMsg(SODW_SODEBUG, "Function {$newmethod} was called with: " . print_r($args, TRUE));
		if (isset($a)) return($a);
		return(FALSE);
	}
}

?>
