<?php
/* Encapsulation for PHPMailer to make things a little more readable in the core script.
   we just pass some values from the ini file into here so we can do a simple sendMail(subject, body) */
   
require_once('phpmailer/class.phpmailer.php');

class encapMail {
	private $from, $to, $subject, $server, $user, $pass, $replyto, $cc;
	private $mail;
	
	public function __construct($to, $from, $server) {
		$this->from = $from;
		$this->to = $to;
		$this->server = $server;
		echo "To: {$to}, From: {$from}, Server: {$server}\n";
		$this->mail = new PHPMailer();
	}
	
	public function sendMail($subject, $body, $attachment = NULL) {
		$this->mail->IsSMTP();
		$this->mail->Host = $this->server;
		$this->mail->SMTPDebug = 2;
		$this->mail->SetFrom($this->from, $this->from);
		$this->mail->Subject = $subject;
		$this->mail->AddAddress($this->to, $this->to);
		$this->mail->MsgHTML($body);
		if (isset($attachment)) {
			$this->mail->AddAttachment($attachment);
		}
		if (!$this->mail->Send()) {
			echo "Mailer error: " . $this->mail->ErrorInfo;
			return(0);
		}
		return(1);
	}
}

?>