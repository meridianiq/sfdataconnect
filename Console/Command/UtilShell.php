<?php
class UtilShell extends AppShell {
	
	public function login_test() {
		$user = $this->args[0];
		$pass = $this->args[1]; 
		$instance = $this->args[2];
	
		//sandbox or production?
		if ($instance == 'login') {
			$type = 'production';
		} else {
			$type = 'sandbox';
		}
		
		//setting target url (instance = test OR login)
		$url = 'https://'.$instance.'.salesforce.com/services/Soap/c/29.0';
		//constructing login string for curl exec, using user and pass
		$loginString = '<?xml version="1.0" encoding="utf-8" ?><env:Envelope xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:env="http://schemas.xmlsoap.org/soap/envelope/"><env:Body><n1:login xmlns:n1="urn:enterprise.soap.sforce.com"><n1:username>'.$user.'</n1:username><n1:password>'.$pass.'</n1:password></n1:login></env:Body></env:Envelope>';
		
		//Logging into salesforce account
		//open connection
		$ch = curl_init();

		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $loginString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: text/xml","SOAPAction: login"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

		//execute post and store result of login attempt
		$loginResult = curl_exec($ch);
		
		//parse login post results and extract necessary information for job details and session id
		$xmlArray = Xml::toArray(Xml::build($loginResult));
		
		if (!$xmlArray1['Envelope']['soapenv:Body']['loginResponse']) {
			$result = 'login failed for ' . $instance . ' instance.';
		} else {
			//parse server url to get server instance of target login
			$serverURL = $xmlArray1['Envelope']['soapenv:Body']['loginResponse']['result']['serverUrl'];
			$server = explode('.', explode('/', $serverURL)[2])[0];
			//retrieve session id from login result
			$sessionId = $xmlArray1['Envelope']['soapenv:Body']['loginResponse']['result']['sessionId'];
			$result = "login successful\n----------------\ninstance: ".$type."\nserver: ".$server."\nsession id: ".$sessionId;
		}
		$this->out(print_r($result));
	}
	
}