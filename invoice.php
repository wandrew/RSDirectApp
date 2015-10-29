<?php
	namespace Model;

	use \Gas\Core;
	use \Gas\ORM;

	class Invoice extends ORM {

		public $primary_key = 'id';

		function _init()
		{
			self::$relationships = array (
					'property' => ORM::belongs_to('\\Model\\Property'),
					'reservations' => ORM::has_many('\\Model\\Invoice_reservation'),
					'files' => ORM::has_many('\\Model\\Invoice_file'),
					'payments' => ORM::has_many('\\Model\\Invoice_payment'),
			);

			self::$fields = array(
					'id' => ORM::field('auto[11]'),
					'property_id' => ORM::field('numeric[11]',array('required'),'INT'),
					'mm' => ORM::field('numeric[2]',array('required'),'INT'),
					'yyyy' => ORM::field('numeric[4]',array('required'),'INT'),
					'statement_date' => ORM::field('datetime',array(),'DATE'),
					'invoice_date' => ORM::field('datetime',array(),'DATE'),
					'payment_date' => ORM::field('datetime',array(),'DATE'),
					'status' => ORM::field('string',array('required'),'ENUM'),
					'created' => ORM::field('datetime',array(),'TIMESTAMP'),
					'modified' => ORM::field('datetime',array(),'DATETIME'),
			);
			
			$this->ts_fields = array('modified','[created]');
		}
	}

/* End of file invoice.php */
/* Location: ./application/models/data/invoice.php */
