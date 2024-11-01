<?php
/*
	Plugin Name: WMF Mobile Theme Switcher
	Plugin URI: https://themeforest.net/user/webbu/portfolio
	Description: A theme switcher for mobile devices.
	Version: 1.0.1
	Author: Webbu
	Author URI: http://themeforest.net/user/Webbu
*/

if ( ! defined( 'ABSPATH' ) ) { exit;}

define("WMFTHMSWTCR_PLUGIN_DIR", plugin_dir_path( __FILE__ ) ); 
define("WMFTHMSWTCR_PLUGIN_URL", plugin_dir_url( __FILE__ ));
define("WMFTHMSWTCR_PLUGIN_CSS_URL",WMFTHMSWTCR_PLUGIN_URL."css/");
define("WMFTHMSWTCR_PLUGIN_JS_URL",WMFTHMSWTCR_PLUGIN_URL."js/");
define("WMFTHMSWTCR_PLUGIN_LANG_URL",WMFTHMSWTCR_PLUGIN_URL."languages/");


if (!class_exists('Wmf_Theme_Switcher')) {

	class Wmf_Theme_Switcher{

		private $themes;
		private $selected_theme;
		private $selected_val;

	    public function __construct(){

	    	require_once 'includes/mobile-detect.php';

	    	register_activation_hook(__FILE__, [$this, 'wmf_theme_switcher_activate']);
	    	register_deactivation_hook( __FILE__, [$this, 'wmf_theme_switcher_deactivate']);

	    	add_action('admin_menu', [$this, 'wmf_theme_switcher_add_options_page']);
	    	add_action('admin_init', [$this, 'wmf_theme_switcher_init']);

			add_action('plugins_loaded', [$this, 'wmf_theme_switcher_action'],1);
			add_action('plugins_loaded', [$this, 'wmf_theme_switcher_remobile_translate']);
			add_action('admin_enqueue_scripts', [$this, 'wmf_theme_switcher_enque']);


			add_action( 'wp_ajax_wmf_nagsystem', [$this, 'wmf_ajax_nagsystem'] );
			add_action( 'wp_ajax_nopriv_wmf_nagsystem', [$this, 'wmf_ajax_nagsystem'] );

			add_action( 'admin_head', [$this, 'wmf_check_permission'] );
			add_action( 'plugins_loaded', [$this, 'wmfthsw_check_force_layout'],0);

			$this->themes = wp_get_themes(array( 'allowed' => true ));
	    }

	    public function wmf_check_permission(){
	    	$permission_type = get_filesystem_method();
			if( $permission_type != 'direct' ){
				add_action( 'admin_notices', [$this, 'wmf_permission_notice']);
			}
	    }

	    public function wmfthsw_check_force_layout(){

	    	if (isset($_GET['wmft'])) {
	    		$string = sanitize_text_field($_GET['wmft']);

		    	if (!empty($string)) {
		    		$wmf_thwsw_time_option = get_option('wmf_thwsw_time_option');
		    		if (absint($wmf_thwsw_time_option) == 0) {
		    			setcookie('wmf_force_settings', $string);
		    		}else{
		    			setcookie('wmf_force_settings', $string, time() + absint($wmf_thwsw_time_option));
		    		}
		    	}
	    	}

	    }

	    public function wmf_ajax_nagsystem(){
			check_ajax_referer( 'wmf_nagsystem', 'security');
			header('Content-Type: application/json; charset=UTF-8;');
			
			$nstatus = $result = $nname = '';

			if(isset($_POST['nname']) && $_POST['nname']!=''){
				$nname = sanitize_text_field($_POST['nname']);
			}

		    $user_id = get_current_user_id();

		    if (!empty($user_id)) {
	    		update_user_meta($user_id, $nname, true);
	    		echo json_encode($result);
		    }

			die();
		}

	    public function wmf_theme_switcher_add_options_page() {
			add_options_page(esc_html__('WMF Mobile Theme Switcher','wmf-theme-switcher'), esc_html__('WMF Theme Switcher','wmf-theme-switcher'), 'manage_options', __FILE__, [$this, 'wmf_settings_theme_switcher']);
		}

		public function wmf_theme_switcher_init(){
			register_setting( 'wmf_thw_plugin_options', 'wmf_thwsw_options' );
			register_setting( 'wmf_thw_plugin_ex_options', 'wmf_thwsw_ex_options' );
			register_setting( 'wmf_thw_plugin_time_option', 'wmf_thwsw_time_option' );
		}

		public function wmf_theme_switcher_activate() {

			/*if( is_network_admin() ){
				wp_die( sprintf( esc_html__( 'WMF Mobile Theme Switcher Plugin can not be activated networkwide, but only on each single site. %s%s%s','wmf-theme-switcher' ),'<div><a class="button" href="'.admin_url( 'network/plugins.php' ).'">',esc_html__( 'Back to plugins','wmf-theme-switcher' ),'</a></div>' ) );
			}*/
			$permission_type = get_filesystem_method();
			if( $permission_type == 'direct' ){

				$creds = request_filesystem_credentials( admin_url("/"), '', false, false, array() );
				
				if ( ! WP_Filesystem( $creds ) ) {
					return false;
				}
				global $wp_filesystem;
				if(empty( $wp_filesystem )){
					require_once (ABSPATH . '/wp-admin/includes/file.php');
					WP_Filesystem();
				}
			 	$plugin_path = str_replace( ABSPATH, $wp_filesystem->abspath(), WMFTHMSWTCR_PLUGIN_DIR );
			 	$mu_dir = str_replace( ABSPATH, $wp_filesystem->abspath(), WPMU_PLUGIN_DIR );
				
				if ( file_exists(WPMU_PLUGIN_DIR.'/wmf-theme-switcher-mu.php') ) {
					$wp_filesystem->delete( WPMU_PLUGIN_DIR.'/wmf-theme-switcher-mu.php',true);
				}
				if (file_exists($plugin_path .'mu-plugins/wmf-theme-switcher-mu.php')) {
					if (!file_exists($mu_dir)) {
						$wp_filesystem->mkdir($mu_dir);
					}
					$wp_filesystem->copy($plugin_path .'mu-plugins/wmf-theme-switcher-mu.php',WPMU_PLUGIN_DIR.'/wmf-theme-switcher-mu.php');
				}
			}
		}

		public function wmf_permission_notice() {
			$wmf_notice = get_user_meta(get_current_user_id(), 'wmf_notice', true);
			if (empty($wmf_notice)) {
				$class = 'notice notice-error is-dismissible';
				$message = '<strong>'.esc_html__( 'ERROR (WMF Mobile Theme Switcher)', 'wmf-theme-switcher' ).'</strong>';
				$message .= '<br/>'. wp_sprintf(esc_html__( 'We could not create a folder in your wp-content directory to place mu-plugins to disable plugin command while switching themes. Please add this code %s to your wp-config.php file to enable it.', 'wmf-theme-switcher'),"<code>define('FS_METHOD','direct');</code>");
				$message .= '<button type="button" class="notice-dismiss">
					<span class="screen-reader-text">'.esc_html__( 'Dismiss this notice.', 'wmf-theme-switcher' ).'</span>
				</button>';
				printf( '<div class="%1$s" id="wmfdismiss"><p>%2$s</p></div>', esc_attr( $class ), $message);
			}
		}

		public function wmf_theme_switcher_deactivate(){
			
			$permission_type = get_filesystem_method();
			if( $permission_type == 'direct' ){

				$creds = request_filesystem_credentials( admin_url("/"), '', false, false, array() );
				
				if ( ! WP_Filesystem( $creds ) ) {
					return false;
				}
				global $wp_filesystem;
				if(empty( $wp_filesystem )){
					require_once (ABSPATH . '/wp-admin/includes/file.php');
					WP_Filesystem();
				}
				if ( file_exists(WPMU_PLUGIN_DIR.'/wmf-theme-switcher-mu.php') ) {
					$wp_filesystem->delete( WPMU_PLUGIN_DIR.'/wmf-theme-switcher-mu.php',true);
				}
			}
			
		}
		

	    public function get_stylesheet_of_theme(){
	    	foreach ($this->themes as $theme_key => $theme_value) {
	    		if ($theme_key == $this->selected_val) {
                    if(isset($theme_value->stylesheet)){
                        $this->selected_theme = $theme_value->stylesheet;
                        return $theme_value->stylesheet;
                    }
	    		}
	    	}
	    }

	    public function get_template_name_of_theme(){
	    	return $this->selected_theme;
	    }

	    public function wmf_theme_switcher_remobile_translate() {
		  load_plugin_textdomain( 'wmf-theme-switcher', false, WMFTHMSWTCR_PLUGIN_LANG_URL ); 
		}

		public function wmf_theme_switcher_action(){
	
			$options = get_option('wmf_thwsw_options');

			$cookie = false;

			if (isset($_COOKIE["wmf_force_settings"])) {
				if (!empty($_COOKIE["wmf_force_settings"])) {
					$cookie_val = sanitize_text_field($_COOKIE["wmf_force_settings"]);
					$cookie = true;
				}
			}

			if (isset($_GET['wmft'])) {
	    		$cookie_val = sanitize_text_field($_GET['wmft']);
	    		$cookie = true;
	    	}

			if (!empty($options)) {

				$filters_apply = false;

				if (!$cookie) {
					$wmfMobileDetect = new WMF_Mobile_Detect();
				
					foreach ($options as $opt_key => $opt_value) {
						if (!in_array($options,array('alltablet','allmobile'))) {
							if ($wmfMobileDetect->is($opt_key)) {
								$this->selected_val = $opt_value;
								$filters_apply = true;
							}
						}
					}

					if (isset($options['alltablet'])) {
						if (!empty($options['alltablet'])) {
							if ($wmfMobileDetect->isTablet()) {
								$this->selected_val = $options['alltablet'];
								$filters_apply = true;
							}	
						}
					}

					if (isset($options['allmobile'])) {
						if (!empty($options['allmobile'])) {
							if ($wmfMobileDetect->isMobile() && (!$wmfMobileDetect->isTablet() && !$wmfMobileDetect->is('iPhone') && !$wmfMobileDetect->is('AndroidOS') && !$wmfMobileDetect->is('WindowsPhoneOS') && !$wmfMobileDetect->is('BlackBerry') && !$wmfMobileDetect->is('iPad') )) {
								$this->selected_val = $options['allmobile'];
								$filters_apply = true;
							}	
						}
					}
				}else{
					if (in_array($cookie_val,array("iPhone","AndroidOS","iPad","WindowsPhoneOS","BlackBerry","alltablet","allmobile"))) {
						$this->selected_val = $options[$cookie_val];
						$filters_apply = true;
					}
				}
				
				if ($filters_apply) {
					add_filter('stylesheet', [$this, 'get_stylesheet_of_theme'],0);
					add_filter('template', [$this, 'get_template_name_of_theme'],0);
				}

			}
		} 

		public function wmf_theme_switcher_enque(){

			$screen = get_current_screen();

			if (isset($screen->base)) {
				if ($screen->base == 'settings_page_wmf-mobile-theme-switcher/wmf-theme-switcher') {
					wp_enqueue_style('wmfthemeswitcherstyles', WMFTHMSWTCR_PLUGIN_CSS_URL . 'style.css', array(), '1.0', 'all');
					wp_enqueue_script( 'jquery.multi-select', WMFTHMSWTCR_PLUGIN_JS_URL . 'jquery.multi-select.js', array('jquery','clipboard'), '0.9.12', true );
					wp_enqueue_script( 'clipboard', WMFTHMSWTCR_PLUGIN_JS_URL . 'clipboard.min.js', array('jquery'), '2.0.6', true );
					wp_enqueue_script( 'wmfthemeswitcherjs', WMFTHMSWTCR_PLUGIN_JS_URL . 'functions.js', array('jquery','clipboard','jquery.multi-select'), '1.0', true );
					wp_localize_script( 'wmfthemeswitcherjs', 'wmfthemeswitcherjscm', array(
						'copiedtext' => esc_html__('Copied!','wmf-theme-switcher')
						) 
					);
				}
			}

			wp_enqueue_script( 'wmfthemeswitchergjs', WMFTHMSWTCR_PLUGIN_JS_URL . 'function-dismiss.js', array('jquery'), '1.0', true );
			wp_localize_script( 'wmfthemeswitchergjs', 'wmfthemeswitcherjscmg', array(
				'nonce' => wp_create_nonce('wmf_nagsystem'),
				'ajaxurl' => admin_url( 'admin-ajax.php' )
				) 
			);
		}

		private function get_optiontitle($key){
			switch ($key) {
				case 'iPhone':echo esc_html_e('iPhone Theme','wmf-theme-switcher');break;
				case 'AndroidOS':echo esc_html_e('Android Theme','wmf-theme-switcher');break;
				case 'alltablet':echo esc_html_e('Other Tablet Devices','wmf-theme-switcher');break;
				case 'allmobile':echo esc_html_e('Other Mobile Devices','wmf-theme-switcher');break;
				case 'iPad':echo esc_html_e('iPad Theme','wmf-theme-switcher');break;
				case 'WindowsPhoneOS':echo esc_html_e('Windows Phone Theme','wmf-theme-switcher');break;
				case 'BlackBerry':echo esc_html_e('BlackBerry Theme','wmf-theme-switcher');break;
			}
		}


		public function wmf_settings_theme_switcher(){
			$themes = wp_get_themes(array( 'allowed' => true ));
			$theme_list = '';

			$defaults =  array(	
				"iPhone" => "",
				"iPad" => "",
				"AndroidOS" => "",
				"WindowsPhoneOS" => "",
				"BlackBerry" => "",
				"alltablet" => "",
				"allmobile" => ""
			);
		?>
		<div class="wmfthemeswitchermainwrap">
			<h2><?php esc_html_e('Webbu Mobile Framework - Theme Switcher Options','wmf-theme-switcher');?></h2>
		<div class="wmfthemeswitcherwrap">
			<form method="post" action="options.php">
	        	<h3><?php esc_html_e('Theme Selection','wmf-theme-switcher');?></h3>
				<?php 
				settings_fields('wmf_thw_plugin_options');  
				$options = get_option('wmf_thwsw_options');

				
				if (empty($options)) {
					$options = $defaults;
				}else{
					$options = array_merge($defaults, $options);
				}
				?>
				<table class="form-table wmfthemeswitcher">
					<?php
						foreach ($options as $option_key => $option_value) {
						if (in_array($option_key,array("iPhone","AndroidOS","iPad","WindowsPhoneOS","BlackBerry","alltablet","allmobile"))) {
					?>
	                <tr valign="top">
						<th scope="row"><?php echo $this->get_optiontitle($option_key);?></th>
						<td>
						<label>
							<select name="wmf_thwsw_options[<?php echo $option_key;?>]">
								<option value=""><?php echo esc_html__('Please Select','wmf-theme-switcher');?></option>
								<?php 
									foreach ($themes as $key => $value) {
										echo '<option value="'.$key.'" '.selected( $option_value, $key, true ).'>'.$value->get('Name').'</option>';
									}
								?>
							</select>
						</label>
						</td>
					</tr>
					<?php 
					}
					}
					?>
	            
				</table>
				<?php 
					settings_fields('wmf_thw_plugin_ex_options');
					$all_plugins = get_plugins();
					$active_plugins = get_option('active_plugins');
					$plugin_options = get_option('wmf_thwsw_ex_options');
					if (empty($plugin_options)) {
						$plugin_options = array();
					}
				?>
				<div class="wmfpluginselectarea">
					<h3><?php esc_html_e('Global Plugin Selection','wmf-theme-switcher');?></h3>
					<p><?php echo esc_html__('You can select plugins to disable from the below list to disable while using Mobile Device Theme.','wmf-theme-switcher');?></p>
					<select multiple="multiple" id="wmf_thwsw_ex_options" name="wmf_thwsw_ex_options[]">
					  <?php
				  		foreach ( $active_plugins as $index => $plugin ) {
							if ( array_key_exists( $plugin, $all_plugins ) ) {
								if ($plugin != 'wmf-theme-switcher/wmf-theme-switcher.php') {
						  	?>
						      <option value='<?php echo $plugin;?>' <?php if (in_array($plugin, $plugin_options)) {echo 'selected';};?>><?php echo $all_plugins[ $plugin ][ 'Name' ];?></option>
					      	<?php
					      		}
				  			}
				  		}
				      ?>
				    </select>
				    <small style="display: block;margin-top:10px"><?php echo esc_html__('Please remove all selected plugins to disable this system.','wmf-theme-switcher');?></small>
					<p></p>
				</div>

				<?php 
				settings_fields('wmf_thw_plugin_time_option');
				$wmf_thwsw_time_option = get_option('wmf_thwsw_time_option');
				if (empty($wmf_thwsw_time_option)) {
					$wmf_thwsw_time_option = '0';
				}
				?>
				<div class="wmfpluginselectarea">
					<h3><?php esc_html_e('Manually Trigger Lifespan','wmf-theme-switcher');?></h3>
					<p><?php echo esc_html__('If you planning to use manually trigger links, please select a lifespan for it.','wmf-theme-switcher');?></p>
					<select name="wmf_thwsw_time_option">
		            	<option value="0" <?php selected($wmf_thwsw_time_option, '0',true);?>><?php echo esc_html__('Until Browser is Closed','wmf-theme-switcher');?></option>
		                <option value="3600" <?php selected($wmf_thwsw_time_option, '3600',true);?>>1 <?php echo esc_html__('Hour','wmf-theme-switcher');?></option>
		                <option value="7200" <?php selected($wmf_thwsw_time_option, '7200',true);?>>2 <?php echo esc_html__('Hours','wmf-theme-switcher');?></option>
		                <option value="10800" <?php selected($wmf_thwsw_time_option, '10800',true);?>>3 <?php echo esc_html__('Hours','wmf-theme-switcher');?></option>
		                <option value="21600" <?php selected($wmf_thwsw_time_option, '21600',true);?>>6 <?php echo esc_html__('Hours','wmf-theme-switcher');?></option>
		                <option value="43200" <?php selected($wmf_thwsw_time_option, '43200',true);?>>12 <?php echo esc_html__('Hours','wmf-theme-switcher');?></option>
		                <option value="86400" <?php selected($wmf_thwsw_time_option, '86400',true);?>>1 <?php echo esc_html__('Day','wmf-theme-switcher');?></option>
		                <option value="604800" <?php selected($wmf_thwsw_time_option, '604800',true);?>>1 <?php echo esc_html__('Week','wmf-theme-switcher');?></option>
		                <option value="2592000" <?php selected($wmf_thwsw_time_option, '2592000',true);?>>1 <?php echo esc_html__('Month','wmf-theme-switcher');?></option>
		            </select>
				</div>
				<p class="submit">
				<input type="submit" class="button-primary" value="<?php esc_html_e('Save Changes','wmf-theme-switcher'); ?>" />
				</p>

				<div class="wmflinksselectarea">
					<h3><?php esc_html_e('Manually Trigger Links','wmf-theme-switcher');?></h3>
					<p><?php echo esc_html__('You can use the below links to activate and deactivate the mobile theme manually. These links activate the theme and use it until you click to return to the desktop theme link.','wmf-theme-switcher');?></p>
					<p><strong><?php esc_html_e('Desktop Theme','wmf-theme-switcher');?>:</strong> <?php echo esc_url(add_query_arg(array('wmft' => 'desktop'), home_url("/")));?> <span class="wmfcopylink" data-clipboard-text="<?php echo esc_url(add_query_arg(array('wmft' => 'desktop'), home_url("/")));?>"><img src="<?php echo WMFTHMSWTCR_PLUGIN_URL;?>/images/copy.png" alt="<?php echo esc_html__('Click to copy','wmf-theme-switcher');?>" title="<?php echo esc_html__('Click to copy','wmf-theme-switcher');?>"></span></p>
					<?php foreach ($options as $option_key => $option_value) {?>
					<p><strong><?php echo $this->get_optiontitle($option_key);?>:</strong> <?php echo esc_url(add_query_arg(array('wmft' => $option_key), home_url("/")));?> <span class="wmfcopylink" data-clipboard-text="<?php echo esc_url(add_query_arg(array('wmft' => $option_key), home_url("/")));?>"><img src="<?php echo WMFTHMSWTCR_PLUGIN_URL;?>/images/copy.png" alt="<?php echo esc_html__('Click to copy','wmf-theme-switcher');?>" title="<?php echo esc_html__('Click to copy','wmf-theme-switcher');?>"></span></p>
					<?php }?>
					<small><?php echo esc_html__('Note, this is a cookie-based system, and it will add cookies to remember the device.','wmf-theme-switcher');?></small>
					<p></p>
				</div>
			</form>


		</div>
		
		<div class="wmfthemeswitchersupportwrap"><p><?php echo wp_sprintf( esc_html__('Please use %sthis link%s to send us support questions.','wmf-theme-switcher'), '<a href="https://wordpress.org/support/plugin/wmf-mobile-theme-switcher/" target="_blank">','</a>'); ?></p></div>
		<a class="wmfthemeswitcherlinkinfowrap" href="https://1.envato.market/gxY6O" target="_blank"><img src="<?php echo WMFTHMSWTCR_PLUGIN_URL;?>/images/aura.jpg"></a>
		</div>
		<?php	
		}
	}
}

new Wmf_Theme_Switcher();
