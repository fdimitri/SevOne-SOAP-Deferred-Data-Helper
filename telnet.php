<?php
/* Still needs a major rewrite, imported some stuff from the NodeMove tool to read data faster, also added command output buffering */
/* Basic command run/get buffer contents for parsing.. */
/* Yeah, this is telnet written directly from a TCP socket -- and yes there are better ways of doing this. */
define('CMD_BUFFER',		0x0001);
define('CMD_NOBUFFER',	0x0002);

class nxOS extends slOut {
	public function getMemoryStatistics($filter = NULL) {	
		$reArray = array(
			'type' => '\d+',
			'name' => '(\[[\w\-]+\]){0,1}([\/\w\d\.\-\_]+)',
			'alloc_cur' => '\d+',
			'alloc_max' => '\d+',
			'bytes_cur' => '\d+',
			'bytes_max' => '\d+',
		);

		$r = $this->runCmd("show system internal vntag mem-stats detail", CMD_BUFFER);
		if (!$r) return(FALSE);
		if (strpos(implode('n', $r), 'Invalid command') !== FALSE) {
			echo "This system does not have a FEX module";
			return(FALSE);
		}
		if ($filter)	$r = $this->filterOutputIn($r, '/' . preg_quote($filter) . '/');
		
		/* Build the regex */
		$reStr = "/";
		foreach ($reArray as $key => $val) {
			$reStr .= "(?P<{$key}>{$val})\s*";
		}
		$reStr .= "/";
		
		$out = array();
		$n = array();
		
		/* Match the regex against each line of output */
		foreach ($r as $l) {
			if (preg_match($reStr, $l, $match)) $n[] = $match;
			else "No match for $reStr to $l\n";
		}
	
		if (count($n)) foreach ($n as $i) {
			foreach ($reArray as $key => $val) {
				$v[$key] = $i[$key];
			}
			$out[] = $v;
		}
		else return(FALSE);		/* If nothing matched return false */
		
		/* Return everything that matched (an array with all the keys from $reArray */
		return($out);
	}
	
}

class slOut {
	protected $tc;
	protected $sock;
	protected $rePrompt;
	protected $timeOut = 90;
	protected $user, $pass;
	protected $conRetry = 0, $conRetryLimit = 20;
	protected $cmdBuffer = array();
	protected $reIP = "/\b(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.(25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\/\d\d\b/";
	/* Good regex for checking for valid IPs, ugly, but it works -- better to match simpler and validate numbers */
	protected $tC = array();
	protected $tCmds = array();
	protected $tOpts = array();
	protected $defOptions = array(
		'loginPrompt' => 'name:',
		'passwordPrompt' => 'word:',
		);
	
	public function __construct($host, $port, $type, $options = NULL) {
		$rePrompts = array(
			array("ASR_RTR", "/RP\/\d\/RSP\d\/CPU\d\:rtr\d\.\w{3}\.\w{6}\#/"),
			array("ASR_DST", "/RP\/\d\/RSP\d\/CPU\d\:dstswr\d\.vod\.\w{6}\#/"),
			array("ASR_ANY", "/RP\/\d\/\w+\d\/\w+\d\:\w+\.\w+\.\w+.*\#/"),
			array("CMTS_UBR", "/ubr\d{3}\.cmts\.\w{6}\#/"),
			//array("CMTS_UBR", "/ubr103.lab.cmts#/"),
		);
		$this->host = $host;
		$this->port = $port;
		foreach ($this->defOptions as $okey => $oval) {
			if ($options && is_array($options) && isset($options[$okey])) $this->options[$okey] = $options[$okey];
			else $this->options[$okey] = $oval;
		}
		
		$this->setArrays();
		foreach ($rePrompts as $pr) {
			/* Set our prompt regex according to device type, see $rePrompts element 0 */
			if (!strcasecmp($pr[0], $type)) {
				$this->rePrompt = $pr[1];
				break;
			}
		}
		if (!strlen($this->rePrompt)) {
			$this->rePrompt = $type;
			echo "Unknown type\n";
		}
		return(TRUE);
	}
	
