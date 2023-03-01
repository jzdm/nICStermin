<?php

namespace nICStermin;

class Settings
{
	
	public static $pagehook;
	
	public static function init()
	{
		add_action('admin_menu', [__CLASS__, 'add_menu']);
		add_action('admin_init', [__CLASS__, 'add_settings']);
	}
	
	public static function add_menu()
	{
		$page_title = __('nICStermin','nicstermin');
		$menu_title = __('nICStermin','nicstermin');
		$capability = 'manage_options';
		$menu_slug  = 'nicstermin-page-admin-settings';
		$callback   = [__CLASS__, 'page'];
		$position   = null;
		
		self::$pagehook = add_options_page($page_title, $menu_title, $capability, $menu_slug, $callback, $position );
	}
	
	public static function add_settings()
	{
		add_settings_section(
			// $id
			'nicstermin_settingsection_calendars',
			// $title
			'<h1>'. __('Calendar settings','nicstermin') .'</h1>',
			// $callback
			function () { // section head html
			},
			// $page
			self::$pagehook
		);
		
		/**
		 * Timezone setting
		 */
		
		register_setting(
			// $option_group
			self::$pagehook,
			// $option_name
			'nicstermin_opt_timezone');
		add_settings_field(
			// $id
			'nicstermin_settingfield_timezone',
			// $title
			__('Timezone', 'nicstermin'),
			// $callback
			function($args) {
				$option = get_option('nicstermin_opt_timezone');
				
				$timezone_identifiers = \DateTimeZone::listIdentifiers();
				
				echo '<select id="'. esc_attr($args['label_for']) .'" name="nicstermin_opt_timezone">';
				foreach ($timezone_identifiers as $tz) {
					$selected = strcmp($option, $tz) ? '' : 'selected ';
					echo '<option value="'. $tz .'" '. $selected .'>'. $tz .'</option>';
				}
				echo '</select>';
			},
			// $page
			self::$pagehook,
			// $section
			'nicstermin_settingsection_calendars',
			// $args
			['label_for' => 'nicstermin_settinglabel_timezone']
		);
		
		
		/**
		 * Update frequency
		 */
		
		register_setting( self::$pagehook, 'nicstermin_opt_updatefrequency', ['type' => 'string', 'sanitize_callback' =>
				/**
				 * Sanatize update frequency setting
				 */
				function($data)
				{
					$cron_schedule_new = \nICStermin\Cron::setup_interval('nicstermin_croncallback_calendarupdate', $data);
					
					return $cron_schedule_new;
				}
			]);
		add_settings_field('nicstermin_settingfield_updatefrequency', __('Update frequency', 'nicstermin'),
				/**
				 * Display update frequency settings
				 */
				function($args)
				{
					
					$option = get_option('nicstermin_opt_updatefrequency');
					$cron_schedules = wp_get_schedules();
					
					echo '<select id="'. esc_attr($args['label_for']) .'" name="nicstermin_opt_updatefrequency">';
					foreach ($cron_schedules as $sched_key => $schedule) {
						$selected = strcmp($option, $sched_key) ? '' : 'selected ';
						echo '<option value="'. $sched_key .'" '. $selected .'>'. $schedule['display'] .'</option>';
					}
					echo '</select>';
					
					$timezone = get_option('nicstermin_opt_timezone');
					$nextrun  = wp_next_scheduled ( 'nicstermin_croncallback_calendarupdate' );
					if ($nextrun) {
						$timestmp = new \DateTime('now', new \DateTimeZone($timezone));
						$timestmp->setTimestamp($nextrun);
						
						echo '<p>';
						printf(
							/* translators: %1$s is a date and time string of the next automated calendar update */
							__('Next calendar update at %1$s','nicstermin'),
							$timestmp->format('Y-m-d H:i:s T')
						);
						echo '</p>';
					}
				},
				self::$pagehook, 'nicstermin_settingsection_calendars', ['label_for' => 'nicstermin_settinglabel_updatefreq']
			);
		
		
		/**
		 * Calendar subscriptions
		 */
		
		register_setting(self::$pagehook, 'nicstermin_opt_caldata', [
			'type'=>'array',
			'sanitize_callback' => 
			function($data)
			{
				$old_data = get_option('nicstermin_opt_caldata');
				
				foreach ($data as $key => &$datarow) {
					if (!empty($datarow['url'])) {
						$datarow['url']    = sanitize_url($datarow['url']);
						$datarow['sha256'] = hash('sha256', $datarow['url']);
					}
				}
				unset($datarow);
				
				/** if any element is empty, delete the complete row */
				return array_filter($data, function($data)
				{
					$isempty = false;
					foreach($data as $element) {
						$isempty |= empty($element);
					}
					return !$isempty;
				});
			}
		]);
		add_settings_field('nicstermin_settingfield_caldata', __('Calendar Subscriptions', 'nicstermin'),
			function($args)
			{
				$timezone       = get_option('nicstermin_opt_timezone');
				$options        = get_option('nicstermin_opt_caldata');
				$update_reports = get_option('nicstermin_opt_calupdate_reports');
				
				$timestmp = new \DateTime('now', new \DateTimeZone($timezone));
				$timestr  = '';
				
				$counter = 0;
				foreach ($options as $key => $caldataset) {
					echo '<input type="text" value="'. $caldataset['url'] .'"   name="nicstermin_opt_caldata['. $counter .'][url]"   />';
					echo '<input type="text" value="'. $caldataset['title'] .'" name="nicstermin_opt_caldata['. $counter .'][title]" />';
					
					// get information on last cron run (atomatic update of calendars)
					$report_time    = $update_reports[$caldataset['sha256']]['last'];
					$report_success = $update_reports[$caldataset['sha256']]['success'];
					$report_msg     = $update_reports[$caldataset['sha256']]['status_message'];
					
					if (is_int($report_time)) {
						$timestmp->setTimestamp($report_time);
						$timestr = $timestmp->format('Y-m-d H:i:s T');
					}
					else {
						$timestr = '';
					}
					
					echo ' '. $report_msg .' @ '. $timestr;
					echo '<br />';
					$counter++;
				}
				echo '<input type="text" placeholder="'. __('URL','nicstermin')      .'" name="nicstermin_opt_caldata['. $counter .'][url]" id="'. esc_attr($args['label_for']) .'" />';
				echo '<input type="text" placeholder="'. __('Title','nicstermin')    .'" name="nicstermin_opt_caldata['. $counter .'][title]" />';
				echo '<br />';
				echo '<p class="description"><span class="dashicons dashicons-info-outline"></span> '. esc_html__('To remove a subscription delete the URL or the title and save the settings.', 'nicstermin') .'</p>';
				$counter++;
			},
			self::$pagehook, 'nicstermin_settingsection_calendars', ['label_for' => 'nicstermin_settinglabel_caldata']
		);
		
		
		
		
		/**
		 * Filter Settings
		 */
		
		 add_settings_section('nicstermin_settingsection_filters', '<h1>'. __('Calendar properties filters','nicstermin') .'</h1>', 
		 		function()
				{
					printf(
						/* translators: %1$s and %3$s are the opening of an HTML link tag, %2$s closes the tag */
						esc_html__(
							'Filters are applied to the user defined property of a calendar event. The filter "pattern" is matched to the property content and "replacement" replaces the matches.'.
							'This functionality is implemented using the PHP function %1$spreg_replace%2$s. Please refer to the %3$sPHP documentation%2$s for more information.',
							'nicstermin'),
						'<a href="https://www.php.net/manual/function.preg-replace.php" target="_blank">',
						'</a>',
						'<a href="https://www.php.net/manual/book.pcre.php" target="_blank">',
						);
				},
				self::$pagehook
			);
		register_setting(self::$pagehook, 'nicstermin_opt_filters', [
				'type'=>'array',
				'sanitize_callback' => 
				function($data)
				{
					$old_data = get_option('nicstermin_opt_filters');
					
					/** if any element is empty, delete the complete row */
					return array_filter($data, function($data)
					{
						$isempty = false;
						foreach($data as $key => $element) {
							if ($key === 'replacement'){
								continue;
							}
							$isempty |= empty($element);
						}
						return !$isempty;
					});
				}
			]);
		add_settings_field('nicstermin_settingfield_filters', __('Properties filters', 'nicstermin'),
			function($args)
			{
				$filters = get_option('nicstermin_opt_filters');
				
				$counter = 0;
				foreach ($filters as $key => $filter) {
					echo '<input type="text" value="'. $filter['ical_field']  .'" name="nicstermin_opt_filters['. $counter .'][ical_field]"   />';
					echo '<input type="text" value="'. $filter['pattern']     .'" name="nicstermin_opt_filters['. $counter .'][pattern]" />';
					echo '<input type="text" value="'. $filter['replacement'] .'" name="nicstermin_opt_filters['. $counter .'][replacement]" />';
					echo '<br />';
					$counter++;
				}
				echo '<input type="text" placeholder="'. __('Event property','nicstermin') .'" name="nicstermin_opt_filters['. $counter .'][ical_field]" id="'. esc_attr($args['label_for']) .'" />';
				echo '<input type="text" placeholder="'. __('Search pattern','nicstermin') .'" name="nicstermin_opt_filters['. $counter .'][pattern]" />';
				echo '<input type="text" placeholder="'. __('Replacement','nicstermin')    .'" name="nicstermin_opt_filters['. $counter .'][replacement]" />';
				echo '<br />';
				echo '<p class="description"><span class="dashicons dashicons-info-outline"></span> '. esc_html__('To remove a filter delete its event property or search pattern and save the settings.', 'nicstermin') .'</p>';
				
			},
			self::$pagehook, 'nicstermin_settingsection_filters', ['label_for' => 'nicstermin_settinglabel_filters']
		);
	}
	
	/**
	 * Displays a settings page
	 */
	public static function page()
	{
		// check user capabilities
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
?>
<div class="wrap">
	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>
	
	<?php settings_errors(); ?>
	
	<form action="options.php" method="post">
		<?php settings_fields(Settings::$pagehook); ?>
		<?php do_settings_sections(Settings::$pagehook); ?>
		<?php submit_button(); ?>
	</form>
	
</div>
<?php
	}
}