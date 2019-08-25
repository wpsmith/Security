<?php

namespace WPS\WP;

use WPS;
use WPS\Core\Singleton;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WPS\WP\Security' ) ) {
	/**
	 * Class Security
	 *
	 * @package WPS\WP
	 */
	class Security extends Singleton {

		/**
		 * @var array
		 */
		public $defaults = array(
			'heartbeat'              => 'autosave_only',
			'force_strong_passwords' => true,
			'disable_auto_update'    => false,
			'disallow_file_edit'     => true,
		);

		/**
		 * Security constructor.
		 *
		 * @param string[] $post_types Array of post type names.
		 */
		protected function __construct( $args = array() ) {
			$args = wp_parse_args( $args, $this->defaults );

			// Throttle the heartbeat
			if ( 'autosave_only' === $args['heartbeat'] ) {
				Admin\HeartbeatThrottle::get_instance();
			}

			if ( $args['force_strong_passwords'] ) {
				require __DIR__ . '/force-strong-passwords/slt-force-strong-passwords.php';
			}

			if ( $args['disable_auto_update'] ) {
				$this->disable_auto_update();
			} else {
				// Set to auto-update WP to minor versions.
				self::define( 'WP_AUTO_UPDATE_CORE', 'minor' );

				// Auto-update our themes.
				add_filter( 'auto_update_theme', '__return_true' );
			}

			if ( $args['disable_auto_update'] ) {
				self::define( 'DISALLOW_FILE_EDIT', true );
			}

			// Prevent user enumeration.
			add_action( 'parse_request', array( __CLASS__, 'sec_prevent_user_enumeration' ), 999 );
		}

		/**
		 * Gets environment variable.
		 *
		 * @param $name
		 *
		 * @return array|bool|false|mixed|string
		 */
		public static function getenv( $name ) {
			// Allow environment variable override.
			if ( \getenv( $name ) ) {
				return \getenv( $name );
			}

			// Default to constant.
			if ( defined( $name ) ) {
				return constant( $name );
			}

			return false;
		}

		/**
		 * Conditionally defines constants if not already set
		 *
		 * @param string $name Constant name.
		 * @param mixed $value Constant value.
		 *
		 * @return bool Whether constant was defined.
		 */
		public static function define( $name, $value ) {
			if ( ! defined( $name ) ) {
				define( $name, $value );

				return true;
			}

			return false;
		}

		/**
		 * Disables auto updates and notifications.
		 */
		protected function disable_auto_update() {
			add_filter( 'auto_update_core', '__return_false', 9999 );
			add_filter( 'auto_update_translation', '__return_false', 9999 );
			add_filter( 'auto_core_update_send_email', '__return_false', 9999 );
			add_filter( 'send_core_update_notification_email', '__return_false', 9999 );
		}

		/**
		 * Prevents user enumeration.
		 */
		public static function sec_prevent_user_enumeration() {
			if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
				return;
			}
			if ( is_admin() ) {
				return;
			}
			if ( 0 !== preg_match( '#wp-comments-post#', $_SERVER['REQUEST_URI'] ) ) {
				return;
			}
			if ( ! isset( $_REQUEST['author'] ) ) {
				return;
			}
			if ( ! is_numeric( $_REQUEST['author'] ) ) {
				return;
			}

			error_log( 'preventing possible attempt to enumerate users' );
			if ( ! headers_sent() ) {
				header( 'HTTP/1.0 403 Forbidden' );
			}
			die;
		}
	}
}