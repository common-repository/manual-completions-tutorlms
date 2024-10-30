<?php
/*
Plugin Name: Manual Completions for TutorLMS
Plugin URI: https://www.nextsoftwaresolutions.com/manual-completions-for-tutor/
Description: Manual Completions for TutorLMS lets you check completion as well as manually mark courses, topics, lessons and quizzes as complete.
Author: Next Software Solutions
Version: 1.3
Author URI: https://www.nextsoftwaresolutions.com
Text Domain: manual-completions-tutorlms
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html
*/

use Tutor\Models\LessonModel;
use Tutor\Models\CourseModel;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
class gbmc_tutor_manual_completions {
	public $version = "1.3";
	public $tutor_link = "https://www.nextsoftwaresolutions.com/r/tutor/manual_completions_tutor";

	static public $addon_name = 'Tutor LMS';
	static public $addon_path = 'tutor/tutor.php';
	static public $addon_code = 'tutor';
	public static $addon_version = '';
	static public $grassblade_path = 'grassblade/grassblade.php';
	public static $grassblade_version = '';

	function __construct() {
		if(!is_admin())
			return;

		global $manual_completions_tutor;
		$manual_completions_tutor = array("uploaded_data" => array(), "upload_error" => array(), "course_structure" => array(), "ajax_url" => admin_url("admin-ajax.php"));

		add_action( 'admin_menu', array($this,'menu'), 11);

		add_action( 'wp_ajax_manual_completions_tutor_course_selected', array($this, 'course_selected') );

		add_action( 'wp_ajax_manual_completions_tutor_mark_complete', array($this, 'mark_complete') );

		add_action( 'wp_ajax_manual_completions_tutor_check_completion', array($this, 'check_completion') );

		add_action( 'wp_ajax_manual_completions_tutor_get_enrolled_users', array($this, 'get_enrolled_users') );

		add_filter( 'safe_style_css', function( $styles ) {
			$styles[] = 'display';
			return $styles;
		} );

		if( !empty($_GET['page']) && $_GET['page'] == "grassblade-manual-completions-tutor") {

			if( !empty($_POST["manual_completions_tutor"]) && !empty($_FILES['completions_file']['name'])) {
				add_filter('upload_mimes', array($this, 'upload_mimes'));
				add_action( 'admin_init', array($this, "process_upload"));
			}
			add_action("admin_print_styles", array($this, "manual_completions_scripts"));
		}
	}
	function get_version($plugin_path){

		if(!file_exists(WP_PLUGIN_DIR . '/'. $plugin_path))
			return "";

		if($plugin_path == self::$addon_path && !empty(self::$addon_version))
			return self::$addon_version;

		if($plugin_path == self::$grassblade_path && !empty(self::$grassblade_version))
			return self::$grassblade_version;

		$plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/'. $plugin_path);
		return empty($plugin_data['Version'])? "":$plugin_data['Version'];
		$version = "";
		switch ($plugin_path) {
			case self::$addon_path:
				self::$addon_version = $version = empty($plugin_data['Version'])? "":$plugin_data['Version'];
				break;
			case self::$grassblade_path:
				self::$grassblade_version = $version = empty($plugin_data['Version'])? "":$plugin_data['Version'];
				break;
		}
		return $version;
	}
	function get_enrolled_users() {
		if(!current_user_can("manage_options") || empty($_REQUEST["course_id"]) || !is_numeric($_REQUEST["course_id"]))
			$this->json_out(array("status" => 0, "message" => esc_html__("Invalid Request", 'manual-completions-tutorlms')));

		if(!empty($_REQUEST["course_id"])) {
			$course_id = filter_input(INPUT_POST, 'course_id', FILTER_SANITIZE_NUMBER_INT);
			$user_ids = tutor_utils()->get_students_data_by_course_id($course_id);
			$user_ids = array_map("intVal", $user_ids);
			$this->json_out( array("status" => 1, "data" => $user_ids, "course_id" => $course_id) );
		}
		$this->json_out(array("status" => 0, "message" => esc_html__("Invalid Request", 'manual-completions-tutorlms')));
	}

