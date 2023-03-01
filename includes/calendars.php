<?php

namespace nICStermin;

class Calendars
{
	
	private static $instance = null;
	
	public static $calendars;
	public static $timezone;
	public static $filters;
	public static $styles_url;
	public static $templates_dir;
	
	private static $twig        = null;
	private static $twig_loader = null;
	
	
	public static function init()
	{
		self::$styles_url    = NICSTERMIN_CSS_URL;
		self::$templates_dir = NICSTERMIN_TEMPLATES_DIR;
		
		self::$timezone = get_option('nicstermin_opt_timezone');
		self::$filters  = get_option('nicstermin_opt_filters');
		
		// register shortcodes
		add_action('init', [__CLASS__, 'register_shortcodes']);
	}
	
	public static function register_shortcodes()
	{
		add_shortcode( 'nicstermin_Month', [__CLASS__, 'shortcode_ViewMonthly']);
		
		add_shortcode( 'nicstermin_List', [__CLASS__, 'shortcode_ViewList']);
		
		wp_enqueue_style( 'nicstermin-styles', self::$styles_url .'/styles.css', array(), "0.1" );
		//wp_enqueue_style( 'nicstermin-styles-fontawesome', self::$styles_url .'/fontawesome-all.min.css', array(), "5.15.4" );
	}
	
	private static function load_twig()
	{
		if (self::$twig === null) {
			// load twig templating engine
			self::$twig_loader = new \Twig\Loader\FilesystemLoader( self::$templates_dir );
			self::$twig        = new \Twig\Environment(self::$twig_loader);
			self::$twig->getExtension(\Twig\Extension\CoreExtension::class)->setTimezone(self::$timezone);
			
			/* Twig loading for debug purposes
			self::$twig_loader = new \Twig\Loader\FilesystemLoader( self::$templates_dir );
			self::$twig        = new \Twig\Environment(self::$twig_loader, ['debug' => true]);    // remove debug for production
			self::$twig->addExtension(new \Twig\Extension\DebugExtension());                            // remove for production
			self::$twig->getExtension(\Twig\Extension\CoreExtension::class)->setTimezone(self::$timezone);
			*/
		}
	}
	
	public static function get_twig()
	{
		if (self::$twig === null) {
			self::load_twig();
		}
		return self::$twig;
	}
	
	/**
	 * Loads all calendar from URLs
	 */
	public static function load_calendars()
	{
		$setting_caldata = get_option('nicstermin_opt_caldata');
		
		$calendars = [];
	
		foreach( $setting_caldata as $calinfo) {
			
			$hash     = $calinfo['sha256'];
			$filename = NICSTERMIN_CALENDAR_CACHE .'/'. $hash .'.ics';
			if (!is_file($filename)) {
				continue;
			}
			
			try {
				$cal = new \ICal\ICal($filename, array(
					'defaultSpan'                 => 2,     // Default value
					'defaultTimeZone'             => self::$timezone,
					'defaultWeekStart'            => 'MO',  // Default value
					'disableCharacterReplacement' => false, // Default value
					'filterDaysAfter'             => null,  // Default value
					'filterDaysBefore'            => null,  // Default value
					'httpUserAgent'               => null,  // Default value
					'skipRecurrence'              => false, // Default value
				));

				$calendars[$calinfo['title']] = $cal;
			} catch (\Exception $e) {
				continue;
			}
		}
		self::$calendars = $calendars;
	}
	
