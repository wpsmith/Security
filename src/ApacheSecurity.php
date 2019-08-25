<?php

namespace WPS\WP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( '\WPS\WP\ApacheSecurity' ) ) {
	/**
	 * Class ApacheSecurity
	 *
	 * @package WPS\WP
	 */
	class ApacheSecurity extends Security {

		/**
		 * Default args.
		 *
		 * @var array
		 */
		public $defaults = array(
			'heartbeat'              => 'autosave_only',
			'force_strong_passwords' => true,
			'disable_auto_update'    => false,
			'disallow_file_edit'     => true,
			'comment_length_limit'   => 13000,
			'hotlink_placeholder'    => '',
//			'hotlink_placeholder'    => 'https://i.imgur.com/removed.png',
			'directories'            => array(
				'wp-content/uploads/',
			)
		);

		/**
		 * ApacheSecurity constructor.
		 *
		 * @param array $args Array of post type names.
		 */
		protected function __construct( $args = array() ) {

			parent::__construct( $args );

			add_filter( 'mod_rewrite_rules', function ( $rules ) {
				return "\n# Permalink rules.\n" . $rules;
			} );
			add_filter( 'mod_rewrite_rules', array( $this, 'php_mod_rewrite_rules' ) );
			add_filter( 'mod_rewrite_rules', array( $this, 'include_only_mod_rewrite_rules' ) );
			add_filter( 'mod_rewrite_rules', array( $this, 'wp_config_mod_rewrite_rules' ) );
			add_filter( 'mod_rewrite_rules', array( $this, 'hotlinking_mod_rewrite_rules' ) );
			add_filter( 'mod_rewrite_rules', array( $this, 'options_indexes_mod_rewrite_rules' ), PHP_INT_MAX );
		}

		/**
		 * Adds block indexes rewrite rules formatted for output to an .htaccess file.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 *
		 * @return string mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		public function hotlinking_mod_rewrite_rules( $rules ) {

			if ( ! $this->args['hotlink_placeholder'] ) {
				return $rules;
			}

			$home_root = $this->get_home_root();

			$new_rules = "\n# Block hotlinking.\n";
			$new_rules .= "<IfModule mod_rewrite.c>\n";
			$new_rules .= "RewriteEngine On\n";
			$new_rules .= "RewriteBase $home_root\n";

			# Block the include-only files.
			$_rules = array(
				'%{HTTP_REFERER} !^$',
				'%{HTTP_REFERER} !^http(s)?://(www\.)?' . $home_root['host'] . ' [NC]',
				'\.(jpg|jpeg|png|gif|svg)$ ' . $this->args['hotlink_placeholder'] . ' [NC,R,L]',
			);

			foreach ( $_rules as $_rule ) {
				$new_rules .= sprintf( "RewriteRule %s\n", $_rule );
			}
			$new_rules .= "</IfModule>\n";

			return $rules . $new_rules;

		}

		/**
		 * Adds php execution disablement rewrite rules formatted for output to an .htaccess file.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 *
		 * @return string mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		public function php_mod_rewrite_rules( $rules ) {

			if ( empty( $this->args['directories'] ) ) {
				return $rules;
			}

			$home_root = $this->get_home_root();

			$new_rules = "\n# Disable PHP File Execution\n";
			$new_rules .= "<IfModule mod_rewrite.c>\n";
			$new_rules .= "RewriteEngine On\n";
			$new_rules .= "RewriteBase $home_root\n";

			# Disable PHP file execution.
			foreach ( $this->args['directories'] as $directory ) {
				$new_rules .= "RewriteRule ^" . $directory . ".*\.php$ - [F,L]\n";
			}
			$new_rules .= "</IfModule>\n";

			return $rules . $new_rules;

		}

		/**
		 * Adds block indexes rewrite rules formatted for output to an .htaccess file.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 *
		 * @return string mod_rewrite Rewrite rules formatted for .htaccess.
		 * @todo Ensure this is always added at the end.
		 *
		 */
		public function options_indexes_mod_rewrite_rules( $rules ) {

			$new_rules = "\n# Block indexes\n";
			$new_rules .= "Options -Indexes\n";

			return $rules . $new_rules;

		}

		/**
		 * Adds block wp-config.php rewrite rules formatted for output to an .htaccess file.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 *
		 * @return string mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		public function wp_config_mod_rewrite_rules( $rules ) {

			// # Block wp-config.php
			$new_rules = "\n# Block wp-config.php\n";
			$new_rules .= "<files wp-config.php>\n";
			$new_rules .= "order allow,deny\n";
			$new_rules .= "deny from all\n";
			$new_rules .= "</files>\n";

			return $new_rules . $rules;

		}

		/**
		 * Add include only rewrite rules formatted for output to an .htaccess file.
		 *
		 * @param string $rules mod_rewrite Rewrite rules formatted for .htaccess.
		 *
		 * @return string mod_rewrite Rewrite rules formatted for .htaccess.
		 */
		public function include_only_mod_rewrite_rules( $rules ) {

			$home_root = $this->get_home_root();

			$new_rules = "\n# Block the include-only files.\n";
			$new_rules .= "<IfModule mod_rewrite.c>\n";
			$new_rules .= "RewriteEngine On\n";
			$new_rules .= "RewriteBase $home_root\n";

			# Block the include-only files.
			$_rules = array(
				'^wp-admin/includes/ - [F,L]',
				'!^wp-includes/ - [S=3]',
				'^wp-includes/[^/]+\.php$ - [F,L]',
				'^wp-includes/js/tinymce/langs/.+\.php - [F,L]',
				'^wp-includes/theme-compat/ - [F,L]',
			);

			foreach ( $_rules as $_rule ) {
				$new_rules .= sprintf( "RewriteRule %s\n", $_rule );
			}
			$new_rules .= "</IfModule>\n";

			return $rules . $new_rules;

		}

		/**
		 * Gets the home root for RewriteBase rule.
		 *
		 * @return mixed|string
		 */
		protected function get_home_root() {

			$home_root = parse_url( home_url() );
			if ( isset( $home_root['path'] ) ) {
				$home_root = trailingslashit( $home_root['path'] );
			} else {
				$home_root = '/';
			}

			return $home_root;

		}

	}
}