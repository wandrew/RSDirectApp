<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

	/**
		* invoice model class.
		* 
		* @extends MY_Model
		*/

	class Provider extends MY_Model
	{	
		
		// --------------------------------------------------------------------------

		/**
			* __construct function.
			*
			* Load common resources.
			* 
			* @access public
			* @return void
			*/

		public function __construct()
		{
			parent::__construct();
			//	Load user configuration
			$this->config->load('provider', TRUE);

			log_message('debug', 'Provider: model loaded.');
		}
		
		// --------------------------------------------------------------------------
		//	(CRUD) FETCH-ACTION METHODS
		// --------------------------------------------------------------------------

		public function do_fetch_providers()
		{
			$data = $this->fetch_files();
			
			$return = array('data' => $data);
			// Get total number of results (pagination)
			$return['total'] = count($data);

			return $return;
		}
		
		public function do_filter_providers($filter_parameters = array(),$order_limit = false)
		{
			
			$sql = "
				SELECT 
					`provider`.`id` AS `provider_id`,
					`provider`.`name` AS `provider_name`,
					`provider`.`model` AS `provider_model`
				FROM 
					`provider`
				WHERE `provider`.`id` > 0
			";
			
			$where_clause = $this->_prepare_where($filter_parameters);
			if(!is_null($where_clause))
			{
				$sql .= " AND " . $where_clause . " ";
			}
			
			// Run query and retrieve results

			$results = $this->db->query($sql)->result();
			$data = array();
			foreach($results as $result)
			{
				$data[$result->provider_id]['provider_name'] = $result->provider_name;
				$data[$result->provider_id]['provider_model'] = $result->provider_model;
			}

			$return = array('data' => $data);
			// Get total number of results (pagination)
			$return['total'] = count($data);

			return $return;
		}

		public function do_fetch_provider_attributes($provider_id,$type = null)
		{
			$return = array(
					'data' => false,
					'results' => 0,
			);
			if(is_numeric($provider_id))
			{
				$sql = ''
								. 'SELECT'
								. '`provider_attribute`.`id` AS `provider_attribute_id`,'
								. '`provider_attribute`.`provider_id` AS `provider_attribute_provider_id`,'
								. '`provider_attribute`.`label` AS `provider_attribute_label`, '
								. '`provider_attribute`.`attribute` AS `provider_attribute_attribute` '
								. 'FROM `provider_attribute` '
								. 'WHERE `provider_attribute`.`provider_id` = ' . $provider_id . ' '
								. '';

				if(!is_null($type)) $sql .= 'AND `provider_attribute`.`type` = \'' .$type . '\'';
				
				$results = $this->db->query($sql)->result();
				$data = array();
				foreach($results as $result)
				{
					$data[$result->provider_attribute_id]['id'] = $result->provider_attribute_id;
					$data[$result->provider_attribute_id]['provider_id'] = $result->provider_attribute_provider_id;
					$data[$result->provider_attribute_id]['label'] = $result->provider_attribute_label;
					$data[$result->provider_attribute_id]['attribute'] = $result->provider_attribute_attribute;
				}

				$return = array(
						'data' => $data,
						'total' => count($data),
				);
			}
			return $return;
		}
		
		public function do_fetch_file($file_id = null)
		{
			return $this->fetch_file($file_id);
		}
		
		// --------------------------------------------------------------------------
		//	(CRUD) SAVE-ACTION METHODS
		// --------------------------------------------------------------------------

		public function do_save_file($file_id,$data)
		{
			$return = false;
			$create = false;
			$datestamp = date('Y-m-d H:i:s',time());
			$errors = array();
			
			if(is_array($data))
			{
				$file = null;
				if(is_numeric($file_id))
				{
					$where_clause = '`id` = \'' . $file_id . '\'';
					$result = Model\File::where($where_clause)->limit(1)->all(FALSE);
					if(!empty($result)) $file = $result;
				}
				
				if(!is_null($file))
				{
					$file->modified = $datestamp;
				}
				else
				{
					$create = true;
					$file = new Model\File();
				}
				foreach($data as $column => $value)
				{
					$file->$column = $value;
				}
				if(!$file->save()) array_push($errors,'Failed to save area.');
			}
			
			if(count($errors) > 0) $return = $errors;
			elseif($create) $return = Model\File::last_created()->id;
			else $return = $file->id;
			return $return;
		}
		
		// --------------------------------------------------------------------------
		//	(CRUD) REMOVE-ACTION METHODS
		// --------------------------------------------------------------------------

		public function do_remove_file($file_id = null)
		{
			$return = false;
			
			if(is_numeric($file_id))
			{
				$file = $this->fetch_file($file_id);
				if(!is_null($file))
				{
					if($file->delete()) $return = true;
				}
			}
			
			return $return;
		}
		
		// --------------------------------------------------------------------------
		//	DATA-FETCH METHODS
		// --------------------------------------------------------------------------

		/**
			* fetch_areas function.
			*
			*
			*
			* @access public
			* @return array
			*/

		protected function fetch_files()
		{
			$return = false;
			
			$sql = ''
							. 'SELECT '
							. '`provider`.`id` AS `provider_id`, '
							. '`provider`.`name` AS `provider_name`, '
							. '`provider`.`library` AS `provider_library` '
							. 'FROM `provider`';
			
			$results = $this->db->query($sql)->result();
			$data = array();
			
			foreach($results as $result)
			{
				$data[$result->provider_id]['provider_name'] = $result->provider_name;
				$data[$result->provider_id]['provider_library'] = $result->provider_library;
			}
			
			return $data;
		}
		
		protected function fetch_file($file_id = null)
		{
			$return = null;
			if(is_numeric($file_id))
			{
				$where_clause = '`id` = \'' . $file_id . '\'';
				$result = Model\File::where($where_clause)->limit(1)->all(false);
				if(!empty($result)) $return = $result;
			}
			return $return;
		}
		
	}