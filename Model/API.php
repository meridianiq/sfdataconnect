<?php
App::uses('AppModel', 'Model');
//RR model for cron shell
class API extends AppModel {

	//highest level function used by shell to control flow of client xml parsing, and salesforce integration 
	public function __dataConnector($clientArray, $SFobject, $instance, $user, $pass, $externalId, $db, $size) {
		//dynamically set db
		$this->useDbConfig = $db;
		//dynamically select table for client data
		$this->useTable = 'SF_Delivery';
		//relate for contacts
		if ($SFobject == 'Contact') {
			$relate = true;
		} else {
			$relate = false;
		}
		//SF.com login function
		$loginResult = $this->__SFlogin($user, $pass, $instance);
		//check if error and return out if so
		if ($loginResult == 'error') {
			return 'Login failed, try again';
		}
		//SF.com job function
		$jobResult = $this->__SFjob($loginResult['server'], $loginResult['sessionId'], $SFobject, $externalId);
		//check if error and return out if so
		if($jobResult == 'error') {
			return 'Job initiation failed, try again';
		}
		//chunk array -> parse into xml -> submit batch
		if ($size == 'full') {
			$list = $this->find('all');
		} else {
			$list = $this->find('all', array('limit' => 1000));
		}
		$list_array = array_chunk($list, 10000);
		//loop through array chunks to parse batches of xml and push to SF
		for ($i = 0; $i < count($list_array); $i++) {
			//parse xml from batched array
			$xmlBatch = $this->__parseXML($clientArray, $list_array[$i], $relate);
			//push batches to SF
			$this->__SFpush($xmlBatch, $loginResult['server'], $jobResult['jobId'], $jobResult['session']);
		}
		return 'Successfully pushed batch(es) to salesforce';
	}
	
	public function __SFpush($xmlBatch, $server, $jobId, $session) {
		//create the header for the batch push
		$header = '<?xml version="1.0" encoding="UTF-8"?>';
		$header.= '<sObjects xmlns="http://www.force.com/2009/06/asyncapi/dataload">';
		$header.= $xmlBatch;
		$header.= '</sObjects>';
		//point to job url for batch submission
		$url = 'https://'.$server.'.salesforce.com/services/async/29.0/job/'.$jobId.'/batch';
		//open connection
		$ch = curl_init();
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $header);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, TRUE);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array($session,"Content-Type: application/xml"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//execute post
		$result = curl_exec($ch);
		return $result;
	}
	
	public function __parseXML($clientArray, $list, $relate) {
		//initialize xml string for xml sum
		$xmlSum = '';
		//loop through list to account for all records in target list
		for ( $i = 0; $i < count($list); $i++) {
			//initialize array to build array for xml string conversion
			$arrayBuilder = [];
			$arrayRelate = [];
			//loop through client arrays - include miq field to sf.com field mapping in storage
			//account for bd id and iardcrdnumber on contact level, if they are included in the client array, they must be imbedded into an extra array, if relate = true
			foreach ($clientArray as $key => $value) {
				if (!($list[$i]['API'][$value['Map']['miq_field']] == '')) {
					if ($relate && ($value['Map']['miq_field'] == 'BD_ID' || $value['Map']['miq_field'] == 'iardcrdnumber')) {
						$arrayRelate['sObject'][$value['Map']['sf_field']] = $list[$i]['API'][$value['Map']['miq_field']]; 
						$arrayBuilder['sObject']['Account'] = $arrayRelate;
					} else {
						$arrayBuilder['sObject'][$value['Map']['sf_field']] =  preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $list[$i]['API'][$value['Map']['miq_field']]);
					}
				}
			}
			//array to valid xml, including character conversion for special characters
			$xmlObject = Xml::fromArray($arrayBuilder, array('format' => 'tags'));
			$xmlString = $xmlObject->asXML();
			$xmlPlain = substr($xmlString,39,-1);	
			$xmlSum .= $xmlPlain;
		}
		return $xmlSum;
	}
	
	public function __SFlogin($user, $pass, $instance) {
		$result = [];
		//sandbox or production?
		if ($instance == 'login') {
			$result['type'] = 'production';
		} else {
			$result['type'] = 'sandbox';
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
		$curlResult = curl_exec($ch);
		
		//parse login post results and extract necessary information for job details and session id
		$xmlArray1 = Xml::toArray(Xml::build($curlResult));
		
		if (!$xmlArray1['Envelope']['soapenv:Body']['loginResponse']) {
			return 'error';
		} else {
			//parse server url to get server instance of target login
			$serverURL = $xmlArray1['Envelope']['soapenv:Body']['loginResponse']['result']['serverUrl'];
			$result['server'] = explode('.', explode('/', $serverURL)[2])[0];
			//retrieve session id from login result
			$result['sessionId'] = $xmlArray1['Envelope']['soapenv:Body']['loginResponse']['result']['sessionId'];
			return $result;
		}
	}
	
	function __SFjob($server, $sessionId, $object, $externalId) {
		//capture result information necessary for batching
		$result = [];
		//create post data necessary to create job
		$url2 = 'https://'.$server.'.salesforce.com/services/async/29.0/job';
		$jobString = '<?xml version="1.0" encoding="UTF-8"?><jobInfo xmlns="http://www.force.com/2009/06/asyncapi/dataload"><operation>upsert</operation><object>'.$object.'</object><externalIdFieldName>'.$externalId.'</externalIdFieldName><contentType>XML</contentType></jobInfo>';
		$session = "X-SFDC-Session: ".$sessionId;
		
		//open connection
		$ch = curl_init();
		//set the url, number of POST vars, POST data
		curl_setopt($ch,CURLOPT_URL, $url2);
		curl_setopt($ch,CURLOPT_POST, 1);
		curl_setopt($ch,CURLOPT_POSTFIELDS, $jobString);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array($session,"Content-Type: application/xml"));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		//execute post
		$curlResult = curl_exec($ch);
		$xmlArray2 = Xml::toArray(Xml::build($curlResult));
		
		if (!$xmlArray2['jobInfo']['id']) {
			return 'error';
		} else {
			$jobResult['session'] = $session;
			$jobResult['jobId']	= $xmlArray2['jobInfo']['id'];
			return $jobResult;
		}
	}
}