	public function getOffendingFunction() {
		echo "getOffendingFunction(): Called\n";
		$trace = debug_backtrace();
		if (!isset($trace[2])) {
			echo "Trace[2] was unset\n";
			return(array('function'=>'unknown'));
		}
		$fCall = $trace[2];
		if (!isset($fCall['function'])) {
			$fCall['function'] = 'unknown';
		}
		return($fCall);
	}
	
	public function startConnection($user, $pass) {
		$this->conRetry++;
		if ($this->conRetry > $this->conRetryLimit) return(FALSE);
		$this->openSock($this->host, $this->port);
		$this->authSession($user, $pass);
		return(TRUE);
	}
	
	private function openSock($host, $port) {
		$this->sock = fsockopen($host, $port);

		while (!$this->sock) {
			$this->sock = fsockopen($host, $port);
			sleep(5);
		}
		stream_set_blocking($this->sock, 0);
		/* Never wait for data on reads (return no bytes instead of waiting for X bytes)
			Never wait to send data (return 15/50 bytes sent)
			This is non-blocking
			And remember..
		   Don't cross the streams. */
		$this->sTelnet();
		usleep(50000);
		echo "Created connection to {$host}\n";
	}
	
	protected function authSession($user = NULL, $pass = NULL) {
		if (!$user) {
			$user = $this->user;
			$pass = $this->pass;
		} 
		if (!$this->user) {
			if (!$user) {
				echo "Asked to authSession before external authSession\n";
				return(0);
			}
			$this->user = $user;
			$this->pass = $pass;
		}
		echo "authSession {$user} {$pass}\n";
		$this->readUntilPrompt(5, $this->options['loginPrompt']);
		echo "authSession send user\n";
		$this->sendData("{$user}\n");
		$this->readUntilPrompt(5, $this->options['passwordPrompt']);
		echo "authSession send pass\n";
		$this->sendData("{$pass}\n\n");
		$n = $this->readAll();
		usleep(50000);
		$n = $this->readAll();
	}
	
	public function getPrompt() {
		return($this->rePrompt);
	}
	
	
	public function sendData($str, $recurse = 1) {
		$len = strlen($str);
		$written = 0;
		while ($written < $len) {
			$res = fwrite($this->sock, substr($str, $written));
			if (!$res) {
				echo "Couldn't write to socket : {$str} -- wrote {$res} of {$len} bytes\n";
				if ($recurse) {
					$this->openSock($this->host, $this->port);
					if ($this->user) {
						$this->authSession($this->user, $this->pass);
					}
					$this->sendData($str, 0);
				}
				else {
					echo "Dying!\n";
					die;
				}
			}
			$written += $res;
		}
	}

	public function runCmd($str, $flags = 0) {
		if ($flags & CMD_BUFFER && isset($this->cmdBuffer[$str])) return ($this->cmdBuffer[$str]);
		$this->readAll();
		$this->sendData("\n");
		$this->readUntilPrompt();
		$this->sendData($str . "\n");
		$cOut = $this->readUntilPrompt();
		/* Filter output to exclude command send if telnet echo was not turned off */
		$cOut = $this->filterOutputEx($cOut, "/" . preg_quote($str, '/') . "/");
		/* Filter output to exclude date from output data, limit one  */
		$cOut = $this->filterOutputEx($cOut, "/\w{3}\s+\w{3}\s+\d+\s+\d{2}\:\d{2}\:\d{2}\.\d+\s+\w{3}/", 1);
		$a = implode("\n", $cOut);
		$this->cmdBuffer[$str] = $cOut;
		return($cOut);
	}
	
	protected function procTelnetSub($cl) {
		$c = str_split($cl);
		$i = 0;
		$cr = $c[$i];
		$obuf="";
		if ($cr == chr(0xFA)) {
			$cr = $c[++$i];
			foreach ($this->tOpts as $x) {
				if ($x[1] == $cr) break;
			}
			$cr = $c[++$i];
			while ($cr != $this->tC['sCmd']) $cr = $c[++$i];
			$rc = $c[++$i];
			while (++$i < count($cr)) $obuf .= $rc[$i];
			return($obuf);
		}
		foreach ($this->tCmds as $x) {
			if ($x[1] == $cr) break;
		}
		$cr = $c[++$i];
		foreach ($this->tOpts as $x) {
			if ($x[1] == $cr) break;
		}
		while (++$i < count($cr)) $obuf .= $rc[$i];
		return($obuf);
	}
	
