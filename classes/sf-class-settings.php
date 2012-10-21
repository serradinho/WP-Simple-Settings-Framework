<?php

/**
 * WP-Simple-Settings-Framework
 *
 * Copyright (c) 2012 Matt Gates.
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 *   * Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *   * Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *   * Neither the names of the copyright holders nor the names of the
 *     contributors may be used to endorse or promote products derived
 *     from this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
 * FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
 * COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
 * INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING,
 * BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT
 * LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN
 * ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * @package     Framework
 * @subpackage  WP-Simple-Settings-Framework
 * @author      Matt Gates <info@mgates.me>
 * @copyright   2012 Matt Gates.
 * @license     http://www.opensource.org/licenses/bsd-license.php  BSD License
 * @link        http://mgates.me
 * @version     1.0
 */

namespace Geczy\WPSettingsFramework;

class SF_Settings_API {

	private $data = array();

	public function __construct( $id, $title, $menu = 'plugins.php' ) {
		$this->assets_url = trailingslashit( plugins_url( 'assets/' , dirname( __FILE__ ) ) );
		$this->id = $id;
		$this->title = $title;
		$this->menu = $menu;

		$this->file = $this->get_main_plugin_file();

		$this->includes();
		$this->actions();

		$this->options = $this->load_options();
		$this->current_options = get_option( $this->id . '_options' );

		$this->parse_options();

		/* If the option has no saved data, load the defaults. */
		/* @TODO: Can prob add this to the activation hook. */
		if ( !$this->current_options ) {
			$this->set_defaults();
		}
	}

	// ==================================================================
	//
	// Getter and setter.
	//
	// ------------------------------------------------------------------

	public function __set( $name, $value ) {
		if ( isset ( $this->data[$name] ) && is_array( $this->data[$name] ) ) {
			$this->data[$name] = array_merge( $this->data[$name], $value );
		} else {
			$this->data[$name] = $value;
		}
	}

	public function __get( $name ) {
		if ( array_key_exists( $name, $this->data ) ) {
			return $this->data[$name];
		}
		return null;
	}

	public function __isset( $name ) {
		return isset( $this->data[$name] );
	}

	public function __unset( $name ) {
		unset( $this->data[$name] );
	}

	/**
	 * Return the main plugin that's calling the Jigoshop
	 * dependencies library.
	 *
	 * @access private
	 *
	 * @return string File path to the main plugin.
	 */
	private function get_main_plugin_file() {
		$file = debug_backtrace();

		// Three functions is how long it took for
		// the main plugin to call us. So three we go!
		$file = $file[2]['file'];

		return $file;
	}

