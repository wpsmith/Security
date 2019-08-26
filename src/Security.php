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
		 * Default args.
		 *
		 * @var array
		 */
		public $defaults = array(
			'heartbeat'              => 'autosave_only',
			'limit_login_attempts'   => true,
			'force_strong_passwords' => true,
			'disable_auto_update'    => false,
			'disallow_file_edit'     => true,
			'force_ssl_admin'        => true,
			'comment_length_limit'   => 13000,
		);

		/**
		 * The args.
		 *
		 * @var array
		 */
		public $args = array();

		/**
		 * Security constructor.
		 *
		 * @param string[] $post_types Array of post type names.
		 */
		protected function __construct( $args = array() ) {
			$this->args = wp_parse_args( $args, $this->defaults );

			// Throttle the heartbeat
			if ( 'autosave_only' === $this->args['heartbeat'] ) {
				Admin\HeartbeatThrottle::get_instance();
			}

			if ( $this->args['force_strong_passwords'] && file_exists( __DIR__ . '/force-strong-passwords/slt-force-strong-passwords.php' ) ) {
				require __DIR__ . '/force-strong-passwords/slt-force-strong-passwords.php';
			}

			if ( $this->args['limit_login_attempts'] && file_exists( __DIR__ . '/limit-login-attempts-reloaded/limit-login-attempts-reloaded.php' ) ) {
				require __DIR__ . '/limit-login-attempts-reloaded/limit-login-attempts-reloaded.php';
			}

			if ( $this->args['disable_auto_update'] ) {
				$this->disable_auto_update();
			} else {
				// Set to auto-update WP to minor versions.
				self::define( 'WP_AUTO_UPDATE_CORE', 'minor' );

				// Auto-update our themes.
				add_filter( 'auto_update_theme', '__return_true' );
			}

			if ( $this->args['disallow_file_edit'] ) {
				self::define( 'DISALLOW_FILE_EDIT', true );
			}

			if ( $this->args['force_ssl_admin'] ) {
				self::define( 'FORCE_SSL_ADMIN', true );
			}

			// Prevent user enumeration.
			add_action( 'parse_request', array( __CLASS__, 'sec_prevent_user_enumeration' ), 999 );

			// Prevent comments from being too long.
			add_action( 'pre_comment_content', array( $this, 'die_on_long_comment' ), PHP_INT_MAX );

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

		/**
		 * Dies when string/comment is greater than 13000 characters.
		 *
		 * @param string $comment_content The comment content.
		 *
		 * @return mixed
		 */
		public function die_on_long_comment( $comment_content ) {
			if ( strlen( $comment_content ) > $this->args['comment_length_limit'] ) {
				$comment = isset( $_POST['comment'] ) ? $_POST['comment'] : '';
				$title   = 'Comment Declined';
				$message = 'This comment is longer than the maximum allowed size (' . $this->args['comment_length_limit'] . ') and has been dropped.' .
				           '<br>' .
				           __( 'You wrote:', 'wps' ) .
				           '<br>' .
				           $comment;

				if ( function_exists( '__' ) ) {
					wp_die(
						$message
						,
						__( $title, 'wps' ),
						array( 'response' => 413 )
					);
				} else {
					wp_die(
						$message,
						$title,
						array( 'response' => 413 )
					);
				}
			}

			return $comment_content;
		}
	}
}