	protected function procTelnet() {
		/* Process incoming telnet messages for debugging purposes -- we shouldn't really
		   have to respond to these, though ASRs are apparently a little picky about what
		   we send to them.. ie they wouldn't accept a raw connection with no telnet commands
		   for some reason. I may expand on this in the future to properly reply to OOB
		   telnet messages and track state changes. */
		$cr = fgetc($this->sock);
		if ($cr == chr(0xFA)) {
			echo "SubCommand: ";
			$cr = fgetc($this->sock);
			foreach ($this->tOpts as $x) {
				if ($x[1] == $cr) {
					echo $x[0];
					break;
				}
			}
			$cr = fgetc($this->sock);
			while ($cr != $this->tC['sCmd']) {
				echo " " .ord($cr);
				$cr = fgetc($this->sock);
			}
			fgetc($this->sock);
			echo "\n";
			return(0);
		}
		foreach ($this->tCmds as $x) {
			if ($x[1] == $cr) {
				echo $x[0] . " ";
				break;
			}
		}
		$cr = fgetc($this->sock);
		foreach ($this->tOpts as $x) {
			if ($x[1] == $cr) {
				echo $x[0] . "\n";
				break;
			}
		}
		return(0);
	}
	
	public function readAll() {
		$lBuf = fread($this->sock, 1);
		$cStat = socket_get_status($this->sock);
		while ($cStat['unread_bytes']) {
			/* While there's data, read it and append it to the previous data.. one byte at a time.
				We do it this way to more easily process OOB telnet */
			$cr = fgetc($this->sock);
			if ($cr == $this->tC['sCmd']) {
				$this->procTelnet();
			}
			else {
				$lBuf = $lBuf . $cr;
			}
			$cStat = socket_get_status($this->sock);
		}
		return($lBuf);
	}
	
	public function readUntilPrompt($timeOutOverride = 0, $promptOverRide = NULL, $recurse = 1) {
		$prompt = $this->rePrompt;
		$timeOut = $this->timeOut;
		if (strlen($promptOverRide)) $prompt = "/{$promptOverRide}/";
		if ($timeOutOverride) $timeOut = $timeOutOverride;
		$startTime = time(NULL);
		$r = array($this->sock);
		$w = $e = NULL;
		$lBuf = "";
		while ((time(NULL) - $startTime) < $timeOut && !preg_match($prompt, $lBuf, $x, PREG_OFFSET_CAPTURE, 0)) {
			while (feof($this->sock)) $this->startConnection($this->user, $this->pass);
			if (feof($this->sock)) return(FALSE);
			$r = array($this->sock);
			do {
				$a = stream_select($r, $w, $e, 500);
				if ($a !== 1) echo "Stream_select: " . print_r($a, TRUE);
				if ($a === FALSE) { 
					$this->startConnection($this->user, $this->pass);
					$r = array($this->sock);
				}
			} while (!$a && (time(NULL) - $startTime) < $timeOut);
			$ca = fread($this->sock, 2000);
			$tp = strpos($ca, chr(0xFF));
			if ($tp !== FALSE) {
				echo "Detected telnet command!";
				$lBuf .= substr($ca, $tp);
				$lBuf .= $this->procTelnetSub($ca);
			}
			else {
				$lBuf .= $ca;
				//file_put_contents("{$this->host}-telnet.log", "{$ca}", FILE_APPEND);
			}
		}
		
		if ((time(NULL) - $startTime) >= $timeOut) return(NULL);
		
		$sBuf = explode("\n", $lBuf);
		$sBuf = $this->filterOutputEx($sBuf, $prompt);
		$sBuf = $this->filterOutputEx($sBuf, "/^\s*$/");
		return($sBuf);
	}
	
