<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

	class Accommodations extends MY_Controller {
		public function __construct()
		{
			parent::__construct();
			$this->load->model(array('property'));

			$this->config->load('property',true,true);

			//	Get currently selected property
			$property = $this->session->userdata('property_data');
			$this->property_id = isset($property['property_id']) ? intval($property['property_id']) : null;
		}

		// --------------------------------------------------------------------------
		//	
		// --------------------------------------------------------------------------

		public function dashboard()
		{
			$property = $this->property->do_fetch_property($this->property_id);
			
			$data = array();
			$data['property_accommodations'] = array();
			
			if(count($property->accommodations()) > 0){
				foreach($property->accommodations() as $property_accommodation){
					if( $property_accommodation->is_active == 1 ){
						$data['property_accommodations'][$property_accommodation->id] = array(
								'name' => $property_accommodation->name,
								'standard_occupancy' => $property_accommodation->standard_occupancy,
								'maximum_occupancy' => $property_accommodation->maximum_occupancy,
								'type' => array(
									'id' => $property_accommodation->type()->id,
									'type' => $property_accommodation->type()->type,
								),
						);
					}
				}
			}
			
			$this->template
				->set('active','accommodations')
				->set('page_title','Property:')
				->set('page_sub_title','Edit Accommodations')
				->set_partial('header','layouts/global/html/header')
				->set_partial('footer','layouts/global/html/footer')
				->set_partial('layout_header','layouts/global/html/layout_header')
				->set_partial('leftnav','layouts/nav/property/property_description/left')
				->set_layout('2column_leftnav')
				->build('content/properties/accommodations/dashboard',$data);

		}
		
		public function record($property_accommodation_id = null)
		{
			//	Fetch requested Data
			$property_accommodation = $this->property->do_fetch_property_accommodation($property_accommodation_id);
			
			// validate user has access
			$user_session = $this->session->userdata('user_data');
			if( $user_session['user_type'] != 'administrator' && is_null($property_accommodation) == false ){
				$this->load->model(array('user'));
				$user = $this->user->do_fetch_user($user_session['user_handle']);
				$user_properties = array();
				foreach($user->properties() as $record){
					$user_properties[] = $record->record['data']['id'];
				}

				if( !in_array($property_accommodation->record['data']['property_id'],$user_properties) ){
					header('HTTP/1.0 403 Forbidden');
					header('Location: /login');
					exit(0);
				}
			}
			
			if(!is_null($property_accommodation_id) && count($property_accommodation) == 0)
			{
				$this->alert->set('error','Invalid accommodation selected');
				redirect('properties/accommodations/dashboard');
			}
			
			if($this->input->post('property-accommodation-save'))
			{
				$success = true;
				$this->form_validation->set_rules('property_accommodation[property_accommodation_type_id]', 'Room Type', 'integer|required');
				$this->form_validation->set_rules('property_accommodation[name]', 'Room Name', 'trim|required|callback__check_property_accommodation_name[' . $property_accommodation_id . ']');
				$this->form_validation->set_rules('property_accommodation[standard_occupancy]', 'Standard Room Occupancy', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[maximum_occupancy]', 'Maximum Room Occupancy', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[adult_maximum_occupancy]', 'Maximum Adult Occupancy', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[child_maximum_occupancy]', 'Maximum Child Occupancy', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[adult_rate_adjustment]', 'Per Adult Fee', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[child_rate_adjustment]', 'Per Child Fee', 'numeric|required');
				$this->form_validation->set_rules('property_accommodation[short_description]', 'Short Description', 'trim|required');
				$this->form_validation->set_rules('property_accommodation[bedding]', 'Bedding', 'trim|required');
				$this->form_validation->set_rules('property_accommodation[long_description]', 'Long Description', 'trim|required');
				
				if($this->form_validation->run() === true)
				{
					$form_data = $this->input->post();

					//	Save the base property
					$property_accommodation_data = $form_data['property_accommodation'];
					$property_accommodation_data['long_description_raw'] = $property_accommodation_data['long_description'];
					$property_accommodation_data['long_description'] = $this->make_markdown(true,$property_accommodation_data['long_description']);
					$result = $this->property->do_save_property_accommodation($this->property_id,$property_accommodation_id,$property_accommodation_data);

					if(is_numeric($result))
					{
						$property_accommodation_id = $result;
						
						// Existing record, check for image uploads/deletions and handle accordingly
						if (is_numeric($property_accommodation_id) && !empty($_FILES['accommodation_image_file']) && $_FILES['accommodation_image_file']['error'] != 4)
						{
							$upload = $this->_upload_image(array('field'=>'accommodation_image_file','filesize'=>2097152));
							// If error, send response
							if (isset($upload['error']))
							{
								$this->alert->set('error',$upload['error']['message']);
							}
							// If no error, move files to final location, create thumbnails
							else
							{
								// Get temp filename, new path
								$tmpimg = pathinfo($this->config->item('pl_target_folder','plupload').DIRECTORY_SEPARATOR.$upload['success']['filename']);
								$img_path = $this->config->item('accommodation_image_base_system_path','property').DIRECTORY_SEPARATOR.$property_accommodation_id.DIRECTORY_SEPARATOR;

								// Create template for new filenames
								$img_uniq = uniqid();
								$img_filename = $img_path.$img_uniq.'%s.'.$tmpimg['extension'];
								$img_url = str_replace(array($this->config->item('accommodation_image_base_system_path','property'),DIRECTORY_SEPARATOR),
								array($this->config->item('accommodation_image_base_'.(isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on" ? 'https' : 'http').'_url','property'),'/'),
								$img_filename);

								// Create directory if req'd
								if (!is_dir($img_path) && !mkdir($img_path))
								{
									$this->alert->set('error','Unable to create directory.');
									$create_db_entry = false;
								}
								// Move uploaded file, create thumbnails
								else
								{
									// Prep image library
									$this->load->library('image_lib');
									$config = array('image_library' => 'gd2',
									'source_image' => $tmpimg['dirname'].DIRECTORY_SEPARATOR.$tmpimg['basename'],
									'quality' => 65);

									// Copy original file, create optimized at 800x540 65%
									copy($config['source_image'],sprintf($img_filename,''));
									$full_opt = array_merge($config,array(
											'new_image' => sprintf($img_filename,'_opt'),
											'width' => 800,
											'height' => 540
									));
									$this->image_lib->initialize($full_opt); $this->image_lib->resize();

									// Create thumb at 292x197 65%
									$thumb1 = array_merge($config,array(
											'new_image' => sprintf($img_filename,'_medium'),
											'width' => 292,
											'height' => 197
									));

									// Create smaller thumb at 140x95 65%
									$thumb2 = array_merge($config,array(
										'new_image' => sprintf($img_filename,'_small'),
										'width' => 140,
										'height' => 95
									));

									$this->image_lib->clear(); $this->image_lib->initialize($thumb1); $this->image_lib->resize();
									$this->image_lib->clear(); $this->image_lib->initialize($thumb2); $this->image_lib->resize();

									$create_db_entry = true;
								}

								// Delete temp file
								$this->plupload->delete_upload($upload['success']['filename']);

								// If file operations were successful, update database

								// If $_POST['old_accommodation_image'] is not set, add is occurring
								if ($create_db_entry && !isset($_POST['old_accommodation_image']))
								{
									$imgid = $this->property->do_save_property_accommodation_image($property_accommodation_id,array(
											'filename' => $img_uniq,
											'ext' => $tmpimg['extension'],
											'title' => $property_accommodation_data['name'],
											'alt' => $property_accommodation_data['name'],
											'enabled' => 'yes'
									));
									// If add failed (array contains error message), remove new images to prevent clutter
									if (is_array($imgid))
									{
										@unlink(sprintf($img_filename,''));
										@unlink(sprintf($img_filename,'_opt'));
										@unlink(sprintf($img_filename,'_medium'));
										@unlink(sprintf($img_filename,'_small'));
										$this->alert->set('error','Unable to create database entry.');
									}
									else
									{
										$this->alert->set('info','Image upload completed.');
									}
								}
								// If $_POST['old_accommodation_image'] is set, replace is occurring
								elseif ($create_db_entry && isset($_POST['old_accommodation_image']))
								{
									// Get existing image filename
									$old_image = $this->property->do_fetch_property_accommodation_image($property_accommodation_id,$this->input->post('old_accommodation_image'));

									// Update record with new filename
									$imgid = $this->property->do_save_property_accommodation_image($property_accommodation_id,array(
											'id'=> $this->input->post('old_accommodation_image'),
											'filename' => $img_uniq,
											'ext' => $tmpimg['extension'],
											'title' => $form_data['property_accommodation']['name'],
											'alt' => $form_data['property_accommodation']['name'],
											//								'enabled' => $this->input->post('enable_photo'),
									));

									// If update failed(array contains error message), remove new images to prevent clutter
									if (is_array($imgid))
									{
										@unlink(sprintf($img_filename,''));
										@unlink(sprintf($img_filename,'_opt'));
										@unlink(sprintf($img_filename,'_medium'));
										@unlink(sprintf($img_filename,'_small'));
										$this->alert->set('error','Unable to update database.');
									}
									// If update succeeded, remove old images
									else
									{
										@unlink($img_path.$old_image->filename.'.'.$old_image->ext);
										@unlink($img_path.$old_image->filename.'_opt.'.$old_image->ext);
										@unlink($img_path.$old_image->filename.'_medium.'.$old_image->ext);
										@unlink($img_path.$old_image->filename.'_small.'.$old_image->ext);
										$this->alert->set('info','Image upload completed.');
									}
								}
							}
						}

						// Delete existing image (no new upload)
						if (is_numeric($property_accommodation_id) && (empty($_FILES['accommodation_image_file']) || $_FILES['accommodation_image_file']['error'] == 4) && isset($_POST['delete_accommodation_image']))
						{
							// Get existing image filename
							$old_image = $this->property->do_fetch_property_accommodation_image($property_accommodation_id);

							// Delete record
							$this->property->do_remove_property_accommodation_image($property_accommodation_id);

							// Delete files
							$img_path = $this->config->item('accommodation_image_base_system_path','property').DIRECTORY_SEPARATOR.$property_accommodation_id.DIRECTORY_SEPARATOR;
							@unlink($img_path.$old_image->filename.'.'.$old_image->ext);
							@unlink($img_path.$old_image->filename.'_opt.'.$old_image->ext);
							@unlink($img_path.$old_image->filename.'_medium.'.$old_image->ext);
							@unlink($img_path.$old_image->filename.'_small.'.$old_image->ext);

							$this->alert->set('info','Image has been deleted.');
						}

						// Set up messages and redirect
						if( isset($result['modified']) ){
							foreach($result['modified'] as $modified){
								$this->alert->set('info',$modified);
							}
						}
						if( isset($result['created']) ){
							foreach($result['created'] as $created){
								$this->alert->set('info',$created);
							}
						}
					} 
					else{
						$success = false;
					}
				}
				else{
					$success = false;
				}
				
				if($success)
				{
					$this->alert->set('success','Accommodation was saved.');
					redirect('properties/accommodations/record/' . $property_accommodation_id);
				}
			}
			
			
			$data = array(
				'property_provider' => null,
				'property_accommodation' => null
			);

			if( isset($property_accommodation->record['data']['id']) ){
				$data['property_provider'] = $this->property->do_fetch_property_provider($property_accommodation->record['data']['property_id']);
				$property_accommodation_images = $this->property->do_fetch_property_accommodation_images($property_accommodation_id);
				//	Set up property skel
				$data['property_accommodation'] = array(
						'id' => (array_key_exists('id',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['id'] : null,
						'property_accommodation_type_id' => (array_key_exists('property_accommodation_type_id',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['property_accommodation_type_id'] : null,
						'name' => (array_key_exists('name',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['name'] : null,
						'type' => array(
								'id' => (array_key_exists('type',$property_accommodation->record['data']) && array_key_exists('id',$property_accommodation->record['data']['type'])) ? $property_accommodation->record['data']['type']['id'] : null,
								'name' => (array_key_exists('type',$property_accommodation->record['data']) && array_key_exists('name',$property_accommodation->record['data']['type'])) ? $property_accommodation->record['data']['type']['name'] : null,
						),
						'images' => array_shift($property_accommodation_images['data']),
						'short_description' => (array_key_exists('short_description',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['short_description'] : null,
						'bedding' => (array_key_exists('bedding',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['bedding'] : null,
						'long_description_raw' => (array_key_exists('long_description_raw',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['long_description_raw'] : null,
						'standard_occupancy' => (array_key_exists('standard_occupancy',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['standard_occupancy'] : null,
						'maximum_occupancy' => (array_key_exists('maximum_occupancy',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['maximum_occupancy'] : null,
						'adult_maximum_occupancy' => (array_key_exists('adult_maximum_occupancy',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['adult_maximum_occupancy'] : null,
						'child_maximum_occupancy' => (array_key_exists('child_maximum_occupancy',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['child_maximum_occupancy'] : null,
						'adult_rate_adjustment' => (array_key_exists('adult_rate_adjustment',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['adult_rate_adjustment'] : null,
						'child_rate_adjustment' => (array_key_exists('child_rate_adjustment',$property_accommodation->record['data'])) ? $property_accommodation->record['data']['child_rate_adjustment'] : null,
				);
			}
			
			$data['property_accommodation_types'] = array();
			$property_accommodation_types = $this->property->do_fetch_property_accommodation_types();

			if( count($property_accommodation_types) > 0 ){
				foreach($property_accommodation_types as $property_accommodation_type){
					
					$id = $property_accommodation_type->record['data']['id'];
					
					$data['property_accommodation_types'][$id] = array(
						'name' => $property_accommodation_type->record['data']['name'],
					);
				}
			}
			
			$this->template
							->set('active','accommodations')
							->set('page_title', ((is_numeric($property_accommodation_id)) ? 'Modify' : 'Create') . ' Property Accommodation :')
							->set('page_sub_title',(!is_null($data['property_accommodation']['name']) ? $data['property_accommodation']['name'] : 'New'))
							->set_partial('header','layouts/global/html/header')
							->set_partial('footer','layouts/global/html/footer')
							->set_partial('layout_header','layouts/global/html/layout_header')
							->set_partial('leftnav','layouts/nav/property/property_description/left')
							->set_layout('2column_leftnav')
							->build('content/properties/accommodations/form',$data);
		}
		
		public function delete($property_accommodation_id = null)
		{
			//	Fetch requested Data
			$property_accommodation = $this->property->do_fetch_property_accommodation($property_accommodation_id);

			// validate user has access
			$user_session = $this->session->userdata('user_data');
			if( $user_session['user_type'] != 'administrator' ){
				$this->load->model(array('user'));
				$user = $this->user->do_fetch_user($user_session['user_handle']);
				$user_properties = array();
				foreach($user->properties() as $record){
					$user_properties[] = $record->record['data']['id'];
				}
				
				if( !in_array($property_accommodation->record['data']['property_id'],$user_properties) ){
					header('HTTP/1.0 403 Forbidden');
					header('Location: /login');
					exit(0);
				}
			}
			
			if( $property_accommodation && isset($property_accommodation->record['data']['id']) ){
				
				$data = $property_accommodation->record['data'];
				$data['is_active'] = 0;
				
				$result = $this->property->do_save_property_accommodation($property_accommodation->property_id, $property_accommodation->id, $data);
				
				if( $result ){
					$this->property->do_deactivate_property_accommodation_availability($property_accommodation->id);
					$this->alert->set('success','The accommodation was successfully deactivated');
				}
				else{
					$this->alert->set('error','An error was encountered, the acommodation was not deactivated.');
				}
			}
			else{
				$this->alert->set('error','An error was encountered, the acommodation was not found.');
			}

//			if(!is_null($property_accommodation_id) && is_null($property_accommodation))
//			{
//				$this->alert->set('error','Invalid accommodation selected');
//			}
//			elseif($this->property->do_remove_property_accommodation($this->property_id,$property_accommodation_id))
//			{
//				$this->alert->set('success','The accommodation was successfully deleted');
//			}
//			else
//			{
//				$this->alert->set('error','There was an error in deleting this accommodation.');
//			}
			redirect('properties/accommodations/dashboard');
		}
		
		public function plans($property_accommodation_id = null)
		{
			$this->load->library(array('rest'));

			if($this->input->post('property-accommodation-provider-attribute-value-save'))
			{
				$payload_data_xml = '';
				$property_accommodation_provider_attribute_value = $this->input->post('property_accommodation_provider_attribute_value');

				foreach($property_accommodation_provider_attribute_value as $action => $values)
				{
					if( $action != 'description' ){
						foreach($values as $provider_attribute_id => $v)
						{
							if( isset($property_accommodation_provider_attribute_value['is_rack_rate'][$provider_attribute_id]) && $property_accommodation_provider_attribute_value['is_rack_rate'][$provider_attribute_id] ){
								$rack_rate_plan = $property_accommodation_provider_attribute_value['is_rack_rate'][$provider_attribute_id];
							}
							else{
								$rack_rate_plan = '';
							}
							
							if( is_array($v) ){
								foreach($v as $value)
								{
									if( isset($property_accommodation_provider_attribute_value['local_description'][$provider_attribute_id][$value]) ){
										$local_description = htmlentities($property_accommodation_provider_attribute_value['local_description'][$provider_attribute_id][$value]);
									}
									else{
										$local_description = '';
									}

									if( isset($property_accommodation_provider_attribute_value['external_description'][$provider_attribute_id][$value]) ){
										$external_description = htmlentities($property_accommodation_provider_attribute_value['external_description'][$provider_attribute_id][$value]);
									}
									else{
										$external_description = '';
									}

									if( isset($property_accommodation_provider_attribute_value['local_policies'][$provider_attribute_id][$value]) ){
										$local_policies = htmlentities($property_accommodation_provider_attribute_value['local_policies'][$provider_attribute_id][$value]);
									}
									else{
										$local_policies = '';
									}
									
									if( isset($property_accommodation_provider_attribute_value['external_policies'][$provider_attribute_id][$value]) ){
										$external_policies = htmlentities($property_accommodation_provider_attribute_value['external_policies'][$provider_attribute_id][$value]);
									}
									else{
										$external_policies = '';
									}
									
									if( $rack_rate_plan == $value){
										$is_rack_rate = 1;
									}
									else{
										$is_rack_rate = 0;
									}	

									$payload_data_xml .= '<PropertyAccommodationProviderAttributeValue>'
													. '<PropertyAccommodationId>' . $property_accommodation_id . '</PropertyAccommodationId>'
													. '<ProviderAttributeId>' . $provider_attribute_id . '</ProviderAttributeId>'
													. '<Value>' . $value . '</Value>'
													. '<Action>' . $action . '</Action>'
													. '<LocalDescription>' . $local_description . '</LocalDescription>'
													. '<ExternalDescription>' . $external_description . '</ExternalDescription>'
													. '<LocalPolicies>' . $local_policies . '</LocalPolicies>'
													. '<ExternalPolicies>' . $external_policies . '</ExternalPolicies>'
													. '<IsRackRate>' . (INT) $is_rack_rate . '</IsRackRate>'
													. '</PropertyAccommodationProviderAttributeValue>'
													. '';
								}
							}
						}
					}
				}
				
				$payload = ''
						. '<PropertyAccommodationProviderAttributeValues>' . $payload_data_xml . '</PropertyAccommodationProviderAttributeValues>'
						. '';
				
				$request = $this->rest->_create_envelope($payload);
				$return = $this->rest->_request('properties/accommodations/update.xml',$request);
			}
			
			$provider_attribute_id = 2;
			//	We are going to make a call to the API to retrieve this data:
			
			$gets = $this->input->get();
			
			$start = $end = '';
			
			if( !empty($gets['date_range']) ){
				$range = explode('-',$gets['date_range']);
				$start = date('Y-m-d', strtotime($range[0]));
				$end = date('Y-m-d', strtotime($range[1]));
			}
			
			//	Attribute values
			$payload = ''
				. '<PropertyAccommodation>'
				. '<Limit/>'
				. '<Offset/>'
				. '<id>' . $property_accommodation_id . '</id>'
				. '</PropertyAccommodation>'
				. '<PropertyAccommodationProviderAttributeValues>'
				. '<Limit/>'
				. '<Offset/>'
				. '<StartDate>' . $start . '</StartDate>'
				. '<EndDate>' . $end . '</EndDate>'
				. '<PropertyAccommodationId>' . $property_accommodation_id . '</PropertyAccommodationId>'
				. '<ProviderAttributeId>' . $provider_attribute_id . '</ProviderAttributeId>'
				. '</PropertyAccommodationProviderAttributeValues>'
				. '';
			
			$request = $this->rest->_create_envelope($payload);
			//$return = $this->rest->makeRequest('properties/accommodations/fetch.xml',$request);
			$return = $this->rest->_request('properties/accommodations/fetch.xml',$request);

			$data['property_accommodation'] = $data['rate_codes'] = array();
			
			if( isset($return['data']) ) {
				$data['property_accommodation'] = $return['data']['property_accommodation'];
				$data['rate_codes'] = $return['data']['property_accommodation_provider_attribute_values']['property_accommodation_provider_attribute_value'];
			}

			$this->template
							->set('active','properties')
							->set('page_title','Property Settings:')
							->set('page_sub_title','Rate Plans')
							->set_partial('header','layouts/global/html/header')
							->set_partial('footer','layouts/global/html/footer')
							->set_partial('layout_header','layouts/global/html/layout_header')
							->set_partial('leftnav','layouts/nav/administration/system_settings/left')
							->set_layout('2column_leftnav')
							->build('content/properties/accommodations/plans',$data);
		}
		
		// --------------------------------------------------------------------------
		//	FORM VALIDATION METHODS
		// --------------------------------------------------------------------------
		
		public function _check_property_accommodation_name($name = null,$property_accommodation_id=null)
		{
			$return = true;
			
			//	Our record ID
			$where_clause = 'property_id = ' . (INT) $this->property_id . ' AND name = \'' . mysql_real_escape_string($name) . '\'';
			$result = Model\Property_accommodation::where($where_clause)->limit(1)->all(FALSE);
			if(count($result) > 0)
			{
				if(!is_numeric($property_accommodation_id) || (is_numeric($property_accommodation_id) && $result->id != $property_accommodation_id))
				{
					$this->form_validation->set_message('_check_property_accommodation_name', 'The %s field must be unique!');
					$return = false;
				}

			}
			return $return;
		}

	}