	function manual_completions_scripts() {
		global $manual_completions_tutor;

		wp_enqueue_script('manual_completions_tutor', plugins_url('/script.js', __FILE__), array('jquery'), $this->version, true );
		wp_enqueue_style("manual_completions_tutor", plugins_url("/style.css", __FILE__), array(), $this->version );
		wp_enqueue_script("select2js", plugins_url("/vendor/select2/js/select2.min.js", __FILE__), array(), $this->version, true );
		wp_enqueue_style("select2css", plugins_url("/vendor/select2/css/select2.min.css", __FILE__), array(), $this->version );

		wp_localize_script( 'manual_completions_tutor', 'manual_completions_tutor',  $manual_completions_tutor);
		wp_add_inline_style("manual_completions_tutor", '#manual_completions_tutor_table .has_xapi {background: url('.esc_url( plugins_url("img/icon-gb.png", __FILE__) ).')}');
	}
	function upload_mimes ( $existing_mimes=array() ) {
	    // add your extension to the mimes array as below
	    $existing_mimes['csv'] = 'text/csv';
	    return $existing_mimes;
	}
	function process_upload() {

		global $manual_completions_tutor;
		if(empty($manual_completions_tutor) || !is_array($manual_completions_tutor))
		$manual_completions_tutor = array();

		$file_name = sanitize_text_field($_FILES['completions_file']['name']);
		if(empty($file_name) || strtolower( pathinfo($file_name, PATHINFO_EXTENSION) ) != "csv" || empty($_FILES["completions_file"]["type"]) || $_FILES["completions_file"]["type"] !== "text/csv" && $_FILES["completions_file"]["type"] !== "application/vnd.ms-excel")
		{
			$manual_completions_tutor["upload_error"] = esc_html__('Upload Error: Invalid file format. Please upload a valid csv file', 'grassblade');
			return;
		}

		require_once(dirname(__FILE__)."/../grassblade/addons/parsecsv.lib.php");
		$csv = new parseCSV( sanitize_text_field($_FILES['completions_file']['tmp_name']) );

		if(empty($csv->data) || !is_array($csv->data) || empty($csv->data[0]))
		{
			$manual_completions_tutor["upload_error"] = esc_html__('Upload Error: Empty csv file', 'grassblade');
			return;
		}
		$csv_data = array();
		foreach ($csv->data as $k => $data) {
			$csv_data[$k] = array();
			foreach ($data as $j => $val) {
				$j = str_replace(" ", "_", strtolower(trim($j)));
				$csv_data[$k][$j] = $val;
			}
		}

		if(!isset($csv_data[0]["user_id"]) || !isset($csv_data[0]["course_id"])) {
			$manual_completions_tutor["upload_error"] = esc_html__('Upload Error: Invalid file format. Expected columns: user_id, course_id, topic_id, lesson_id, quiz_id ', 'grassblade');
			return;
		}

		$uploaded_data = $courses = $course_structure = $rejected_rows = array();
		$allowed_columns = array("user_id", "course_id", "topic_id", "lesson_id", "quiz_id");
		foreach ($csv_data as $k => $data) {
			$row = array();
			$empty_row = true;

			foreach ($allowed_columns as $col) {
				if(!empty($data[$col]))
					$empty_row = false;

				$row[$col] = (isset($data[$col]) && (is_numeric($data[$col]) || $data[$col] == "all"))? $data[$col]:"";
			}

			if($empty_row)
				continue;

			if(empty($row["user_id"])) {
				if(!empty($data["user_email"])) {
					$user = get_user_by("email", sanitize_email($data["user_email"]));
					if(!empty($user->ID))
						$row["user_id"] = $user->ID;
				}
			}
			if(empty($row["user_id"])) {
				if(!empty($data["user_login"])) {
					$user = get_user_by("login", sanitize_user($data["user_login"]));
					if(!empty($user->ID))
						$row["user_id"] = $user->ID;
				}
			}

			if(!empty($row["course_id"]) && !empty($row["user_id"])) {
				$course_id = $row["course_id"];
				if(!empty($courses[$course_id]))
					$course = $courses[$course_id];
				else {
					$course = get_post($course_id);
					if(!empty($course->ID) && $course->post_status == "publish" && $course->post_type == "courses")
						$courses[$course_id] = $course;
					else
						$course = null;
				}

				if(empty($course->ID)) {
					$rejected_rows[] = $k + 2;
					continue;
				}
				if(!isset($course_structure[$course_id]))
					$course_structure[$course_id] = $this->get_course_structure($course);

				if(!empty($row["topic_id"]) && is_numeric($row["topic_id"]) && empty($row["lesson_id"]) && empty($row["quiz_id"]))
					$row["lesson_id"] = "all";
				else if(empty($row["topic_id"]) && empty($row["lesson_id"]) && empty($row["quiz_id"]))
					$row["topic_id"] = "all";

				$uploaded_data[] = $row;
			}
			else
				$rejected_rows[] = $k + 2;
		}

		$manual_completions_tutor["uploaded_data"] 	  = $uploaded_data;
		$manual_completions_tutor["course_structure"] = $course_structure;

		if(!empty($rejected_rows))
		$manual_completions_tutor["upload_error"] = "Rejected Rows: ".implode(", ", $rejected_rows);
	}
	function menu() {
		global $submenu, $admin_page_hooks;
		$icon = plugin_dir_url(__FILE__)."img/icon-gb.png";

		if(empty( $admin_page_hooks[ "grassblade-lrs-settings" ] )) {
			add_menu_page("GrassBlade", "GrassBlade", "manage_options", "grassblade-lrs-settings", array($this, 'menu_page'), $icon, null);
			add_action("admin_print_styles", array($this, "manual_completions_scripts"));
		}

		add_submenu_page("grassblade-lrs-settings", esc_html__('Manual Completions Tutor', "manual-completions-tutorlms"), esc_html__('Manual Completions Tutor LMS', "manual-completions-tutorlms"),'manage_options','grassblade-manual-completions-tutor', array($this, 'menu_page'));
		add_submenu_page("tutor", esc_html__('Manual Completions', "manual-completions-tutorlms"), esc_html__('Manual Completions', "manual-completions-tutorlms"),'manage_options','admin.php?page=grassblade-manual-completions-tutor', array($this, 'menu_page'),4);
	}

