<?php
/********************************************************************
 * Copyright (C) 2019 Darko Gjorgjijoski (https://darkog.com)
 *
 * This file is part of WP Batch Processing
 *
 * WP Batch Processing is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * WP Batch Processing is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with WP Batch Processing. If not, see <https://www.gnu.org/licenses/>.
 **********************************************************************/

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Direct access is not allowed.' );
}

if ( class_exists( 'WP_Batch' ) ) {

	/**
	 * Class MY_Example_Batch
	 */
	class MY_Example_Batch extends WP_Batch {

		/**
		 * Unique identifier of each batch
		 * @var string
		 */
		public $id = 'poc_foundation_bitrix24_async';

		/**
		 * Describe the batch
		 * @var string
		 */
		public $title = 'Bitrix24 Async';

		/**
		 * To setup the batch data use the push() method to add WP_Batch_Item instances to the queue.
		 *
		 * Note: If the operation of obtaining data is expensive, cache it to avoid slowdowns.
		 *
		 * @return void
		 */
		public function setup() {

			$object_ids = get_transient( 'poc_foundation_bitrix24_async_waiting_list' );

			foreach ( $object_ids as $id ) {
				$this->push( new WP_Batch_Item( $id, array( 'post_id' => $id ) ) );
			}
		}

		/**
		 * Handles processing of batch item. One at a time.
		 *
		 * In order to work it correctly you must return values as follows:
		 *
		 * - TRUE - If the item was processed successfully.
		 * - WP_Error instance - If there was an error. Add message to display it in the admin area.
		 *
		 * @param WP_Batch_Item $item
		 *
		 * @return bool|\WP_Error
		 */
		public function process( $item ) {

			$post_id = $item->get_value( 'post_id' );

			$data = get_post_meta( $post_id, 'sb_elem_cfd' );

			$name = $this->find_data_by_key( $data,'name' );

			$contact_data = array(
				'fields' => array(
					'NAME' => $name,
					'SECOND_NAME' => '_',
					'LAST_NAME' => '_',
					'PHONE' => $this->find_data_by_key( $data,'phone' ),
					'EMAIL' => $this->find_data_by_key( $data,'email' )
				)
			);

			$add_contact = $this->send_bitrix24_request( 'crm.contact.add', $contact_data );

			if ( is_null( $add_contact ) ) {
				return new WP_Error( 302, 'Deal skipped' );
			}

			$contact_id = $add_contact[0];

			$add_deal = $this->send_bitrix24_request( 'crm.deal.add', array(
				'fields' => array(
					'TITLE' => 'New deal for ' . $this->find_data_by_key( $data, 'name' ),
					'STAGE_ID' => 'C19:NEW',
					'CONTACT_ID' => $contact_id,
		            'CATEGORY_ID' => 19
				)
			) );

			if ( is_null( $add_deal ) ) {
				return new WP_Error( 302, 'Deal skipped' );
			}

			return true;
		}

		protected function find_data_by_key( $data, $key )
		{
			$fields = $data[0]['fields_original']['form_fields'];
			$raw_data = $data[0]['data'];

			$index1 = array_search( $key, array_column( $fields, 'custom_id' ) );

			if ( $index1 === false ) {
				return '';
			}

			$label = $fields[$index1]['field_label'];

			if ( empty( $label ) ) {
				$label = "No Label $key";
			}

			$index2 = array_search( $label, array_column( $raw_data, 'label' ) );

			if ( $index2 === false ) {
				return '';
			}

			return $raw_data[$index2]['value'];
		}

		protected function send_bitrix24_request( $method, $body )
		{
			$settings = get_option( 'elementor-bitrix24-integration-settings' );

			$webhook = $settings['webhook'];

			$response = wp_remote_post(
				$webhook . $method,
				[
					'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/66.0.3359.181 Safari/537.36',
					'body' => $body
				]
			);

			if ( is_wp_error( $response ) ) {
				return null;
			}

			$data = wp_remote_retrieve_body( $response );

			if ( empty( $data ) ) {
				return null;
			}

			$data = json_decode( str_replace( '\'', '"', $data ), true );

			if ( ! isset( $data['result'] ) ) {
				return null;
			}

			return (array) $data['result'];
		}

		/**
		 * Called when specific process is finished (all items were processed).
		 * This method can be overriden in the process class.
		 * @return void
		 */
		public function finish() {
			// Do something after process is finished.
			// You have $this->items, or other data you can set.
		}

	}

	/**
	 * Initialize the batches.
	 */
	function wp_batch_processing_init() {
		$batch = new MY_Example_Batch();
		WP_Batch_Processor::get_instance()->register( $batch );
	}

	add_action( 'wp_batch_processing_init', 'wp_batch_processing_init', 15, 1 );
}


