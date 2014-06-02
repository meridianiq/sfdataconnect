<?php
App::uses('AppModel', 'Model');


class Procedure extends AppModel {
	//set db to use for client stored specifications
	//public $useDbConfig = 'rrdb';
	//table definition
	//public $useTable = 'ClientSPs';
	//relationship definition for pulling client fields
	/*
	public $belongsTo = array(
		'Client' => array(
			'className' => 'Client',
			'foreignKey' => 'client_id',
		)
	);
	*/
	public function __spFindAndExecute($id, $obj, $db) {
		$this->useDbConfig = $db;
		$this->useTable = 'ClientSPs';
		$procedureConditions = array('client_id' => $id, 'sf_object_id' => $obj);
		$sp = $this->find('first', array('conditions' => $procedureConditions))['Procedure']['sp_name'];
		$spFinish = $this->query('CALL '.$sp.'()');
		return $spFinish;
	}
}