	function form() {

		$courses = get_posts("post_type=courses&posts_per_page=-1&post_status=publish");
		$users = get_users(array('fields' => array('ID', 'display_name', 'user_login', 'user_email')));

		$this->manual_completions_scripts();
		include_once (dirname(__FILE__) . "/form.php");
	}
	function menu_page() {

	    if (!current_user_can('manage_options'))
	    {
	      wp_die( esc_html__('You do not have sufficient permissions to access this page.', 'manual-completions-tutorlms') );
	    }

		$dependency_active = true;
	    if (!file_exists(WP_PLUGIN_DIR . '/'. self::$grassblade_path) ) {
	    	$xapi_td = '<td><img src="'.plugin_dir_url(__FILE__).'img/no.png"/> '.$this->get_version(self::$grassblade_path).'</td>';
	    	$xapi_td .= '<td>
							<a class="buy-btn" href="https://www.nextsoftwaresolutions.com/grassblade-xapi-companion/">'.esc_html__("Buy Now", "manual-completions-tutorlms").'</a>
						</td>';
	    	$dependency_active = false;
		}
	    else {
	    	$xapi_td = '<td><img src="'.plugin_dir_url(__FILE__).'img/check.png"/> '.$this->get_version(self::$grassblade_path).'</td>';
	    	if ( !is_plugin_active(self::$grassblade_path) ) {
				$xapi_td .= '<td>'.$this->activate_plugin(self::$grassblade_path).'</td>';
		    	$dependency_active = false;
			}else {
	    		$xapi_td .= '<td><img src="'.plugin_dir_url(__FILE__).'img/check.png"/></td>';
	    	}
	    }

	    if (!file_exists( WP_PLUGIN_DIR . '/'. self::$addon_path ) ) {
	    	$tutor_td = '<td><img src="'.plugin_dir_url(__FILE__).'img/no.png"/> '.$this->get_version(self::$addon_path).'</td>';
	    	$tutor_td .= '<td colspan="2">
							<a class="buy-btn" href="'.esc_url($this->tutor_link).'">'.esc_html__("Buy Now", "grassblade-xapi-tutor").'</a>
						</td>';
			$dependency_active = false;
	    }else
		if(version_compare( $this->get_version(self::$addon_path) , "2.1.0", "<")) {
			$tutor_td = '<td><img src="'.plugin_dir_url(__FILE__).'img/no.png"/> '. $this->get_version(self::$addon_path) . " | Required: 2.1.0+".'</td>';
			$tutor_td .= '<td>'.$this->update_plugin(self::$addon_path).'</td>';
		    	$dependency_active = false;
	    }
	    else {
	    	$tutor_td = '<td><img src="'.plugin_dir_url(__FILE__).'img/check.png"/> '.$this->get_version(self::$addon_path).'</td>';
	    	if ( !is_plugin_active(self::$addon_path) ) {
				$tutor_td .= '<td>'.$this->activate_plugin(self::$addon_path).'</td>';
		    	$dependency_active = false;
			} else {
	    		$tutor_td .= '<td><img src="'.plugin_dir_url(__FILE__).'img/check.png"/></td>';
	    	}
	    }

		if($dependency_active)
			$this->form();
		else {

			$allowed_html = array(
				'td' => array(
					'colspan' => array(),
					'class' => array(),
				),
				'img' => array(
					'src' => array(),
					'alt' => array(),
				),
				'a' => array(
					'href' => array(),
					'onclick' => array(),
					'class' => array(),
				),
				'span' => array(
					'class' => array(),
					'style' => array(
						'display' => array(),
					),
					'id' => array(),
				),
			);
		?>
		<div id="manual_completions_tutor" class="manual_completions_tutor_requirements">
			<h2>
				<img style="margin-right: 10px;" src="<?php echo esc_url(plugin_dir_url(__FILE__)."img/icon_30x30.png"); ?>"/>
				Manual Completions for Tutor
			</h2>
			<hr>
			<div>
				<p class="text">To use Manual Completions for Tutor, you need to meet the following requirements.</p>
				<h2>Requirements:</h2>
				<table class="requirements-tbl">
					<thead>
						<tr>
							<th>SNo</th>
							<th>Requirements</th>
							<th>Installed</th>
							<th>Active</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>1. </td>
							<td><a class="links" href="https://www.nextsoftwaresolutions.com/grassblade-xapi-companion/">GrassBlade xAPI Companion</a></td>
							<?php echo wp_kses($xapi_td, $allowed_html); ?>
						</tr>
						<tr>
							<td>2. </td>
							<td><a class="links" href="<?php echo esc_url($this->tutor_link); ?>">Tutor LMS</a></td>
							<?php
								echo wp_kses($tutor_td, $allowed_html);
							?>
						</tr>
					</tbody>
				</table>
				<br>
			</div>
		</div>
	<?php }
	}
	/**
	 * Generate an activation URL for a plugin like the ones found in WordPress plugin administration screen.
	 *
	 * @param  string $plugin A plugin-folder/plugin-main-file.php path (e.g. "my-plugin/my-plugin.php")
	 *
	 * @return string         The plugin activation url
	 */
	function activate_plugin($plugin)
	{
		$activation_link = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . urlencode( $plugin ), 'activate-plugin_' . $plugin );
		$link = '<a href="#" onClick="return grassblade_tutor_plugin_activate_deactivate(jQuery(this),  \''.$activation_link.'\');">'.esc_html__("Activate", "manual-completions-tutorlms").'<span id="gb_loading_animation" style="display:none; position:unset; margin-right: unset;"><span class="dashicons dashicons-update"></span></span></a>';
		return $link;
	}
	function update_plugin($plugin){
		$activation_link = wp_nonce_url( 'update.php?action=upgrade-plugin&amp;plugin=' . urlencode( $plugin ), 'upgrade-plugin_' . $plugin );
		$link = '<a href="#" onClick="return grassblade_tutor_plugin_activate_deactivate(jQuery(this),\''.$activation_link.'\');">'.esc_html__("Update", "manual-completions-tutorlms").'<span id="gb_loading_animation" style="display:none; position:unset; margin-right: unset;"><span class="dashicons dashicons-update"></span></span></a>';
		return $link;
	}
	function course_selected() {

		if(!current_user_can("manage_options") || empty($_REQUEST["course_id"]))
			$this->json_out(array("status" => 0));

		$course_id = intval(sanitize_text_field($_REQUEST["course_id"]));
		$course = get_post($course_id);

		if(empty($course->ID) || $course->post_status != "publish")
			$this->json_out(array("status" => 0));

		$this->json_out(array("status" => 1, "data" => $this->get_course_structure($course) ));

	}
	function check_completion($return = false) {

		if(!current_user_can("manage_options") || empty($_REQUEST["data"]) || (!is_array($_REQUEST["data"]) && !is_object($_REQUEST["data"])) )
			$this->json_out(array("status" => 0, "message" => "Invalid Data"));

		$completions = map_deep( wp_unslash( $_REQUEST['data'] ), 'sanitize_text_field' );
		foreach ($completions as $k => $completion) {
			$course_id = $completion["course_id"] = intval(sanitize_text_field($completion["course_id"]));
			$topic_id  = $completion["topic_id"]  = (!empty($completion["topic_id"]) && $completion["topic_id"] != "all") ? intval(sanitize_text_field($completion["topic_id"])) : sanitize_text_field($completion["topic_id"]);
			$lesson_id = $completion["lesson_id"] = (!empty($completion["lesson_id"]) && $completion["lesson_id"] != "all") ? intval(sanitize_text_field($completion["lesson_id"])) : sanitize_text_field($completion["lesson_id"]);
			$quiz_id   = $completion["quiz_id"]   = intval(sanitize_text_field($completion["quiz_id"]));
			$user_id   = $completion["user_id"]   = intval(sanitize_text_field($completion["user_id"]));

			if(empty($course_id)) {
				$completions[$k]["message"] = self::get_message("course_not_selected");
				$completions[$k]["status"] = 0;
			}
			else
			if(empty($user_id)) {
				$completions[$k]["message"] = self::get_message("user_not_selected");
				$completions[$k]["status"] = 0;
			}
			else if( !self::is_enrolled($course_id, $user_id) ) {
				$completions[$k]["message"] = self::get_message("not_enrolled");
				$completions[$k]["status"] = 0;
			}
			else
			{

				$completed = null;
				if(!empty($quiz_id))
				$completed = self::is_quiz_passed( $quiz_id, $user_id );
			else if(!empty($lesson_id)) {
				if($lesson_id == "all")
						$completed = self::is_topic_complete($topic_id, $user_id);
					else
					$completed = !empty(tutor_utils()->is_completed_lesson( $lesson_id, $user_id ));
				}else if($topic_id == "all"){
					$course_status = tutor_utils()->is_completed_course($course_id, $user_id);
					$completed = !empty($course_status);
				}
				else
				{
					$completions[$k]["message"] = "Quiz/Lesson not selected.";
					$completions[$k]["status"] = 0;
				}
				if(isset($completed)) {
					$completions[$k]["message"] = is_bool($completed)? (empty($completed)? "Not Completed":"Completed"): "";
					$completions[$k]["status"] 	= 1;
					$completed = is_string($completed)? ($completed == "completed"):$completed;
					$completions[$k]["completed"] 	= intVal($completed);
				}
			}
		}
		if( $return )
			return $completions;

		$this->json_out( array("status" => 1, "data" => $completions) );
	}

	function mark_complete() {

		if(!current_user_can("manage_options") || empty($_REQUEST["data"]) || (!is_array($_REQUEST["data"]) && !is_object($_REQUEST["data"])) )
			$this->json_out(array("status" => 0, "message" => "Invalid Data"));

		$completions = map_deep( wp_unslash( $_REQUEST['data'] ), 'sanitize_text_field' );
		foreach ($completions as $k => $completion) {
			$course_id  = $completion["course_id"] = intval(sanitize_text_field($completion["course_id"]));
			$topic_id   = $completion["topic_id"]  = (!empty($completion["topic_id"]) && $completion["topic_id"] != "all") ? intval(sanitize_text_field($completion["topic_id"])) : sanitize_text_field($completion["topic_id"]);
			$lesson_id  = $completion["lesson_id"] = (!empty($completion["lesson_id"]) && $completion["lesson_id"] != "all") ? intval(sanitize_text_field($completion["lesson_id"])) : sanitize_text_field($completion["lesson_id"]);
			$user_id    = $completion["user_id"] = intval(sanitize_text_field($completion["user_id"]));
			$quiz_id    = $completion["quiz_id"] = intval(sanitize_text_field($completion["quiz_id"]));

			if(empty($course_id)) {
				$completions[$k]["message"] = self::get_message("course_not_selected");
				$completions[$k]["status"] = 0;
			}
			else
			if(empty($user_id)) {
				$completions[$k]["message"] = self::get_message("user_not_selected");
				$completions[$k]["status"] = 0;
			}
			else if( !self::is_enrolled($course_id, $user_id) ) {
				$completions[$k]["message"] = self::get_message("not_enrolled");
				$completions[$k]["status"] = 0;
			}
			else
			{
				$force_completion = false;
				if(!empty($_REQUEST["force_completion"])) {
					$completions[$k]["a"] = "Force Completion";
					$force_completion = true;
				}

				if(!empty($quiz_id))
					$completions[$k] = $this->mark_quiz_complete($completion, $force_completion);
				else if(!empty($lesson_id)){
					if($lesson_id == "all")
						$completions[$k] = $this->mark_topic_complete($completion, $force_completion);
					else
						$completions[$k] = $this->mark_lesson_complete($completion, $force_completion);
				}
				else if($topic_id == "all")
					$completions[$k] = $this->mark_course_complete($completion);
				else
				{
					$completions[$k]["message"] = "Quiz/Lesson not selected.";
					$completions[$k]["status"] = 0;
				}
			}
		}

		$this->json_out( array("status" => 1, "data" => $completions) );
	}

	function mark_topic_complete($completion, $force_completion) {

		$course_id 	= !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;
		$user_id 	= !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;
		$topic_id 	= !empty($completion["topic_id"]) ? intval($completion["topic_id"]) : 0;

		if( empty($user_id) || empty($course_id) || empty($topic_id) ){
			$completion["message"] 	= self::get_message("invalid_data");
			$completion["status"] 	= 0;
			return $completion;
		}

		$is_completed = self::is_topic_complete($topic_id, $user_id);
		if(!empty($is_completed)){
			$completion["message"] 	= self::get_message("already_completed");
			$completion["status"] 	= 1;
		}else{
			$status = array();
			if(! $is_completed ){
				$topic_contents = tutor_utils()->get_course_contents_by_topic( $topic_id, -1 );
				if($topic_contents->have_posts())
				foreach($topic_contents->get_posts() as $post){
					$post_id = $post->ID;
					if($post->post_type == "lesson")
						$status["lesson_".$post_id] = $this->mark_lesson_complete(array("course_id" => $course_id, "user_id" => $user_id, "lesson_id" => $post_id), true);
					else if($post->post_type == "tutor_quiz")
						$status["quiz_".$post_id] = $this->mark_quiz_complete(array("course_id" => $course_id, "user_id" => $user_id, "quiz_id" => $post_id), true);
				}
			}

			$is_completed = self::is_topic_complete($topic_id, $user_id);
			$completion["status"]  = !empty($is_completed) ? 1 : 0;
			$completion["message"] = !empty($is_completed) ? self::get_message('completed') : self::get_message('failed');
		}
		return $completion;
	}

	function is_topic_complete($topic_id, $user_id){
		$topic_id = intval($topic_id);
		$user_id = intval($user_id);

		if(empty($topic_id) || empty($user_id))
			return false;

		$topic_contents = tutor_utils()->get_course_contents_by_topic( $topic_id, -1 );
		if($topic_contents->have_posts()){
			$topic_steps = $topic_contents->get_posts();
			foreach($topic_steps as $step){
				if($step->post_type == "tutor_quiz"){
					if(!self::is_quiz_passed( $step->ID, $user_id ))
						return false;
				}else if($step->post_type == "lesson"){
					if(!tutor_utils()->is_completed_lesson( $step->ID, $user_id ))
						return false;
				}
			}
		}
		return true;
	}

	function mark_course_complete($completion) {
		$course_id = !empty($completion["course_id"]) ? intval($completion["course_id"]) : 0;
		$user_id   = !empty($completion["user_id"]) ? intval($completion["user_id"]) : 0;

		if( empty($user_id) || empty($course_id) ){
			$completion["message"] 	= "Invalid Data";
			$completion["status"] 	= 0;
			return $completion;
		}

		$is_completed = tutor_utils()->is_completed_course($course_id, $user_id);
		if(!empty($is_completed)){
			$completion["message"] 	= "Already Completed!";
			$completion["status"] 	= 1;
		}else {
			$status 		  = array();
			$course_structure = $this->get_course_structure(get_post($course_id));
			if(! $is_completed ){
				if( !empty( $course_structure->topics ) )
				foreach ( $course_structure->topics as $topic_id => $topic ) {
					// $status["topic_".$topic_id] = $this->mark_topic_complete(array("course_id" => $course_id, "user_id" => $user_id, "topic_id" => $topic_id), true);

					if( !empty( $topic->lessons ) )
					foreach ( $topic->lessons as $lesson_id => $lesson ) {
						$status["lesson_".$lesson_id] = $this->mark_lesson_complete(array("course_id" => $course_id, "user_id" => $user_id, "lesson_id" => $lesson_id), true);
					}
					if( !empty( $topic->quizzes ) )
					foreach ( $topic->quizzes as $quiz_id => $quiz ) {
						$status["quiz_".$quiz_id] = $this->mark_quiz_complete(array("course_id" => $course_id, "user_id" => $user_id, "quiz_id" => $quiz_id), true);
					}
				}
			}

			$is_completed = CourseModel::mark_course_as_completed($course_id, $user_id);

			$completion["status"]  		= !empty($is_completed) ? 1 : 0;
			$completion["message"]		= !empty($is_completed) ? self::get_message('completed') : self::get_message('failed');
			$completion["info"]			= $status;
		}
		return $completion;
	}
	function mark_quiz_complete($completion, $force_completion) {
		$quiz_id 	= !empty($completion['quiz_id']) ? intval($completion["quiz_id"]) : 0;
		$user_id 	= !empty($completion['user_id']) ? intval($completion["user_id"]) : 0;
		$course_id 	= !empty($completion['course_id']) ? intval($completion["course_id"]) : 0;

		if( empty($quiz_id) || empty($user_id) || empty($course_id) ){
			$completion["message"] 	= "Invalid Data";
			$completion["status"] 	= 0;
			return $completion;
		}

		if(self::is_quiz_passed( $quiz_id, $user_id )){
			$completion["message"] 	= "Already Completed!";
			$completion["status"] 	= 1;
		}else{
			if($force_completion)
				self::complete_quiz($course_id, $quiz_id, $user_id);
			else{
				if( self::check_xapi_content_completion( $quiz_id, $user_id ) )
					self::complete_quiz($course_id, $quiz_id, $user_id);
			}
			if(self::is_quiz_passed( $quiz_id, $user_id )){
				// Mark Complete course as well if all lessons && quizzes are completed
				$course_complete_percentage = tutor_utils()->get_course_completed_percent( $course_id, $user_id );
				if($course_complete_percentage == 100)
					CourseModel::mark_course_as_completed($course_id, $user_id);

				$completion["message"] 	= self::get_message('completed');
				$completion["status"] 	= 1;
			}else{
				$completion["message"] 	= self::get_message('failed');
				$completion["status"] 	= 0;
			}
		}
		return $completion;
	}

	static function is_enrolled($course_id, $user_id){

		if(empty($course_id) || empty($user_id))
			return false;

		if(TUTOR\Course_List::is_public($course_id))
			return true;

		if(tutor_utils()->is_enrolled($course_id, $user_id))
			return true;

		return false;
	}

	static function complete_quiz($course_id, $quiz_id, $user_id){
		$percentage 	= 100;
		$percentage 	= round($percentage, 2);

		$max_question_allowed = tutor_utils()->max_questions_for_take_quiz( $quiz_id );
		$tutor_quiz_option = (array) maybe_unserialize( get_post_meta( $quiz_id, 'tutor_quiz_option', true ) );
		$tutor_quiz_option['time_limit']['time_limit_seconds'] = 0;

		$attempt_data = array(
			'course_id'                => $course_id,
			'quiz_id'                  => $quiz_id,
			'user_id'                  => $user_id,
			'total_questions'          => $max_question_allowed,
			'total_answered_questions' => 0,
			'attempt_info'             => maybe_serialize( $tutor_quiz_option ),
			'attempt_status'           => 'attempt_ended',
			'attempt_ip'               => tutor_utils()->get_ip(),
			'attempt_started_at'       => date("Y-m-d H:i:s", tutor_time()),
			'attempt_ended_at'         => date("Y-m-d H:i:s", tutor_time()),
			'total_marks'  			   => 100,
			'earned_marks'             => 100,
		);

		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'tutor_quiz_attempts', $attempt_data );
		$attempt_id = (int) $wpdb->insert_id;

		update_comment_meta( $attempt_id, 'earned_mark_percent', $percentage );
		do_action('tutor_quiz_finished', $attempt_id, $quiz_id, $user_id);
	}
	function mark_lesson_complete( $completion, $force_completion ) {
		$user_id   = !empty($completion['user_id']) ? intval($completion["user_id"]) : 0;
		$lesson_id = !empty($completion['lesson_id']) ? intval($completion["lesson_id"]) : 0;
		$course_id = !empty($completion['course_id']) ? intval($completion["course_id"]) : 0;

		if( empty($lesson_id) || empty($user_id) || empty($course_id) ){
			$completion["message"] 	= "Invalid Data";
			$completion["status"] 	= 0;
			return $completion;
		}

		$is_complete = tutor_utils()->is_completed_lesson( $lesson_id, $user_id );
		if( $is_complete ) {
			$completion["message"] = "Already Completed!";
			$completion["status"] = 1;
		}else{
			if($force_completion)
				LessonModel::mark_lesson_complete( $lesson_id, $user_id );
			else{
				if( $this->check_xapi_content_completion( $lesson_id, $user_id ) )
					LessonModel::mark_lesson_complete( $lesson_id, $user_id );
			}
			$is_complete = tutor_utils()->is_completed_lesson( $lesson_id, $user_id );
			if( empty( $is_complete ) ) {
				$completion["status"] = 0;
				$completion["message"] = self::get_message('failed');
			}
			else {
				// Mark Complete course as well if all lessons & quizzes are completed
				$course_complete_percentage = tutor_utils()->get_course_completed_percent( $course_id, $user_id );
				if($course_complete_percentage == 100)
					CourseModel::mark_course_as_completed($course_id, $user_id);

				$completion["status"] = 1;
				$completion["message"] = self::get_message('completed');
			}
		}
		return $completion;
	}

	function get_course_structure($course){
		$course_structure = new stdClass();

		if(empty($this->get_version(self::$addon_path)))
		return $course_structure;

		$course->activity_id = grassblade_post_activityid($course->ID);
		$course_structure->course = $course;

		$topics = tutor_utils()->get_topics( $course->ID );

		$structure_topics = new stdClass();
		while( $topics->have_posts() ){
			$topics->the_post();
			$topic = get_post();
			$structure_topics->{$topic->ID} = new stdClass();
			$structure_topics->{$topic->ID}->topic = $topic;

			/** Add Topic Items */
			$topic_contents = tutor_utils()->get_course_contents_by_topic( get_the_ID(), -1 );
			$structure_lessons = new stdClass();
			$structure_quizzes = new stdClass();
			$topic_contents = $topic_contents->get_posts();
			foreach( $topic_contents as $k => $v){
				if($v->post_type == "lesson"){
					$structure_lessons->{$v->ID} = new stdClass();
					$v->activity_id = grassblade_post_activityid($v->ID);
					$structure_lessons->{$v->ID}->lesson = $v;
					$structure_lessons->{$v->ID} = $this->add_xapi_content_structure($structure_lessons->{$v->ID}, $v->ID);
				}
				if($v->post_type == "tutor_quiz"){
					$structure_quizzes->{$v->ID} = new stdClass();
					$v->activity_id 			 = grassblade_post_activityid($v->ID);
					$structure_quizzes->{$v->ID}->quiz = $v;
					$structure_quizzes->{$v->ID} = $this->add_xapi_content_structure($structure_quizzes->{$v->ID}, $v->ID);
				}
			}

			$structure_topics->{$topic->ID}->lessons = $structure_lessons;
			$structure_topics->{$topic->ID}->quizzes = $structure_quizzes;
		}

		$course_structure->topics = $structure_topics;
		$course_structure = $this->add_xapi_content_structure($course_structure, $course->ID);
		return $course_structure;
	}

	function add_xapi_content_structure($structure, $post_id){
		$xapi_content_ids = grassblade_xapi_content::get_post_xapi_contents( $post_id );
		if(!empty($xapi_content_ids) && is_array($xapi_content_ids)) {
			foreach ($xapi_content_ids as $xapi_content_id) {
				$xapi_content = get_post($xapi_content_id);
				if(!empty($xapi_content->ID) && $xapi_content->post_status == "publish") {
					$xapi_content->activity_id = grassblade_post_activityid($xapi_content->ID);

					if(empty($structure->xapi_contents))
						$structure->xapi_contents = [];

					$structure->xapi_contents[] = $structure->xapi_content = $xapi_content; //Multiple xAPI Contents supported only after LRS v2.3
				}
			}
		}
		return $structure;
	}

	static function is_quiz_passed( $quiz_id, $user_id = 0 ) {
		global $wpdb;

		$user_id             = tutor_utils()->get_user_id( $user_id );
		$attempts            = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}tutor_quiz_attempts WHERE user_id=%d AND quiz_id=%d", $user_id, $quiz_id ) );
		$required_percentage = tutor_utils()->get_quiz_option( $quiz_id, 'passing_grade', 0 );

		foreach ( $attempts as $attempt ) {
			if(!empty($attempt->earned_marks) && !empty($attempt->total_marks)){
				$earned_percentage = $attempt->earned_marks > 0 ? ( ( $attempt->earned_marks * 100 ) / $attempt->total_marks ) : 0;
				if ( $earned_percentage >= $required_percentage ) {
					return true;
				}
			}
		}

		return false;
	}

	static function check_xapi_content_completion($post_id, $user_id){

		if(empty($post_id) || empty($user_id))
			return false;

		$user_id = $user_id;
		$completed = grassblade_xapi_content::post_contents_completed($post_id, $user_id);

		if(is_bool($completed) && $completed) //No content
			return true;

		return (empty($completed) || count($completed) == 0) ? false : true;
	}

	function json_out($data) {
		header('Content-Type: application/json');
		echo wp_json_encode($data);
		exit();
	}

	function get_message($key){
		$messages = array(
			"course_not_selected" => __("Course not selected.", "manual-completions-tutorlms"),
			"user_not_selected"   => __("User not selected.", "manual-completions-tutorlms"),
			"not_enrolled" 		  => __("User not enrolled to course.", "manual-completions-tutorlms"),
			"quiz_not_selected"   => __("Quiz not selected.", "manual-completions-tutorlms"),
			"lesson_not_selected" => __("Lesson not selected.", "manual-completions-tutorlms"),
			"already_completed"   => __("Already Completed!", "manual-completions-tutorlms"),
			"completed"			  => __("Successfully Marked Complete", "manual-completions-tutorlms"),
			"failed" 			  => __("Completion Failed", "manual-completions-tutorlms"),
			"invalid_data" 		  => __("Invalid Data", "manual-completions-tutorlms"),
		);
		return isset($messages[$key])? esc_html($messages[$key]):"";
	}
}

new gbmc_tutor_manual_completions();
