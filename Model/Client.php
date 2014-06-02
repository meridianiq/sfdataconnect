<?php
App::uses('AppModel', 'Model');

class Client extends AppModel {
	/*
	public $hasMany = array(
		'Map' => array(
			'className' => 'Map',
			'foreignKey' => 'client_id',
		),
		'Procedure' => array(
			'className' => 'Procedure',
			'foreignKey' => 'client_id',
		)
	);
	*/
	public function __Map($name, $object, $db) {
		//set db to use for client stored specifications
		$this->useDbConfig = $db;
		//table definition
		$this->useTable = 'Clients';
		//relationship definition for pulling client fields
		$this->primaryKey = 'client_id';
		//find client for reference by name
		$id = $this->findByClientName($name)['Client']['client_id'];
		//set conditions for finding stored procedure to create temp table for client
		//$procedureConditions = array('Procedure.client_id' => $id, 'Procedure.sf_object_id' => $object);
		//$sp = $this->Procedure->find('first', array('conditions' => $procedureConditions))['Procedure']['sp_name'];
		//$spFinish = $this->query('CALL '.$sp.'()');
		//set conditions for finding mapping to sf fields
		//$mapConditions = array('Map.client_id' => $id, 'Map.sf_object_id' => $object);
		//$client = $this->Map->find('all', array('conditions' => $mapConditions));
		return $id;
	}
}