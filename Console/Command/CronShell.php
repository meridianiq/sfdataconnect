<?php
class CronShell extends AppShell {
	public $uses = array('API', 'Client', 'Procedure', 'Map');

	public function SFconnect() {
		$client = $this->args[0];
		$obj = $this->args[1];
		// instance = 'login' || 'test'
		$instance = $this->args[2];
		$user = $this->args[3];
		$pass = $this->args[4];
		$extId = $this->args[5];
		$product = $this->args[6];
		$size = $this->args[7];
		//reference object ids in database
		if ($obj == 'Account') {
			$object = 1;
		}
		if ($obj == 'Contact') {
			$object = 2;
		}
		if ($obj == 'Lead') {
			$object = 3;
		}
		//set db and model dynamically
		if ($product == 'rr') {
			$db = 'rrdb';
		} else if ($product == 'ria') {
			$db = 'riadb';
		} else if ($product == 'bd') {
			$db = 'bddb';
		} else {
			$db = 'tdb';
		}
		//call to client mapping function that returns the mapping array based on the sf object input in cli
		$clientId = $this->Client->__Map($client, $object, $db);
		$sp = $this->Procedure->__spFindAndExecute($clientId, $object, $db);
		$clientMap = $this->Map->__fields($clientId, $object, $db);
		//use the mapping and obj definitions to push data to salesforce in batches
		$result = $this->API->__dataConnector($clientMap, $obj, $instance, $user, $pass, $extId, $db, $size);
		//output message formulation
		date_default_timezone_set('America/New_York');
		$date = date('Y-m-d H:i:s');
		$message = $date . ' -- ' . $result;
		$this->out(print_r($message));
	}
	
}