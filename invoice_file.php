<?php
	namespace Model;

	use \Gas\Core;
	use \Gas\ORM;

	class Invoice_file extends ORM {

		public $primary_key = 'id';

		function _init()
		{
			self::$relationships = array (
					'invoice' => ORM::belongs_to('\\Model\\Invoice'),
					'file' => ORM::belongs_to('\\Model\\File'),
			);

			self::$fields = array(
					'id' => ORM::field('auto[11]'),
					'invoice_id' => ORM::field('numeric[11]',array('required'),'INT'),
					'type' => ORM::field('string',array('required'),'ENUM'),
					'file_id' => ORM::field('char[3,155]'),
					'revenue' => ORM::field('numeric[13,2]',array('required'),'DECIMAL'),
					'commission' => ORM::field('numeric[13,2]',array('required'),'DECIMAL'),
					'created' => ORM::field('datetime',array(),'TIMESTAMP'),
					'modified' => ORM::field('datetime',array(),'DATETIME'),
			);
			
			$this->ts_fields = array('modified','[created]');
		}
	}

/* End of file invoice.php */
/* Location: ./application/models/data/invoice.php */
