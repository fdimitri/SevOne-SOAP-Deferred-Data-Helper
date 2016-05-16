<?php
/* This could be easily reworked without having all these ridiculous public variables..
   Fill *Keys array with data from ini file, would be cleaner/less code, too. Write simple
   getValue(groupName, keyName);
   */
   
class encapIni {
	private $SevOne;
	private $Cisco;
	private $iniFile;
	private $SevOneSection = "SevOne";
	private $CiscoSection = "Cisco";
	private $eMailSection = "Email";
	private $iniArray = array(array());

	public $SOuser, $SOpass, $SOsoap, $SOserver, $SOgroup;
	public $Cuser, $Cpass, $Ctimeout, $Cretry, $Cdelay, $Cwantssh, $Cnotelnet;
	public $gmaxJobs, $gTestMode, $gmaxAge;
	public $gInject;
	
	public $eTo, $eFrom, $eSubject, $eServer;
	
	private $eMailKeys = array(
		array("from", "sandbox@frankd.lab"),
		array("to", "sandbox@frankd.lab"),
		array("cc", "USERNAME@frankd.lab"),
		array("replyto", "sandbox@frankd.lab"),
		array("subject", "Alert"),
		array("frequency", "1"),
		array("server", "mail.frankd.lab"),
		array("login", "none"),
		array("password", "none")
	);
	private $SevOneKeys = array(
		array("server", NULL),
		array("user", NULL),
		array("password", NULL),
		array("soap", "/soap3/api.wsdl"),
		array("groupnames", NULL)
		);
	private $CiscoKeys = array(
		array("user", NULL),
		array("password", NULL),
		array("prompt_timeout", 5),
		array("conn_retries", 0),
		array("conn_delay", 10),
		array("prefer_ssh", 0),
		array("no_telnet", 0)
	);

	public function __construct($iniFile) {
		if (!$iniFile || !file_exists($iniFile)) {
			echo "Error opening $iniFile\n";
		}
		$this->iniFile = $iniFile;
		$this->iniArray = parse_ini_file($this->iniFile, true);
		$this->parseIni();
	}
	public function SoapURL($server = 1) {
		if ($server) {
			return("http://{$this->SOserver}{$this->SOsoap}");
		}
		return("{$this->SOsoap}");
	}
	public function SoapServer() {
		return("{$this->SOserver}");
	}
	private function parseIni() {
		$this->gTestMode = 0;
		if (isset($this->iniArray['Global']['testmode']) && (intval($this->iniArray['Global']['testmode']) == 1 || !strcasecmp($this->iniArray['Global']['testmode'], "true"))) {
			echo "Using test credentials/servers instead of production\n";
			$this->SevOneSection = $this->SevOneSection . "Test";
			$this->gTestMode = 1;
		}
		$this->gmaxJobs = 10; $this->gInject = 0;
		if (isset($this->iniArray['Global']['maxjobs']) && intval($this->iniArray['Global']['maxjobs'])) {
			$this->gmaxJobs = intval($this->iniArray['Global']['maxjobs']);
		}
		if (isset($this->iniArray['Global']['maxage']) && intval($this->iniArray['Global']['maxage'])) {
			$this->gmaxAge = intval($this->iniArray['Global']['maxage']);
		}
		if (isset($this->iniArray['Global']['noinject']) && intval($this->iniArray['Global']['noinject'])) {
			$this->gInject = intval($this->iniArray['Global']['noinject']);
		}

		echo "mJobs: {$this->gmaxJobs}, mAge: {$this->gmaxAge}";
		if (!isset($this->iniArray[$this->SevOneSection])) {
			echo "{$this->iniFile} does not contain a valid {$this->SevOneSection}, exiting";
			exit();
		}
		foreach ($this->SevOneKeys as &$a) {
			if (!isset($this->iniArray[$this->SevOneSection][$a[0]])) {
				echo "{$this->SevOneSection}.{$a[0]} is not set";
				if (isset($a[1])) {
					echo ", using default $a[1]\n";
					$this->iniArray[$this->SevOneSection][$a[0]] = $a[1];
				}
				if (!isset($a[1])) {
					echo " and key has no default. Failing.\n";
					exit(0);
				}
			}
			//echo "{$this->SevOneSection}.{$a[0]} is {$this->iniArray[$this->SevOneSection][$a[0]]}\n";
			switch ($a[0]) {
				case "server":
					$this->SOserver = $this->iniArray[$this->SevOneSection][$a[0]];
				break;
				case "user":
					$this->SOuser = $this->iniArray[$this->SevOneSection][$a[0]];
				break;
				case "password":
					$this->SOpass = $this->iniArray[$this->SevOneSection][$a[0]];
				break;
				case "soap":
					$this->SOsoap = $this->iniArray[$this->SevOneSection][$a[0]];
				break;
				case "groupnames":
					$this->SOgroup = $this->iniArray[$this->SevOneSection][$a[0]];
				break;
			}
		}

		foreach ($this->CiscoKeys as &$a) {
			if (!isset($this->iniArray[$this->CiscoSection][$a[0]])) {
				echo "{$this->CiscoSection}.{$a[0]} is not set, ";
				if (isset($a[1])) {
					echo " using default $a[1]\n";
					$this->iniArray[$this->CiscoSection][$a[0]] = $a[1];
				}
				if (!isset($a[1])) {
					echo " and key has no default. Failing.\n";
					exit(0);
				}
			}
			//echo "{$this->CiscoSection}.{$a[0]} is {$this->iniArray[$this->CiscoSection][$a[0]]}\n";
			switch ($a[0]) {
				case "user":
					$this->Cuser = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "password":
					$this->Cpass = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "prompt_timeout":
					$this->Ctimeout = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "conn_retries":
					$this->Cretry = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "conn_delay";
					$this->Cdelay = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "prefer_ssh":
					$this->Cwantssh = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
				case "no_telnet":
					$this->Cnotelnet = $this->iniArray[$this->CiscoSection][$a[0]];
				break;
			}
		}
		foreach ($this->eMailKeys as &$a) {
			if (!isset($this->iniArray[$this->eMailSection][$a[0]])) {
				echo "{$this->eMailSection}.{$a[0]} is not set, ";
				if (isset($a[1])) {
					echo ", using default $a[1]\n";
					$this->iniArray[$this->eMailSection][$a[0]] = $a[1];
				}
				if (!isset($a[1])) {
					echo " and key has no default. Failing.\n";
					exit(0);
				}
			}
			switch ($a[0]) {
				case "to":
					$this->eTo = $this->iniArray[$this->eMailSection][$a[0]];
				case "from":
					$this->eFrom = $this->iniArray[$this->eMailSection][$a[0]];
				case "server":
					$this->eServer = $this->iniArray[$this->eMailSection][$a[0]];
				break;
			}
		}
	}
}
?>