	// maps ical event \ICal\Event to custom data format
	public static function map_event($event, $calendar_title)
	{
		$allday = false;
		
		// starting time from UNIX timestamp (always UTC)
		$dtstart_utc = \DateTime::createFromFormat('U', sprintf('%d', $event->dtstart_array[2]) , new \DateTimeZone('UTC'));
		
		// filter for all-day events
		if (isset($event->x_microsoft_cdo_alldayevent)) {
			
			$allday = strcasecmp($event->x_microsoft_cdo_alldayevent,'TRUE') === 0 ? true : false;
			
			if ($allday) {
				$dtend_utc = clone $dtstart_utc;
				$dtend_utc->modify('+1 day');
			}
		}
		else {
			// filter for all-day event by NextCloud Calendar
			// dtstart and dtend is only YYYYMMDD, no time information and
			// $event->dtstart_array[0][VALUE] == DATE
			if (isset($event->dtstart_array[0]['VALUE']) && strtolower($event->dtstart_array[0]['VALUE']) === strtolower('DATE')) {
				$allday = true;
			}
		}
		
		// create timestamp of end from UNIX timestamp (always UTC), if not existent
		if (!isset($dtend_utc) && isset($event->dtend_array[2])) {
			$dtend_utc   = \DateTime::createFromFormat('U', sprintf('%d', $event->dtend_array[2]) ,   new \DateTimeZone('UTC'));
		}
		
		
		
		$evt = [
			"calendar_title" => $calendar_title,   // title of calendar
			"summary"        => $event->summary,   // description ("summary") of event
			"dtstart"        => $dtstart_utc,      // event starting time in UTC
			"dtend"          => $dtend_utc,        // event ending time in UTC
			"description"    => isset($event->description) ? $event->description : '',
			"location"       => isset($event->location)    ? $event->location    : '',
			"status"         => isset($event->status)      ? $event->status      : '',
			"url"            => isset($event->url)         ? $event->url         : '',
			"allday"         => $allday,
			"raw"            => $event
		];
		
		$event_filtered = self::apply_filters($evt);
		
		return $event_filtered;
	}
	
	/**
	 * Applies user defined filters on ical event properties
	 * @param  mixed[] $event Event object/array to filter
	 * @return mixed[]        Event object/array with apllied filtering.
	 */
	public static function apply_filters($event)
	{
		$filters = self::$filters;
		
		/*
		$filters = [
				['ical_field' => 'location', 'pattern' => '/((?:(Freie evangelische )?Bibelgemeinde Meine( e\.V\.)?.*)|(?:Ortholzweg 2,?(38527\s)?Meine))/i', 'replacement' => '']
			];
		*/
		
		foreach ($filters as $key => $filter) {
			$property    = $filter['ical_field'];
			$pattern     = $filter['pattern'];
			$replacement = $filter['replacement'];
			
			if (array_key_exists($property, $event) && is_string($event[$property]) && strlen($event[$property]) > 0) {
				$event[$property] = preg_replace($pattern, $replacement, $event[$property]);
				
				
			}
		}
		
		return $event;
	}
	
	
	public static function events_per_day($tstart = NULL)
	{

		if ($tstart === NULL) {
			// if no start date is given, set current month
			$tstart = new \DateTime('first day of this month', new \DateTimeZone(self::$timezone));
		}
		else {
			$tstart->modify('first day of this month');
		}
		//$tstart->setTimeZone(new \DateTimeZone(Calendars::$timezone));
		$tstart->setTime(0,0,0,0);

		// get nuber of days in this month
		$daysInMonth = intval($tstart->format("t"));

		// prepare array of events for each day in this month
		$eventsOfDay = array_fill(0, $daysInMonth, []);

		foreach (self::$calendars as $calendar_title => $cal) {
			for ($i = 0; $i < $daysInMonth; $i++) {
				$tcur = clone $tstart;
				$tcur->add(new \DateInterval("P". $i ."D"));

				$events = $cal->eventsFromRange($tcur->format("Y-m-d"), $tcur->format("Y-m-d"));
				if (count($events)) {
					foreach ($events as $evt) {
						$eventsOfDay[$i][] = self::map_event($evt, $calendar_title);
					}
					//$eventsOfDay[$i]['date_day'] = $tcur;
					// TODO: restructure $eventsOfDay so that for each day with events there is also a \DateTime timestamp of that day available
					//       to achieve this, the events array has to go one layer deeper and everything needs to adapt.
				}
				
				// sort events each day by their starting time in ascending order
				usort($eventsOfDay[$i], function ($a, $b) {
					return $a['dtstart'] <=> $b['dtstart'];
				});
			}
		}

		return [$eventsOfDay, $tstart, $daysInMonth];
	}
	