	public function filterOutputEx($sBuf, $pat, $limit = 0) {
		$cSlice = 0;
		
		if (!is_array($sBuf)) {
			$r = $this->getOffendingFunction();
			echo "sBuf is not an array, called by " . $r['function'] . ", returning FALSE -- " . print_r($sBuf, TRUE);
			return(FALSE);
		}
		
		foreach ($sBuf as $key => $val) {
			if (preg_match($pat, $val)) {
				if ($limit && $cSlice > $limit) {
					break;
				}
				/* This line matches the pattern */
				unset($sBuf[$key]);
				$cSlice++;
			}
		}
		$sBuf = array_values($sBuf);
		return($sBuf);
	}

	public function sliceOutput($sBuf, $pat, $limit = 0) {
		$slOut = array();
		foreach ($sBuf as $key => $val) {
			if (preg_match($pat, $val, $match)) {
				array_push($slOut, $match[0]);
			}
		}
		return($slOut);
	}

	public function sliceOutputArray($sBuf, $iArray, $limit = 0) {
		$slOut = array();
		foreach ($sBuf as $key => $val) {
			//echo "sBuf {$key} {$val}\n";
			$cLine = array();
			foreach ($iArray as $iKey => $iVal) {
				//echo "iArray {$iKey} {$iVal}\n";
				if (preg_match($iVal, $val, $match)) {
					$cLine[$iKey] = $match[0];
				}
			}
			array_push($slOut, $cLine);
			$cLine = NULL;
		}
		return($slOut);
	}
	
	public function filterOutputIn($sBuf, $pat, $limit = 0) {
		$cSlice = 0;
		foreach ($sBuf as $key => $val) {
			if (!preg_match($pat, $val)) {
				$cSlice++;
				if ($limit && $cSlice > $limit) {
					break;
				}
				/* This line matches the pattern */
				unset($sBuf[$key]);
			}
		}
		$sBuf = array_values($sBuf);
		return($sBuf);
	}

	public function extractASRCounter($sBuf, $pat) {
		foreach ($sBuf as $c) {
			if (preg_match($pat, $c, $match)) {
				if (preg_match("/\d+/", $match[0], $ctr)) {
					return($ctr[0]);
				}
				return(0);
			}
		}
		return(0);
	}
		
	protected function setArrays() {
		$this->tC['sEnd'] = chr(0xF0);
		$this->tC['subOption'] = chr(0xFA);
		$this->tC['will'] = chr(0xFB);
		$this->tC['wont'] = chr(0xFC);
		$this->tC['do'] = chr(0xFD);
		$this->tC['dont'] = chr(0xFE);
		$this->tC['sCmd'] = chr(0xFF);


		$this->tC['negWindowSize'] = chr(0x1F);
		$this->tC['termSpeed'] = chr(0x20);

		$this->tC['supGoAhead'] = chr(0x03);
		$this->tC['termType'] = chr(0x18);
		$this->tC['windowSize'] = chr(0x1F);
		$this->tC['remoteFlowControl'] = chr(0x21);
		$this->tC['lineMode'] = chr(0x22);
		$this->tC['newEnvOption'] = chr(0x27);
		$this->tC['status'] = chr(0x05);
		$this->tC['XDisplayLoc'] = chr(0x23);
		$this->tC['envOption'] = chr(0x24);
		$this->tC['echo'] = chr(0x01);
		$tCmds = array(
			array("sEnd", chr(0xF0)),
			array("subOption", chr(0xFA)),
			array("will", chr(0xFB)),
			array("wont", chr(0xFC)),
			array("do", chr(0xFD)),
			array("dont", chr(0xFE)),
			array("scmd", chr(0xFF))
			);
		$this->tCmds = $tCmds;
		$tOpts = array(
			array("echo", chr(0x01)),
			array("supGoAhead", chr(0x03)),
			array("status", chr(0x05)),
			array("termType", chr(0x18)),
			array("windowSize", chr(0x1F)),
			array("termSpeed", chr(0x20)),
			array("remoteFlowControl", chr(0x21)),
			array("lineMode", chr(0x22)),
			array("XDisplayLoc", chr(0x23)),	
			array("envOption", chr(0x24)),
			array("newEnvOption", chr(0x27)),
		);
		$this->tOpts = $tOpts;
		return(0);
	}

