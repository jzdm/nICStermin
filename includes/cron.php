<?php

namespace nICStermin;

class Cron
{
	public static $default_schedule_name = 'hourly';
	
	public static function init()
	{
		add_filter('cron_schedules', [__CLASS__, 'add_cron_schedules']);
		
		// setup cron for calendar updates if not existent
		// use settings from database or use default
		$db_option_calfreq   = 'nicstermin_opt_updatefrequency';
		$cron_option_calfreq = 'nicstermin_croncallback_calendarupdate';
		if (! wp_next_scheduled ( $cron_option_calfreq )) {
			$db_schedule  = get_option($db_option_calfreq);
			$new_schedule = self::setup_interval($cron_option_calfreq, $db_schedule);
			update_option($db_option_calfreq, $new_schedule);
		}
		
		add_action($cron_option_calfreq, [__CLASS__, 'update_calendars'] );
	}
	
	public static function add_cron_schedules($schedules)
	{
		$schedules['1min'] = [
			'interval' => 60,
			'display' => __('Every minute', 'nicstermin'),
		];
		
		$schedules['15min'] = [
			'interval' => 60 * 15,
			'display' => __('Every 15 minutes', 'nicstermin'),
		];
		
		$schedules['30min'] = [
			'interval' => 60 * 30,
			'display' => __('Every 30 minutes', 'nicstermin'),
		];
		
		return $schedules;
	}
	
	/**
	 * Installs new cron event with given schedule
	 * @param  string $event_name         Name of the event.
	 * @param  string $cron_schedule_name Name of an available cron schedule. Hourly default otherwise.
	 * @return string                     Name of the cron schedule installed.
	 */
	public static function setup_interval($event_name, $cron_schedule_name)
	{
		self::remove_interval($event_name);
		
		$cron_schedules = wp_get_schedules();
		$new_schedule   = array_key_exists($cron_schedule_name, $cron_schedules) ? $cron_schedule_name : self::$default_schedule_name;
		
		wp_schedule_event( time(), $new_schedule, $event_name );
		
		return $new_schedule;
	}
	
	public static function remove_interval($event_name)
	{
		wp_clear_scheduled_hook( $event_name );
	}
	
	/**
	 * Removes the WordPress cronjob
	 * call once during plugin deactivation
	 */
	public static function deactivated()
	{
		self::remove_interval('nicstermin_croncallback_calendarupdate');
	}
	
	/**
	 * Cron callback function, run hourly
	 */
	public static function callback()
	{
		//self::update_calendars();
	}
	
	/**
	 * Update cached calendar files
	 * called by hourly cron or directly
	 */
	public static function update_calendars()
	{
		
		$caldata = get_option('nicstermin_opt_caldata');
		
		$caldata_update_report = [];
		
		foreach ($caldata as $key => $calrow) {
			$url  = $calrow['url'];
			$hash = $calrow['sha256'];
			
			$report = ['last' => time(), 'success' => false, 'status_message' => __('Update started.', 'nicstermin')];
			
			// download calendar data
			$response    = wp_remote_get($url);
			if (is_wp_error($response)) {
				$report['status_message'] = sprintf(
					/* translators: %s is a WordPress error message */
					__( 'Download of calendar failed with message: %s.', 'nicstermin' ),
					$response->get_error_message()
				);
				$caldata_update_report[$hash] = $report;
				continue;
			}
			
			// check HTTP status code
			$status_code = wp_remote_retrieve_response_code($response);
			if ($status_code < 200 || $status_code >= 300) {
				$report['status_message'] = sprintf(
					/* translators: %d is an HTTP status code */
					__( 'Download of calendar failed with HTTP status code %d.', 'nicstermin' ),
					$status_code
				);
				$caldata_update_report[$hash] = $report;
				continue;
			}
			
			// extract calendar data from HTTP response
			$calendar_contents = wp_remote_retrieve_body($response);
			if (is_wp_error($calendar_contents)) {
				$report['status_message'] = sprintf(
					/* translators: %s is a WordPress error message */
					__( 'Calendar contents could not be retrieved. Error message: %s.', 'nicstermin' ),
					$calendar_contents->get_error_message()
				);
				$caldata_update_report[$hash] = $report;
				continue;
			}
			
			// open file to write calendar data to
			$filename = NICSTERMIN_CALENDAR_CACHE .'/'. $hash .'.ics';
			if(!$fid = fopen($filename, 'w')) {
				$report['status_message'] = __( 'Failed to open calendar file to write to.', 'nicstermin' );
				$caldata_update_report[$hash] = $report;
				continue;
			}
			
			// write calendar data to opened file
			$bytes_written = fwrite($fid, $calendar_contents);
			if ($bytes_written === false) {
				$report['status_message'] = __( 'Failed to write to calendar file.', 'nicstermin' );
				$caldata_update_report[$hash] = $report;
				
				fclose($fid);
				continue;
			}
			fclose($fid);
			
			if ($bytes_written != strlen($calendar_contents) ) {
				$report['status_message'] = sprintf(
					/* translators: %1$d is the number of bytes written to a file. %2$d is the number of bytes that should have been written to a file. */
					__( 'Calendar contents incompletely written: %1$d of %2$d bytes', 'nicstermin' ),
					$bytes_written, strlen($calendar_contents)
				);
				$caldata_update_report[$hash] = $report;
				continue;
			}
			
			$report['status_message'] = __( 'Calendar updated successfully.', 'nicstermin' );
			$report['success']        = true;
			
			$caldata_update_report[$hash] = $report;
		}
		
		// write status messages back to database
		update_option('nicstermin_opt_calupdate_reports', $caldata_update_report);
	}
}