	// Add a "Settings" link to the plugins.php page
	public function add_settings_link( $links, $file ) {
		$this_plugin = plugin_basename( $this->file );
		$page = strpos( $this->menu, '.php' ) ? $this->menu : 'admin.php';
		if ( $file == $this_plugin ) {
			$settings_link = '<a href="'.$page.'?page='.$this->id.'">' . __( 'Settings', 'geczy' ) . '</a>';
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	// ==================================================================
	//
	// Begin initialization.
	//
	// ------------------------------------------------------------------

	private function includes() {
		require_once dirname( __FILE__ ) . '/sf-class-sanitize.php';
		new SF_Sanitize;
	}

	private function actions() {
		add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_init', array( &$this, 'register_options' ) );
		add_action( 'admin_menu', array( &$this, 'create_menu' ) );
		add_filter( 'plugin_action_links', array( &$this, 'add_settings_link' ), 10, 2 );
	}

	/* Resources required on admin screen. */
	public function admin_enqueue_scripts() {
		wp_register_script( 'bootstrap-tooltip' , $this->assets_url . 'js/bootstrap-tooltip.js' ,  array( 'jquery' ), '1.0' );
		wp_register_script( 'select2' , $this->assets_url . 'js/select2/select2.min.js' ,  array( 'jquery' ), '1.0' );
		wp_register_script( 'sf-scripts' , $this->assets_url . 'js/sf-jquery.js' ,  array( 'jquery' ), '1.0' );
		wp_register_style( 'select2' , $this->assets_url . 'js/select2/select2.css' );
		wp_register_style( 'sf-styles' , $this->assets_url . 'css/sf-styles.css' );
	}

	public function admin_print_scripts() {
		wp_enqueue_script( 'bootstrap-tooltip' );
		wp_enqueue_script( 'select2' );
		wp_enqueue_script( 'sf-scripts' );
		wp_enqueue_style( 'select2' );
		wp_enqueue_style( 'sf-styles' );
	}

	public function register_options() {
		register_setting( $this->id . '_options_nonce', $this->id . '_options', array( &$this, 'validate_options' ) );
	}

	public function create_menu() {
		$page = add_submenu_page( $this->menu, $this->title, $this->title, 'manage_options', $this->id, array( &$this, 'init_settings_page' ) );
		add_action( 'admin_print_scripts-' . $page, array( &$this, 'admin_print_scripts' ) );
	}

	private function parse_options() {
		$options = $this->options;

		foreach ( $options as $option ) {

			if ( $option['type'] == 'heading' ) {
				$tab_name = sanitize_title( $option['name'] );
				$this->tab_headers = array( $tab_name => $option['name'] );

				continue;
			}

			$option['tab'] = $tab_name;
			$tabs[$tab_name][] = $option;

		}

		$this->tabs = $tabs;

		return $tabs;
	}

	private function load_options() {
		if ( empty( $this->options ) ) {
			require_once dirname( dirname( __FILE__ ) ) . '/sf-options.php';
			return $options;
		}

		return $this->options;
	}

	public function validate_options( $input ) {
		if ( !isset( $_POST['update'] ) )
			return $this->get_defaults();

		$clean = array();
		$options = $this->options;

		$tabname = $_POST['currentTab'];

		foreach ( $this->current_options as $id => $value ) :
			$clean[$id] = $value;
		endforeach;

		foreach ( $this->tabs[$tabname] as $option ) :

			if ( ! isset( $option['id'] ) )
				continue;

			if ( ! isset( $option['type'] ) )
				continue;

			$id = preg_replace( '/[^a-zA-Z0-9._\-]/', '', strtolower( $option['id'] ) );

		// Set checkbox to false if it wasn't sent in the $_POST
		if ( 'checkbox' == $option['type'] && ! isset( $input[$id] ) )
			$input[$id] = 0;

		// For a value to be submitted to database it must pass through a sanitization filter
		if ( has_filter( 'geczy_sanitize_' . $option['type'] ) ) {
			$clean[$id] = apply_filters( 'geczy_sanitize_' . $option['type'], $input[$id], $option );
		}

		endforeach;

		do_action( 'sf_options_updated', $clean );
		add_settings_error( $this->id, 'save_options', __( 'Settings saved.', 'geczy' ), 'updated' );
		return apply_filters( 'sf_options_on_update', $clean );
	}

	private function set_defaults() {
		$options = $this->get_defaults();
		update_option( $this->id . '_options', $options );
	}

	private function get_defaults() {
		$output = array();
		$config = $this->options;

		foreach ( $config as $option ) {
			if ( ! isset( $option['id'] ) || ! isset( $option['std'] ) || ! isset( $option['type'] ) )
				continue;

			if ( has_filter( 'geczy_sanitize_' . $option['type'] ) ) {
				$output[$option['id']] = apply_filters( 'geczy_sanitize_' . $option['type'], $option['std'], $option );
			}
		}

		return $output;
	}

	private function template_header() {
?>
		<div class="wrap">
			<?php screen_icon(); ?><h2><?php echo $this->title; ?></h2>

			<h2 class="nav-tab-wrapper">
				<?php echo $this->display_tabs(); ?>
			</h2><?php

		if ( !empty ( $_REQUEST['settings-updated'] ) )
			settings_errors();

	}

	private function template_body() {

		if ( empty( $this->options ) ) return false;

		$options = $this->options;
		$tabs = $this->get_tabs();
		$tabname = !empty ( $_GET['tab'] ) ? $_GET['tab'] : $tabs[0]['slug']; ?>

		<form method="post" action="options.php">
			<?php settings_fields( $this->id . '_options_nonce' ); ?>
			<table class="form-table">

			<?php foreach ( $this->tabs[$tabname] as $value ) :
			$this->settings_options_format( $value );
		endforeach; ?>

			</table>

			<p class="submit">
				<input type="hidden" name="currentTab" value="<?php echo $tabname; ?>">
				<input type="submit" name="update" class="button-primary" value="<?php echo sprintf( __( 'Save %s changes', 'geczy' ), $this->tab_headers[$tabname] ); ?>" />
			</p>
		</form> <?php

	}

	private function template_footer() {
		echo '</div>';
	}

	private function settings_options_format( $value ) {
		if ( empty( $value ) )
			return false;

		$defaultOptions = array(
			'name',
			'desc',
			'placeholder',
			'class',
			'tip',
			'id',
			'css',
			'type',
			'std',
			'options',
			'restrict',
			'group',
		);

		foreach ( $defaultOptions as $key ) {
			if ( !is_array( $key ) && !isset( $value[$key] ) ) $value[$key] = '';
			else if ( is_array( $key ) ) foreach ( $key as $val ) $value[$key][$val] = esc_attr( $value[$key][$val] );
		}

		/* Each to it's own variable for slim-ness' sakes. */
		extract( $value );

		$optionVal   = $this->get_option( $id );
		$optionVal   = $optionVal !== false ? esc_attr ( $optionVal ) : false;
		$numberType  = $type == 'number' && !empty( $restrict ) && is_array( $restrict ) ? true : false;
		$title       = $name;
		$name        = $this->id . "_options[{$id}]";

		$grouped     = !$title ? 'style="padding-top:0px;"' : '';
		$tip         =  $tip ? '<a href="#" title="' . $tip . '" class="sf-tips" tabindex="99"></a>' : '';
		$description =  $desc && !$grouped && !$group && $type != 'checkbox' ? '<br /><small>' . $desc . '</small>' : '<label for="' . $id . '"> ' .$desc . '</label>';
		$description =  ( ( $type == 'title' || $type == 'radio' ) && !empty( $desc ) ) ? '<p>' . $desc . '</p>' : $description;

		/* Header of the option. */
		?><tr valign="top">
		<?php if ( $type != 'heading' && $type != 'title' ) : ?>
					<th scope="row" <?php echo $grouped; ?> >

						<?php echo $tip; ?>

						<?php if ( !$grouped ) : ?>
						<label for="<?php echo $name; ?>" class="description"><?php echo $title; ?></label>
						<?php endif; ?>

					</th>
		<?php endif; ?>
					<td <?php echo $grouped; ?> >
		<?php
		/* Meat & footer of the option. */
		switch ( $type ) :

		case 'title':
			?><thead>
			<tr>
				<th scope="col" colspan="2">
					<h3 class="title"><?php echo $title; ?></h3>
					<?php echo $description; ?>
				</th>
			</tr>
		  </thead><?php
		break;

	case 'text'   :
	case 'number' :
		?><input name="<?php echo $name; ?>"
				 id="<?php echo $id; ?>"
				 type="<?php echo $type; ?>"

				 <?php if ( $numberType ): ?>
				 min="<?php echo !empty( $restrict['min'] ) ? $restrict['min'] : ''; ?>"
				 max="<?php echo !empty( $restrict['max'] ) ? $restrict['max'] : ''; ?>"
				 step="<?php echo isset( $restrict['step'] ) ? $restrict['step'] : 'any'; ?>"
				 <?php endif; ?>

				 class="regular-text <?php echo $class; ?>"
				 style="<?php echo $css; ?>"
				 placeholder="<?php echo $placeholder; ?>"
				 value="<?php echo $optionVal !== false ? $optionVal : $std; ?>"
				/>
		<?php echo $description;
		break;

	case 'checkbox':
		?><input name="<?php echo $name; ?>"
				 id="<?php echo $id; ?>"
				 type="checkbox"
				 class="<?php echo $class; ?>"
				 style="<?php echo $css; ?>"
				 <?php if ( $optionVal !== false ) echo checked( $optionVal, 1, false ); else echo checked( $std, 1, false ); ?>
				 />
		<?php echo $description;
		break;

	case 'radio':
		foreach ( $options as $key => $val ) : ?>
					<label class="radio">
					<input type="radio"
						   name="<?php echo $name; ?>"
						   id="<?php echo $key; ?>"
						   value="<?php echo $key; ?>"
						   class="<?php echo $class; ?>"
							<?php if ( $optionVal !== false ) echo checked( $optionVal, $key, false ); else echo checked( $std, $key, false ); ?>
					/>
					<?php echo $val; ?>
					</label><br /><?php
		endforeach;
		echo $description;
		break;

	case 'single_select_page':

		$selected = ( $optionVal !== false ) ? $optionVal : $std;

		$args = array(
			'name'       => $name,
			'id'         => $id,
			'sort_order' => 'ASC',
			'echo'       => 0,
			'selected'   => $selected
		);
		echo wp_dropdown_pages( $args );
		echo $description;
		?><script type="text/javascript">jQuery(function() {jQuery("#<?php echo $id; ?>").select2({ width: '350px' });});</script><?php
		break;

	case 'select':

		$selected = ( $optionVal !== false ) ? $optionVal : $std;

		?><select id="<?php echo $id; ?>"
				  class="<?php echo $class; ?>"
				  style="<?php echo $css; ?>"
				  name="<?php echo $name; ?>"
				  >

		<?php foreach ( $options as $key => $val ) : ?>
					<option value="<?php echo $key; ?>" <?php selected( $selected, $key, true ); ?>>
					<?php echo $val; ?>
					</option>
		<?php endforeach; ?>
		</select>
		<script type="text/javascript">jQuery(function() {jQuery("#<?php echo $id; ?>").select2({ width: '350px' });});</script>
		<?php break;

	case 'textarea':
		?><textarea name="<?php echo $name; ?>"
							id="<?php echo $id; ?>"
							class="large-text <?php echo $class; ?>"
							style="<?php if ( $css ) echo $css; else echo 'width:300px;'; ?>"
							placeholder="<?php echo $placeholder; ?>"
							rows="3"
				  ><?php echo ( $optionVal !== false ) ? $optionVal : $std; ?></textarea>
				<?php echo $description;
		break;

		// Heading for Navigation
	case 'heading' :
		?><h3><?php echo esc_html( $value['name'] ); ?></h3><?php
		break;

		endswitch;

		/* Footer of the option. */
		if ( $type != 'heading' || $type != 'title' ) echo '</td></tr>';

	}

	public function init_settings_page() {

		$this->template_header();
		$this->template_body();
		$this->template_footer();

	}

	private function get_tabs() {
		$tabs = array();
		foreach ( $this->options as $option ) {

			if ( $option['type'] != 'heading' )
				continue;

			$option['slug'] = sanitize_title( $option['name'] );
			unset($option['type']);

			$tabs[] = $option;
		}
		return $tabs;
	}

	// Heading for Navigation
	private function display_tabs() {
		$tabs = $this->get_tabs();
		$tabname = !empty ( $_GET['tab'] ) ? $_GET['tab'] : $tabs[0]['slug'];
		$menu = '';

		foreach ( $tabs as $tab ) {
			$class = $tabname == $tab['slug'] ? 'nav-tab-active' : '';
			$menu .= sprintf( '<a id="%s-tab" class="nav-tab %s" title="%s" href="?page=%s&tab=%s">%s</a>', $tab['slug'], $class, $tab['name'], $this->id, $tab['slug'], esc_html( $tab['name'] ) );
		}

		return $menu;
	}

	public function update_option( $name, $value ) {
		$this->current_options[$name] = $value;
		return update_option( $this->id .'_options', $this->current_options );
	}

	public function get_option( $name, $default = false ) {
		return isset( $this->current_options[$name] ) ? $this->current_options[$name] : $default;
	}

}