	protected function sTelnet() {
		$initCmds = 
			$this->tC['sCmd'] . $this->tC['do'] . $this->tC['supGoAhead'] .
			$this->tC['sCmd'] . $this->tC['will'] . $this->tC['termType'] .
			$this->tC['sCmd'] . $this->tC['will'] . $this->tC['windowSize'] .
			$this->tC['sCmd'] . $this->tC['will'] . $this->tC['termSpeed'] .
			$this->tC['sCmd'] . $this->tC['wont'] . $this->tC['remoteFlowControl'] .
			$this->tC['sCmd'] . $this->tC['wont'] . $this->tC['lineMode'] .
			$this->tC['sCmd'] . $this->tC['will'] . $this->tC['newEnvOption'] .
			$this->tC['sCmd'] . $this->tC['do'] . $this->tC['status'] .
			$this->tC['sCmd'] . $this->tC['will'] . $this->tC['XDisplayLoc'];
			$this->tC['sCmd'] . $this->tC['dont'] . $this->tC['remoteFlowControl'] .
		usleep(500000);
		
		$this->sendData($initCmds);usleep(100000);
		
		$nCmds = $this->tC['sCmd'] . $this->tC['wont'] . $this->tC['envOption'];
		$this->sendData($nCmds);
		
		$nCmds = $this->tC['sCmd'] . $this->tC['subOption'] . $this->tC['negWindowSize'] . chr(0x0) . chr(0xab) . chr(0x0) . chr(0xff) . $this->tC['sCmd'] . $this->tC['sEnd'];
		$this->sendData($nCmds);

		$nCmds = $this->tC['sCmd'] . $this->tC['subOption'] . $this->tC['termSpeed'] . chr (0x00) . "300,300" . $this->tC['sCmd'] . $this->tC['sEnd'];
		$this->sendData($nCmds);

		$nCmds = $this->tC['sCmd'] . $this->tC['subOption'] . $this->tC['XDisplayLoc'] . chr(0x0) . "127.0.0.1:0" . $this->tC['sCmd'] . $this->tC['sEnd'];
		$this->sendData($nCmds);

		$nCmds = $this->tC['sCmd'] . $this->tC['subOption'] . $this->tC['newEnvOption'] . chr(0x0) . "HOME=/" . $this->tC['sCmd'] . $this->tC['sEnd'];
		$this->sendData($nCmds);

		$nCmds = $this->tC['sCmd'] . $this->tC['subOption'] . $this->tC['termType'] . chr(0x0) . "XTERM-256COLOR" . $this->tC['sCmd'] . $this->tC['sEnd'];
		$this->sendData($nCmds);

		$nCmds = $this->tC['sCmd'] . $this->tC['wont'] . $this->tC['echo'];
		$this->sendData($nCmds);usleep(100000);

		$nCmds = $this->tC['sCmd'] . $this->tC['dont'] . $this->tC['echo'];
		$this->sendData($nCmds);usleep(100000);

		$nCmds = $this->tC['sCmd'] . $this->tC['wont'] . $this->tC['remoteFlowControl'];
		$this->sendData($nCmds);usleep(100000);
		

		$nCmds = $this->tC['sCmd'] . $this->tC['will'] . $this->tC['echo'];
		$this->sendData($nCmds);usleep(100000);
		$nCmds = $this->tC['sCmd'] . $this->tC['dont'] . $this->tC['echo'];
		$this->sendData($nCmds);usleep(100000);
		$nCmds = $this->tC['sCmd'] . $this->tC['will'] . $this->tC['echo'];
		$this->sendData($nCmds);usleep(100000);
		
		return(0);
	}
	public function checkHost($host, $ip) {
		$gh = gethostbyname($host);
		if (preg_match("/{$gh}/", "{$host}")) {
			echo "Hostname does not resolve to IP -- no DNS A Record, device may be deprecated or still being added to tools\n";
			echo "gethostbyname({$host}) returns {$gh}, SevOne says {$ip}\n";
			return(-1);
		}
		if (!preg_match("/{$gh}/", "{$ip}")) {
			echo "gethostbyname() reports {$gh}, launcher told us {$ip}.. quitting\n";
			echo "CMTS-DCA: {$host}" . "<pre>DNS A Record for this device does not match the IP in SevOne\nResolved IP: {$gh}\nSevOne IP: {$ip}\n</pre>";
			return(-1);
		}
		return(0);
	}

}


?>