	public static function name_of_month($month_number)
	{
		$month_names = [ '',
			__('January',   'nicstermin'),
			__('February',  'nicstermin'),
			__('March',     'nicstermin'),
			__('April',     'nicstermin'),
			__('May',       'nicstermin'),
			__('June',      'nicstermin'),
			__('July',      'nicstermin'),
			__('August',    'nicstermin'),
			__('September', 'nicstermin'),
			__('October',   'nicstermin'),
			__('November',  'nicstermin'),
			__('December',  'nicstermin'),
		];
		
		return $month_names[intval($month_number)];
	}
	
	public static function name_of_day($day_number)
	{
		$day_names = [ '',
			__('Monday',    'nicstermin'),
			__('Tuesday',   'nicstermin'),
			__('Wednesday', 'nicstermin'),
			__('Thursday',  'nicstermin'),
			__('Friday',    'nicstermin'),
			__('Saturday',  'nicstermin'),
			__('Sunday',    'nicstermin'),
		];
		
		return $day_names[intval($day_number)];
	}
	

	
	public static function shortcode_ViewMonthly($atts, $content = '')
	{
		$twigTemplate = 'calview.html.twig';
		
		$tstart = NULL;
		if (!empty($_GET['date'])) {
			try {
				$tstart = new \DateTime($_GET['date']);
			}
			finally {}
		}
		
		
		// load calendars from web or file
		self::load_calendars();
		[$eventsOfDay, $tstart, $daysInMonth] = self::events_per_day($tstart);
		
		$month         = intval($tstart->format("m"));
		$year          = intval($tstart->format("Y"));
		$days_in_month = intval($tstart->format("t"));
		$date_firstOfMonth = new \DateTime("$year-$month-01");
		$dow  = intval($date_firstOfMonth->format("N"));
		$rows = ceil( ($days_in_month + $dow - 1)/7 );
		
		$name_of_month = self::name_of_month($month);
		
		// get day number of 'today' for calendar display.
		// if another month is shown, return zero
		$t_current = new \DateTime('now', new \DateTimeZone(self::$timezone));
		$today_day = ($t_current->format('Y-m') === $tstart->format('Y-m')) ? intval($t_current->format('d')) : 0;
		
		// loalized (shortened) names of week days
		$day_names_localized       = array_map([__CLASS__, 'name_of_day'], range(1,7));
		$day_names_localized_short = array_map(function($item){return substr($item,0,3);}, $day_names_localized);
		
		// map events per day to visual calendar structure, i.e. one week per row
		$offset = 0;
		$evt = array_fill(0,$rows,[]);
		$evt[0] = array_slice($eventsOfDay, $offset , 7 - $dow + 1);
		$offset = 7 - $dow + 1;
		for ($row = 1; $row < $rows; $row++) {
			$evt[$row] = array_slice($eventsOfDay, $offset, 7);
			$offset += 7;
		}
		
		
		// make variables available to the twig template
		$twigParams = [
			'month'         => $month,                     // month as number
			'year'          => $year,                      // year as number
			'today'         => $today_day,                 // day number of 'today' or zero if another month is shown
			'monthName'     => $name_of_month,             // month name as string
			'dayNames'      => $day_names_localized,       // array of localized day names monday to sunday
			'dayNamesShort' => $day_names_localized_short, // array of localized shortened (first 3 letters) day names monday to sunday
			'str_prevMonth' => __('Previous month', 'nicstermin'),
			'str_nextMonth' => __('Next month',     'nicstermin'),
			'str_curMonth'  => __('Current month',  'nicstermin'),
			'weekStart'     => $dow,                       // weekday number of first of month
			'calRows'       => $rows,                      // calendar rows to display
			'daysInMonth'   => $days_in_month,             // number of days this month
			'timezone'      => self::$timezone,            // timezone of calendar
			'events'        => $evt,                       // array of events
		];
		
		//return $twig->render($twigTemplate, $twigParams); // .'<pre>'. print_r($twigParams,true) .'</pre>';
		return self::get_twig()->render($twigTemplate, $twigParams);
	}
	
