<?php
/**
 * Plugin Name: POC Lead Bitrix24
 */

if ( ! class_exists( 'POC_Lead_Bitrix24' ) ) {
	class POC_Lead_Bitrix24
	{
		private static $instance = null;

		protected function __construct()
		{
		    $this->add_packages();

			$this->add_hooks();
		}

		protected function add_packages()
        {
            require_once dirname( __FILE__ ) . '/packages/wp-batch-processing/wp-batch-processing.php';
        }

		protected function add_hooks()
		{
			add_filter( 'bulk_actions-edit-elementor_cf_db', array( $this, 'bulk_actions' ) );

			add_filter( 'handle_bulk_actions-edit-elementor_cf_db', array( $this, 'bulk_actions_handle' ), 10, 3 );

			add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
		}

		public function bulk_actions( $bulk_array )
		{
			$bulk_array['poc_foundation_bitrix24_async'] = 'Send to Bitrix24';

			return $bulk_array;
		}

		public function bulk_actions_handle( $redirect, $doaction, $object_ids )
		{
			$redirect = remove_query_arg( array( 'poc_foundation_bitrix24_async' ), $redirect );

			if ( $doaction = 'poc_foundation_bitrix24_async' ) {
				set_transient( 'poc_foundation_bitrix24_async_waiting_list', $object_ids );
				$redirect = add_query_arg(
					'poc_foundation_bitrix24_async',
					'scheduled',
					$redirect
				);
			}

			return $redirect;
		}

		public function bulk_action_notices()
		{
			if ( empty( $_REQUEST['poc_foundation_bitrix24_async'] ) || $_REQUEST['poc_foundation_bitrix24_async'] != 'scheduled' ) {
				return;
			}

			$url = get_admin_url( null, 'admin.php?page=dg-batches&action=view&id=poc_foundation_bitrix24_async' );

			ob_start(); ?>
			<div id="message" class="updated notice is-dismissible">
				<p>Bitrix24 Async taks have been scheduled. Click <a href="<?php echo $url; ?>">here</a> to run it now.</p>
			</div>
			<?php
			echo ob_get_clean();
		}

		final public static function instance()
		{
			if ( is_null( static::$instance ) ) {
				static::$instance = new static();
			}
			return static::$instance;
		}
	}
}

add_action( 'plugins_loaded', function () {
	POC_Lead_Bitrix24::instance();
} );