<?php
App::uses('AppModel', 'Model');


class Map extends AppModel {
	//set db to use for client stored specifications
	//public $useDbConfig = 'rrdb';
	//table definition
	//public $useTable = 'ClientFieldMapping';
	//relationship definition for pulling client fields
	/*
	public $belongsTo = array(
		'Client' => array(
			'className' => 'Client',
			'foreignKey' => 'client_id',
		)
	);
	*/
	
	public function __fields($id, $object, $db) {
		$this->useDbConfig = $db;
		$this->useTable = 'ClientFieldMapping';
		$mapConditions = array('client_id' => $id, 'sf_object_id' => $object);
		$map = $this->find('all', array('conditions' => $mapConditions));
		return $map;
	}
}