	/**
	 * Shortcode: [nicstermin_List]
	 * By default shows tabular list of events of this month and the next.
	 * 
	 * Options:
	 *   [nicstermin_List range="3"]
	 *   Shows three months in total, current month and the next two months.
	 * 
	 *   [nicstermin_List range="-2, 0, 3, 4"]
	 *   Shows four months in total, the month before the last month (-2), current month (0), third and fourth month from current one.
	 * 
	 * Data Format:
	 * $events = [
	 *    [0] => [
	 *        "year"       => 2021,   // number of year
	 *        "month"      => 03,     // number of month
	 *        "month_name" => "März", // locaclized name of month
	 *        "events"     => [       // sorted list of events per day of month
	 *            [0]  => [],              // first day of month, no events
	 *            [1]  => [ event array[] ], // array of events -> look at map_event()
	 *             …
	 *            [30] => [ event array[] ], // last day of month with events that day
	 *          ],
	 *      ],
	 *    [1] => […]   // same structure as above, another month
	 * ];
	 */
	public static function shortcode_ViewList($atts, $content = '')
	{
		$twigTemplate = 'calview_list.html.twig';
		
		// DateTime of first of current month
		$time_this_month = new \DateTime('first day of this month', new \DateTimeZone(self::$timezone));
		$time_this_month->setTime(0,0,0,0);
		
		$relative_months = [0, 1];
		if (!empty($atts['range'])) {
			$monthlist = array_map('intval', explode(',', $atts['range']));
			if (count($monthlist) == 1) {
				$relative_months = range(0, $monthlist[0]-1);
			}
			else {
				$relative_months = $monthlist;
			}
		}
		
		$tstart = [];
		foreach ($relative_months as $relmonth) {
			$tnew     = clone $time_this_month;
			$tnew->modify(sprintf('%+d month', $relmonth));
			$tstart[] = $tnew;
		}
		
		// load calendars from web or file
		self::load_calendars();
		
		$events = [];
		foreach($tstart as $t) {
			[$eventsOfDay, ,$daysInMonth] = self::events_per_day($t);
			$events[] = [
					"year"       => $t->format('Y'),
					"month"      => $t->format('m'),
					"month_name" => self::name_of_month($t->format('m')),
					"events"     => $eventsOfDay
				];
		}
		
		
		// loalized (shortened) names of week days
		$day_names_localized       = array_map( [__CLASS__, 'name_of_day'], range(1,7));
		$day_names_localized_short = array_map(function($item){return substr($item,0,2);}, $day_names_localized);
		
		
		// make variables available to the twig template
		$twigParams = [
			'dayNames'      => $day_names_localized,       // array of localized day names monday to sunday
			'dayNamesShort' => $day_names_localized_short, // array of localized shortened (first 3 letters) day names monday to sunday
			'str_prevMonth' => __('Previous month', 'nicstermin'),
			'str_nextMonth' => __('Next month',     'nicstermin'),
			'str_curMonth'  => __('Current month',  'nicstermin'),
			'str_AllDayEvent' => __('all day', 'nicstermin'), // descriptions for all-day events
			'timezone'      => self::$timezone,       // timezone of calendar
			'events'        => $events,               // array of events
		];
		
		return self::get_twig()->render($twigTemplate, $twigParams); //.'<pre>'. print_r($twigParams,true) .'</pre>';
	}
}
