<?php defined('BLUDIT') or die('Unauthorized access!');
/** Visitor Statistics
 * -----------------------------------------------------------------------
 *
 *  Plugin Visitor Statistics for Bludit CMS
 *
 * @author Elix
 * @url https://github.com/Elixcz/visitor-statistics
 * @email elix.code@gmail.com
 * @version 1.1.1
 * -----------------------------------------------------------------------
 */



class visitorStatistics extends Plugin {

	// Version of this plugin
	private $build = '10101';

	// The number of days displayed in the chart
	private $numOfDays = 31;

	// The color of the visits graph
	private $visitsChartBgrColor = '#2EB7C8';

	// The color of the unique visits graph
	private $uniqueVisitsChartBgrColor = '#064AA0';

	// The path to the data folder
	private $dataPath = '/data/';

	// The path to the library folder
	private $libPath = '/library/';

	// A temporary variable to store the contents of the log file so that this log
	// does not have to be opened every time.
	private $linesFromLog = array();

	// A temporary variable to store the contents of the BotsList file
	// does not have to be opened every time.
	private $botsList = array();

	// A temporary variable containing a list of all log names for the last month.
	// used in the getVisitsPerMonth() and getUniqueVisitsPerMonth() methods.
	private $tmp_logs;

	// Github repository url
	private $githubUrl = 'https://github.com/elixcz/visitor-statistics';

	// Plugin site url
	private $pluginUrl = 'https://elix.mzf.cz/plugin-visitor-statistics-pro-bludit-cms';



	/** Init plugin
	 */
	public function init()
	{
		$this->dbFields = array(
			'showChartOnDashboard' => true,
			'excludeMonitoringServices' => true,
			'excludeBots' => false,
			'numEntriesInLog' => 25,
			'excludeUsers' => true
		);
	}



	/** Widget in dashboard
	 *
	 * @return void
	 */
	public function dashboard()
	{
		// if is the chart on dashboard enabled in settings
		if( $this->getValue('showChartOnDashboard') ):

		global $L;
		global $site;
		$currentDate    = Date::current( 'Y-m-d' );
		$visitsToday    = $this->getVisitsByDate( $currentDate );
		$uniqueVisitors = $this->getUniqueVisitsByDate( $currentDate );

		$html  = PHP_EOL . '<!-- Plugin Visitor Statistics -->' . PHP_EOL;
		$html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" integrity="sha256-+8RZJua0aEWg+QVVKg4LEzEEm/8RFez5Tb4JBNiV5xA=" crossorigin="anonymous"></script>' . PHP_EOL;
		$html .= '<div class="visitor-statistics-plugin"><div class="my-4 pt-4 border-top">' . PHP_EOL;
		$html .= '<div class="chart-container" style="position: relative; height:50% !important; width:100%; font-size: 90%;"><canvas id="visitorStatisticsChart" aria-label="Visitor Statistics" role="img">' . $L->get('Your browser does not support the canvas element.') . '</canvas></div>' . PHP_EOL;
		$html .= '</div></div>' . PHP_EOL;

		if ( pluginActivated('pluginSimpleStats') )
		{
			$this->moveSimpleStatsLogs();
			deactivatePlugin('pluginSimpleStats');
			if ( pluginActivated('pluginSimpleStats') )
			{
				$html .= $this->getModal();
				$html .= '<script>$(\'#visitorStatisticsError\').modal("show");</script>';
			}
		}

		$numOfDays = $this->numOfDays - 1;
		$date_format = trim( str_replace(',', '', str_ireplace('Y', '', $site->dateFormat() ) ) );
		for ( $i = $numOfDays; $i >= 0 ; $i-- )
		{
			$dateWithOffset = Date::currentOffset( 'Y-m-d', '-'.$i.' day');
			$visits[$i]     = $this->getVisitsByDate( $dateWithOffset );
			$unique[$i]     = $this->getUniqueVisitsByDate( $dateWithOffset );
			$days[$i]       = Date::format( $dateWithOffset, 'Y-m-d', $date_format );
			unset( $dateWithOffset );
		}

		$labels = "'" . implode("','", $days) . "'";
		$seriesVisits = implode(',', $visits);
		$seriesUnique = implode(',', $unique);
		unset( $days, $visits, $unique );
		$tv_label = $L->get('Visits per day');
		$tuv_label = $L->get('Unique visits per day');
		$visitsChartBgrColor = $this->visitsChartBgrColor;
		$uniqueVisitsChartBgrColor = $this->uniqueVisitsChartBgrColor;
		$days_text = $L->get('Days');
		$visits_text = $L->get('Visits');
		$script = <<<EOF
<script>
	const labels = [$labels];
	const data = {
		labels: labels,
		datasets: [{
			label: '$tv_label',
			backgroundColor: '$visitsChartBgrColor',
			borderColor: '$visitsChartBgrColor',
			data: [$seriesVisits],
			tension: 0.3,
			pointStyle: 'rect',
			pointRadius: 4,
			pointHoverRadius: 8
		},
		{
			label: '$tuv_label',
			backgroundColor: '$uniqueVisitsChartBgrColor',
			borderColor: '$uniqueVisitsChartBgrColor',
			data: [$seriesUnique],
			tension: 0.3,
			pointStyle: 'circle',
			pointRadius: 4,
			pointHoverRadius: 8
		}
		]
	};
	const config = {
		type: 'line',
		data: data,
		options: {
			responsive: true,
			interaction: {
				intersect: false,
			},
			scales: {
				x: {
					display: true,
					title: {
						display: false,
						text: '$days_text'
					}
				},
				y: {
					display: true,
					title: {
						display: false,
						text: '$visits_text'
					},
					beginAtZero: true,
					onlyInteger: true,
					suggestedMin: 0,
					ticks: {
						stepSize: 1
					}
				}
			}
		}
	};
	const myChart = new Chart( document.getElementById('visitorStatisticsChart'), config );
</script>
<!-- /Plugin Visitor Statistics -->
EOF;
		$this->deleteOldLogs();
		return $html.PHP_EOL.$script.PHP_EOL;

		endif;
	}



	/** Add new visitor from frontend in to log
	 *
	 * @return void
	 */
	public function siteBodyEnd()
	{
		$this->addNewVisit();
	}



	/** Add new visitor from login page in to log
	 *
	 * @return void
	 */
	public function loginBodyEnd()
	{
		$this->addNewVisit();
	}



	/** Plugin settings page
	 *
	 * @return void;
	 */
	public function form()
	{
		global $L;
		$html = '';
		$html .= PHP_EOL . '<!-- Plugin Visitor Statistics -->' . PHP_EOL;
		$html .= '<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&display=swap" rel="stylesheet">' . PHP_EOL;
		$html .= '<style>.visitor-statistics-font, .card{font-family:Nunito,sans;font-size:1rem;}</style>' . PHP_EOL;

		$html .= '<hr>';

		if ( pluginActivated('pluginSimpleStats') )
		{
			$this->moveSimpleStatsLogs();
			deactivatePlugin('pluginSimpleStats');
			if ( pluginActivated('pluginSimpleStats') )
			{
				$html .= '<div class="alert alert-danger alert-dismissible fade show visitor-statistics-font mt-2 mb-2" role="alert">';
				$html .= $L->get('Plugin Simple stats is active. Deactivate it!');
				$html .= '<button type="button" class="close" data-dismiss="alert" aria-label="' . $L->get('Close') . '"><span aria-hidden="true">&times;</span></button>';
				$html .= '</div>';
			}
		}

		$html .= '<div class="card shadow mt-4">';
		$html .= '<div class="card-header" style="background-color:#E9E9E9;text-shadow: 1px 1px 0 #fff;">';
		$html .= '<strong>'.$L->get('Show the chart on dashboard').'</strong>';
		$html .= '</div>';
		$html .= '<div class="card-body">';
		$html .= '<select name="showChartOnDashboard">';
		$html .= '<option value="true" ' . ( $this->getValue('showChartOnDashboard') === true ? 'selected' : '') . '>' . $L->get('Enabled').'</option>';
		$html .= '<option value="false" ' . ( $this->getValue('showChartOnDashboard') === false ? 'selected' : '') . '>' . $L->get('Disabled').'</option>';
		$html .= '</select>';
		$html .= '<p class="card-text pt-2 text-muted small">'.$L->get('Enable this option to display the visits graph on the dashboard.').'</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="card shadow mt-4">';
		$html .= '<div class="card-header" style="background-color:#E9E9E9;text-shadow: 1px 1px 0 #fff;">';
		$html .= '<strong>'.$L->get('Exclude monitoring services from visits chart').'</strong>';
		$html .= '</div>';
		$html .= '<div class="card-body">';
		$html .= '<select name="excludeMonitoringServices">';
		$html .= '<option value="true" ' . ( $this->getValue('excludeMonitoringServices') === true ? 'selected' : '') . '>' . $L->get('Enabled').'</option>';
		$html .= '<option value="false" ' . ( $this->getValue('excludeMonitoringServices') === false ? 'selected' : '') . '>' . $L->get('Disabled').'</option>';
		$html .= '</select>';
		$html .= '<p class="card-text pt-2 text-muted small">'.$L->get('enable-this-option-to-disable-access-logging-for-monitoring-services').'</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="card shadow mt-4">';
		$html .= '<div class="card-header" style="background-color:#E9E9E9;text-shadow: 1px 1px 0 #fff;">';
		$html .= '<strong>'.$L->get('Exclude bots from visits chart').'</strong>';
		$html .= '</div>';
		$html .= '<div class="card-body">';
		$html .= '<select name="excludeBots">';
		$html .= '<option value="true" ' . ( $this->getValue('excludeBots') === true ? 'selected' : '') . '>' . $L->get('Enabled').'</option>';
		$html .= '<option value="false" ' . ( $this->getValue('excludeBots') === false ? 'selected' : '') . '>' . $L->get('Disabled').'</option>';
		$html .= '</select>';
		$html .= '<p class="card-text pt-2 text-muted small">'.$L->get('exclude-bots-from-visits-chart-description').'</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="card shadow mt-4">';
		$html .= '<div class="card-header" style="background-color:#E9E9E9;text-shadow: 1px 1px 0 #fff;">';
		$html .= '<strong>'.$L->get('Exclude logged in users from visits chart').'</strong>';
		$html .= '</div>';
		$html .= '<div class="card-body">';
		$html .= '<select name="excludeUsers">';
		$html .= '<option value="true" ' . ( $this->getValue('excludeUsers') === true ? 'selected' : '') . '>' . $L->get('Enabled').'</option>';
		$html .= '<option value="false" ' . ( $this->getValue('excludeUsers') === false ? 'selected' : '') . '>' . $L->get('Disabled').'</option>';
		$html .= '</select>';
		$html .= '<p class="card-text pt-2 text-muted small">'.$L->get('Logged in users in the administration will not be recorded as visits.').'</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<div class="card shadow mt-4">';
		$html .= '<div class="card-header" style="background-color:#E9E9E9;text-shadow: 1px 1px 0 #fff;">';
		$html .= '<strong>'.$L->get('the-number-of-displayed-entries').'</strong>';
		$html .= '</div>';
		$html .= '<div class="card-body">';
		$html .= '<select name="numEntriesInLog">';
		$html .= '<option value="10" ' . ( $this->getValue('numEntriesInLog') == 10 ? 'selected' : '') . '>10 ' . $L->get('rows').'</option>';
		$html .= '<option value="25" ' . ( $this->getValue('numEntriesInLog') == 25 ? 'selected' : '') . '>25 ' . $L->get('rows (recomended)').'</option>';
		$html .= '<option value="50" ' . ( $this->getValue('numEntriesInLog') == 50 ? 'selected' : '') . '>50 ' . $L->get('rows').'</option>';
		$html .= '<option value="75" ' . ( $this->getValue('numEntriesInLog') == 75 ? 'selected' : '') . '>75 ' . $L->get('rows').'</option>';
		$html .= '<option value="100" ' . ( $this->getValue('numEntriesInLog') == 100 ? 'selected' : '') . '>100 ' . $L->get('rows').'</option>';
		$html .= '</select>';
		$html .= '<p class="card-text pt-2 text-muted small">'.$L->get('the-number-of-displayed-entries-description').'</p>';
		$html .= '</div>';
		$html .= '</div>';

		$html .= '<p class="clearfix"></p>' . PHP_EOL;
		$html .= $this->getPluginFooter();
		$html .= '<p class="clearfix"></p>' . PHP_EOL;

		$html .= PHP_EOL . '<!-- /Plugin Visitor Statistics -->' . PHP_EOL;
		return $html;
	}



	/** Deleting logs older 31 days
	 *
	 * @return void
	 */
	public function deleteOldLogs()
	{
		$logs = Filesystem::listFiles( $this->workspace(), '*', 'log', true );
		if( count($logs) > $this->numOfDays )
		{
			$remove = array_slice( $logs, $this->numOfDays );
			foreach ($remove as $log) {
				Filesystem::rmfile($log);
			}
		}
		@clearstatcache();
	}



	/** Moving Simple stats logs to this workspace after deactivating plugin
	 *
	 * @return void
	 */
	private function moveSimpleStatsLogs()
	{
		// Move logs from Simple stats workspace in to Visitor Statistics workspace
		$ss_logs = Filesystem::listFiles( PATH_WORKSPACES . 'simple-stats', '*', 'log', false );
		$old_log = '';
		if( count($ss_logs) > 0 )
		{
			foreach( $ss_logs as $old_log)
			{
				// Fix for prevent overwriting file with same name
				if( file_exists( $this->workspace() . $old_log ) ) continue;

				@copy( PATH_WORKSPACES . 'simple-stats' . DS . $old_log, $this->workspace() . $old_log );
			}
		}
	}



	/** Returns the amount of visits by date
	 *
	 * @param string Date
	 * @return int Visitors
	 */
	public function getVisitsByDate( $date )
	{
		$logFile = $this->workspace() . $date . '.log';
		if( !isset( $this->linesFromLog[$date] ) || empty( $this->linesFromLog[$date] ) )
		{
			$this->linesFromLog[$date] = @file( $logFile );
			unset( $logFile );
		}

		if ( empty( $this->linesFromLog[$date] ) )  return 0;
		if ( $this->linesFromLog[$date] === false ) return 0;

		return count( $this->linesFromLog[$date] );
	}



	/** Return number of all visitors per month
	 *
	 * @return int Visitors
	 */
	public function getVisitsPerMonth()
	{
		$this->deleteOldLogs();
		$logs = Filesystem::listFiles( $this->workspace(), '*', 'log', true );
		$this->tmp_logs = $logs;
		$numLines = 0;
		foreach ($logs as $log) {
			$linesOflog = @file( $log );
			if( empty( $linesOflog ) || $linesOflog === false ) $linesOflog = array();
			$numLines = $numLines + count( $linesOflog );
		}
		return $numLines;
	}



	/** Return number of unique visitors per month
	 *
	 * @return int Visitors
	 */
	public function getUniqueVisitsPerMonth()
	{
		$tmp = array();
		foreach ($this->tmp_logs as $log) {
			$linesOflog = @file( $log );
			if( empty( $linesOflog ) || $linesOflog === false ) return 0;
			foreach ( $linesOflog as $line)
			{
				$data = json_decode( $line );
				$ip = $data[0];
				$tmp[$ip] = true;
			}
		}
		return count( $tmp );
	}



	/** Returns the num of unique visitors by date
	 *
	 * @param string Date
	 * @return int Number of visitors
	 */
	public function getUniqueVisitsByDate( $date )
	{
		$logFile = $this->workspace() . $date . '.log';
		if( !isset( $this->linesFromLog[$date] ) || empty( $this->linesFromLog[$date] ) )
		{
			$this->linesFromLog[$date] = @file( $logFile );
			unset( $logFile );
		}

		if ( empty( $this->linesFromLog[$date] ) )  return 0;
		if ( $this->linesFromLog[$date] === false ) return 0;

		$tmp = array();
		foreach ( $this->linesFromLog[$date] as $line) {
			$data = json_decode( $line );
			$ip = $data[0];
			$tmp[$ip] = true;
		}
		return count( $tmp );
	}



	/** Add new visitor to the log
	 *
	 * @return void|false
	 */
	private function addNewVisit()
	{
		global $L;

		if( $this->getValue('excludeUsers') )
		{
			if ( Cookie::get('BLUDIT-KEY') )
			{
				return false;
			}
		}

		if( !empty( $_SERVER['HTTP_USER_AGENT'] ) )
		{
			$ua = $_SERVER['HTTP_USER_AGENT'];
		}else{
			$ua = $L->get('Unknown');
		}

		// if exclude bots from loging
		if( $this->getValue('excludeBots') ):
			if( $this->isBot( $ua ) ) return false;
		endif;

        $currentTime = Date::current('Y-m-d H:i:s');
		$ip = TCP::getIP();
		if( stripos($ip, ',') !== false )
		{
			$tmp = explode(',', $ip);
			$ip = $tmp[0];
			unset($tmp);
		}
		$ghba = gethostbyaddr( $ip );
		$request = $_SERVER['REQUEST_URI'];
		$ref = $_SERVER['HTTP_REFERER'];

		if( stripos( $request, '/apple-touch-icon') !== false ) return false;
		if( stripos( $request, '/favicon') !== false ) return false;

		// If exclude monitoring services from loging
		if( $this->getValue('excludeMonitoringServices') ):
			if( $this->checkInvisibleIP( $ip ) === false ) return false;
			if( $this->checkInvisibleUA( $ua ) === false ) return false;
		endif;

		$line = json_encode( array( $ip, $currentTime, $request, $ref, $ua, $ghba ) );
		$currentDate = Date::current('Y-m-d');
		$logFile = $this->workspace() . $currentDate . '.log';

		$write = @file_put_contents( $logFile, $line . PHP_EOL, FILE_APPEND | LOCK_EX );
		if( $write === FALSE ) return false;
	}



	/** Checks if the IP adress is on the list of items that will not be displayed in the listing.
	 *  (eg. Pingdom etc.)
	 *
	 * @param string IP
	 * @return bool
	 */
	private function checkInvisibleIP( $ip )
	{
		if( file_exists( __DIR__ . $this->dataPath . 'invisibleIP.php' ) )
		{
			$lines = @file( __DIR__ . $this->dataPath . 'invisibleIP.php' );

			if ( ! empty( $lines ) )
			{
				unset($lines[0]);
				foreach ($lines as $line) {
					if( empty( $line ) ) continue;
					if( trim($line) == $ip ) return false;
				}
			}
		}
		return true;
	}



	/** Checks if the useragent is on the list of items that will not be displayed in the listing.
	 *  (eg. Pingdom etc.)
	 *
	 * @param string Useragent
	 * @return bool
	 */
	private function checkInvisibleUA( $ua )
	{
		if( file_exists( __DIR__ . $this->dataPath . 'invisibleUA.php' ) )
		{
			$lines = @file( __DIR__ . $this->dataPath . 'invisibleUA.php' );

			if ( ! empty( $lines ) )
			{
				unset($lines[0]);
				foreach ($lines as $line) {
					if( empty( $line ) ) continue;
					if( trim($line) == $ua ) return false;
				}
			}
		}
		return true;
	}



	/** Check if the visit is bot
	 *
	 * @param string Useragent
	 * @return bool
	 */
	private function isBot( $useragent )
	{
		if( empty( $this->botsList ) ):
			if( file_exists( __DIR__ . $this->dataPath . 'botsList.php' ) ):
				$this->botsList = @file( __DIR__ . $this->dataPath . 'botsList.php' );
				$botPart = '';

				if ( ! empty( $this->botsList ) )
				{
					unset( $this->botsList[0] );
					foreach ( $this->botsList as $botPart) {
						if( empty( $botPart ) ) continue;
						if( stripos( $useragent, trim($botPart) ) !== false ) return true;
						unset($botPart);
					}
				}
			endif;
		else:
			$botPart = '';
			foreach ( $this->botsList as $botPart) {
				if( empty( $botPart ) ) continue;
				if( stripos( $useragent, trim($botPart) ) !== false ) return true;
				unset($botPart);
			}
		endif;

		return false;
	}



	/** Return Bot name in badge
	 *
	 * @param string Useragent
	 * @return string HTML badge
	 */
	private function getBot( $useragent )
	{
		global $L;
		if( stripos( $useragent, 'GoogleBot') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google bot</span>';
		elseif( stripos( $useragent, 'Chrome-Lighthouse') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Lighthouse</span>';
		elseif( stripos( $useragent, 'Google-Youtube-Links') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Youtube bot</span>';
		elseif( stripos( $useragent, 'GoogleToolbar') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google Toolbar</span>';
		elseif( stripos( $useragent, 'Google-Shopping-Quality') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google Shopping</span>';
		elseif( stripos( $useragent, 'Google-Adwords-DisplayAds-WebRender') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google Adwords bot</span>';
		elseif( stripos( $useragent, 'AdsBot-Google-Mobile') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google Ads bot</span>';
		elseif( stripos( $useragent, 'Google Page Speed Insights') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Google Page speed</span>';
		elseif( stripos( $useragent, 'MSNbot') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">MSN bot</span>';
		elseif( stripos( $useragent, 'wordpress') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">WordPress</span>';
		elseif( stripos( $useragent, 'whatsapp') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">WhatsApp bot</span>';
		elseif( stripos( $useragent, 'GTmetrix') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">GTmetrix bot</span>';
		elseif( stripos( $useragent, 'Wayback Machine Live Record') !== false || stripos( $useragent, 'archive.org') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Archive.org</span>';
		elseif( stripos( $useragent, 'MailChimp.com') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">MailChimp bot</span>';
		elseif( stripos( $useragent, 'FeedlyApp') !== false || stripos( $useragent, 'Feedly/') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Feedly bot</span>';
		elseif( stripos( $useragent, 'Facebook') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Facebook bot</span>';
		elseif( stripos( $useragent, 'Snapchat') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Snapchat bot</span>';
		elseif( stripos( $useragent, 'pingdom') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Pingdom bot</span>';
		elseif( stripos( $useragent, 'Quora') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Quora Link Preview</span>';
		elseif( stripos( $useragent, 'BrokenLinkCheck') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Broken Link Check</span>';
		elseif( stripos( $useragent, 'BingBot') !== false || stripos( $useragent, 'BingPreview') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Bing bot</span>';
		elseif( stripos( $useragent, 'Yandex') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Yandex bot</span>';
		elseif( stripos( $useragent, 'OpenWebSpider') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">OpenWebSpider</span>';
		elseif( stripos( $useragent, 'bitlybot') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Bitly bot</span>';
		elseif( stripos( $useragent, 'baidu') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Baidu bot</span>';
		elseif( stripos( $useragent, 'SeznamEmailProxy') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Seznam bot</span>';
		elseif( stripos( $useragent, 'Seznam screenshot-generator') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Seznam bot</span>';
		elseif( stripos( $useragent, 'SeznamBot') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Seznam bot</span>';
		elseif( stripos( $useragent, 'UptimeRobot') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Uptime robot</span>';
		elseif( stripos( $useragent, 'AddThis.com') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">AddThis bot</span>';
		elseif( stripos( $useragent, 'Microsoft-CryptoAPI') !== false || stripos( $useragent, 'Microsoft-WebDAV') !== false || stripos( $useragent, 'DavClnt') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Microsoft</span>';
		elseif( stripos( $useragent, 'slurp') !== false || stripos( $useragent, 'Yahoo') ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">Yahoo! bot</span>';
		elseif( stripos( $useragent, 'W3C-checklink') !== false || stripos( $useragent, 'W3C_Validator') !== false || stripos( $useragent, 'W3C-mobileOK') !== false || stripos( $useragent, 'jigsaw') !== false ):
			return '<span class="badge badge-warning rounded" title="' . $useragent . '">W3C Validator bot</span>';
		else:
			return '<span class="badge badge-secondary rounded"  title="' . $useragent . '">' . $L->get('Unknown bot') . '</span>';
		endif;
	}



	/** Today's visit log
	 *  (on the statistics page in the administration)
	 *
	 * @return string|false HTML with rows of visits
	 */
	private function getTodayLog()
	{
		global $L;
		global $site;
		$format = $site->dateFormat();

		if( empty( $this->linesFromLog[ Date::current('Y-m-d') ] ) )
		{
			return '<div class="alert alert-info">' . $L->get('There are no visitors for today.') . '</div>';
		}

		$html = '<h3 class="visitor-statistics-title pt-5 border-bottom">' . $L->get('Last') . ' ' . $this->getValue('numEntriesInLog') . ' ' . $L->get('today visits') . '</h3>';
		$html .= '<table class="table table-striped table-bordered mb-1 shadow" style="font-size:90%;">';
		$html .= '<thead><tr style="background:#E8E8E8;"><th scope="col">#</th><th scope="col">Time</th><th scope="col">IP</th><th scope="col">Request</th><th scope="col" class="referer">Referer</th><th scope="col">Info</th></tr></thead>';
		$html .= '<tbody>';

		if( file_exists( dirname( __FILE__ ) . $this->libPath . 'UserAgentParser.php' ) )
		{
			require dirname( __FILE__ ) . $this->libPath . 'UserAgentParser.php';
		}else return false;

		$y = 0;
		$linesToday = $this->linesFromLog[ Date::current('Y-m-d') ];
		$i = count( $linesToday );
		$linesToday = array_reverse( $linesToday );
		$ccode = '';

		foreach ( $linesToday as $line) {
			if( $y == $this->getValue('numEntriesInLog') ) break;
			$data = json_decode( $line );
			$ip = $data[0];
			if( function_exists('geoip_country_code_by_name') )
			{
				$ccode = '[' . geoip_country_code_by_name( $ip ) . '] ';
			}
			$datetime = Date::format( $data[1], DB_DATE_FORMAT, 'G:i:s');
			$request = $data[2];
			$referer = $data[3];
			$ua = $data[4];
			if( isset($data[5]) || !empty($data[5]) )
			{
				$ghba = $data[5];
			}
			$ua_info = parse_user_agent( $ua );

			// Row in log
			$html .= '<tr>';
			$html .= '<th scope="row">' . $i . '</th>';
			$html .= '<td>' . $datetime . '</td>';
			$html .= '<td><a href="https://dnschecker.org/ip-location.php?ip=' . $ip . '" title="' . $ghba . '" target="_blank" class="info-link" rel="noreferrer">' . $ip . '</a></td>';
			$html .= '<td><a href="' . DOMAIN . $request . '" title="' . $request . '" target="_blank" rel="noreferrer">' . $request . '</a></td>';
			$html .= '<td class="referer"><a href="' . $referer . '" title="' . $referer . '" target="_blank" rel="noreferrer">' . $referer . '</a></td>';

			if( $this->isBot( $ua ) )
			{
				$html .= '<td>' . $this->getBot( $ua ) . '</td>';
			}else{
				$html .= '<td>' . $ccode . $this->getPlatformIcon( $ua_info['platform'] ) . ' ' . $this->getBrowserIcon( $ua_info['browser'], $ua_info['version'] ) . ' ' . $this->getInfoIcon( $ua ) . '</td>';
			}
			$html .= '</tr>';
			// ---
			$i--;
			$y++;
			unset($datetime, $ip, $line, $ua, $ua_info, $data, $referer, $request, $ghba );
		}
		$html .= '</tbody>';
		$html .= '</table>';
		$html .= '<p class="clearfix pt-5"></p>';
		return $html;
	}



	/** Return platform icon and name of platform in title
	 *
	 * @param string Platform
	 * @return string HTML
	 */
	private function getPlatformIcon( $platform )
	{
		$iconSize = '16';

		switch ( $platform ) {
					case 'Macintosh':
					case 'iPad':
					case 'iPhone':
					case 'iPod':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/apple.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Chrome OS':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/chromeos.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Linux':
					case 'FreeBSD':
					case 'NetBSD':
					case 'OpenBSD':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/linux.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Windows':
					case 'Windows Phone':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/windows.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Android':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/android.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'BlackBerry':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/blackberry.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Kindle':
					case 'Kindle Fire':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/kindle.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'New Nintendo 3DS':
					case 'Nintendo 3DS':
					case 'Nintendo DS':
					case 'Nintendo Switch':
					case 'Nintendo Wii':
					case 'Nintendo WiiU':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/nintendo.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'PlayStation 3':
					case 'PlayStation 4':
					case 'PlayStation 5':
					case 'PlayStation Vita':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/playstation.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Symbian':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/symbian.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					case 'Xbox':
					case 'Xbox One':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/xbox.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
						break;
					default:
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/unknown-platform.png" alt="' . $platform .'" title="' . $platform .'" width="' . $iconSize . '" height="' . $iconSize . '" class="platform-ico">';
		}
	}



	/** Return browser icon and name of browser in title
	 *
	 * @param string Browser
	 * @param string Version
	 * @return string HTML
	 */
	private function getBrowserIcon( $browser, $version )
	{
		$iconSize = '16';

		switch ( $browser ) {
					case 'Chrome':
					case 'HeadlessChrome':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/chrome.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Safari':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/safari.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Firefox':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/firefox.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Opera':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/opera.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'curl':
					case 'Wget':
					case 'Lynx':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/bash.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'MSIE':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/explorer.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Edge':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/edge.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Android Browser':
					case 'BlackBerry Browser':
					case 'Browser':
					case 'IEMobile':
					case 'SamsungBrowser':
					case 'TizenBrowser':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/mobile-browser.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'UC Browser':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/uc-browser.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Vivaldi':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/vivaldi.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Puffin':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/puffin.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'MiuiBrowser':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/miui.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					case 'Midori':
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/midori.png" alt="' . $browser .' ' . $version . '" title="' . $browser .' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
						break;
					default:
						return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/unknown-browser.png" alt="' . $browser . ' ' . $version . '" title="' . $browser . ' ' . $version . '" width="' . $iconSize . '" height="' . $iconSize . '" class="browser-ico">';
		}
	}



	/** Return info icon and useragent text as title
	 *
	 * @param string Useragent text
	 * @return string HTML
	 */
	private function getInfoIcon( $text )
	{
		return '<img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/info.png" alt="Info" title="' . $text . '" width="16" height="16" class="info-ico">';
	}



	/** Plugin page in administration
	 *
	 * @return void
	 */
	public function adminView()
    {
		global $L;
		global $site;

		$visitor_statistics_version = $this->build;

		$currentDate = Date::current( 'Y-m-d' );
		$visitsToday = $this->getVisitsByDate( $currentDate );
		$uniqueVisitors = $this->getUniqueVisitsByDate( $currentDate );

		$numOfDays = $this->numOfDays;
		$numOfDays = $numOfDays - 1;
		$date_format = trim( str_replace(',', '', str_ireplace('Y', '', $site->dateFormat() ) ) );
		for ($i = $numOfDays; $i >= 0 ; $i--) {
			$dateWithOffset = Date::currentOffset('Y-m-d', '-'.$i.' day');
			$visits[$i] = $this->getVisitsByDate( $dateWithOffset );
			$unique[$i] = $this->getUniqueVisitsByDate( $dateWithOffset );
			$days[$i] = Date::format($dateWithOffset, 'Y-m-d', $date_format);
		}
		$labels = "'" . implode("','", $days) . "'";
		$seriesVisits = implode(',', $visits);
		$seriesUnique = implode(',', $unique);

		$html = PHP_EOL . '<!-- Plugin Visitor Statistics -->' . PHP_EOL;
		$html .= '<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js" integrity="sha256-+8RZJua0aEWg+QVVKg4LEzEEm/8RFez5Tb4JBNiV5xA=" crossorigin="anonymous"></script>' . PHP_EOL;
		$html .= '<link href="https://fonts.googleapis.com/css2?family=Nunito:ital,wght@0,300;0,400;0,700;1,300;1,400;1,700&display=swap" rel="stylesheet">' . PHP_EOL;
		$html .= '<style>.visitor-statistics-plugin-page{font-family:Nunito,sans;font-size:1rem;}a.info-link, img.browser-ico, img.platform-ico, img.info-ico, .badge{ cursor:help !important;}@media (max-width: 992px){ h3.visitor-statistics-title{padding-top:2rem!important;}.card{margin-top:1rem;}.referer{display:none !important;}}</style>' . PHP_EOL;
		$html .= '<div class="visitor-statistics-plugin-page">' . PHP_EOL;
		$html .= '<h2><img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/chart.png" alt="" width="30" height="30" class="mr-2">' . $L->get('Visitor Statistics') . '</h2>' . PHP_EOL;
		$html .= $this->getUpdateMessage();
		$html .= '<div class="visitor-statistics-plugin"><div class="mb-2 pb-2 border-top pt-2"><div class="chart-container" style="position: relative; height:50% !important; width:100%; font-size: 90%;"><canvas id="visitorStatisticsChart" aria-label="Visits Stats" role="img">' . $L->get('Your browser does not support the canvas element.') . '</canvas></div></div></div>';
		$html .= '<p class="clearfix py-1 my-1"></p>' . PHP_EOL;
		$html .= '<div class="row mb-4">' . PHP_EOL;
		$html .= '<div class="col-sm-12 col-lg-6 mt-3"><div class="card text-center shadow"><div class="card-header" style="background:#E8E8E8;"><strong>' . $L->get('Visits today') . '</strong></div><div class="card-body"><p><h1 style="color:' . $this->visitsChartBgrColor . '"><strong>' . $visitsToday . '</strong></h1></p></div></div></div><div class="col-sm-12 col-lg-6 mt-3"><div class="card text-center shadow"><div class="card-header" style="background:#E8E8E8;"><strong>' . $L->get('Unique Visits today') . '</strong></div><div class="card-body"><p><h1 style="color:' . $this->uniqueVisitsChartBgrColor . '"><strong>' . $uniqueVisitors . '</strong></h1></p></div></div></div>';
		$html .= '<div class="col-sm-12 col-lg-6 mt-3"><div class="card text-center shadow"><div class="card-header" style="background:#E8E8E8;"><strong>' . $L->get('Visits per month') . '</strong></div><div class="card-body"><p><h1 style="color:' . $this->visitsChartBgrColor . '"><strong>' . $this->getVisitsPerMonth() . '</strong></h1></p></div></div></div><div class="col-sm-12 col-lg-6 mt-3"><div class="card text-center shadow"><div class="card-header" style="background:#E8E8E8;"><strong>' . $L->get('Unique Visits per month') . '</strong></div><div class="card-body"><p><h1 style="color:' . $this->uniqueVisitsChartBgrColor . '"><strong>' . $this->getUniqueVisitsPerMonth() . '</strong></h1></p></div></div></div>';
		$html .= '</div>' . PHP_EOL;
		$html .= $this->getTodayLog();
		$html .= '<p class="clearfix"></p>' . PHP_EOL;
		$html .= $this->getPluginFooter();
		$html .= '<p class="clearfix"></p>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;
		$html .= '<!-- /Plugin Visitor Statistics -->' . PHP_EOL;

		$tv_label = $L->get('Visits per day');
		$tuv_label = $L->get('Unique visits per day');
		$visitsChartBgrColor = $this->visitsChartBgrColor;
		$uniqueVisitsChartBgrColor = $this->uniqueVisitsChartBgrColor;
		$days_text = $L->get('Days');
		$visits_text = $L->get('Visits');
		$script = <<<EOF
<script>
	const labels = [$labels];
	const data = {
		labels: labels,
		datasets: [{
			label: '$tv_label',
			backgroundColor: '$visitsChartBgrColor',
			borderColor: '$visitsChartBgrColor',
			data: [$seriesVisits],
			tension: 0.4,
			pointStyle: 'rect',
			pointRadius: 4,
			pointHoverRadius: 8
		},
		{
			label: '$tuv_label',
			backgroundColor: '$uniqueVisitsChartBgrColor',
			borderColor: '$uniqueVisitsChartBgrColor',
			data: [$seriesUnique],
			tension: 0.4,
			pointStyle: 'circle',
			pointRadius: 4,
			pointHoverRadius: 8
		}
		]
	};
	const config = {
		type: 'line',
		data: data,
		options: {
			responsive: true,
			interaction: {
				intersect: false,
			},
			scales: {
				x: {
					display: true,
					title: {
						display: false,
						text: '$days_text'
					}
				},
				y: {
					display: true,
					title: {
						display: false,
						text: '$visits_text'
					},
					beginAtZero: true,
					onlyInteger: true,
					suggestedMin: 0,
					ticks: {
						stepSize: 1
					}
				}
			}
		}
	};
	const myChart = new Chart( document.getElementById('visitorStatisticsChart'), config );
</script>

EOF;

		$script .= $this->getUpdateScript();
		$this->deleteOldLogs();
		return $html.PHP_EOL.$script.PHP_EOL;
    }



	/** Return HTML of modal
	 *
	 * @return string HTML
	 */
    private function getModal()
    {
		global $L;

		$html = '';
		$html .= '<div class="modal fade modal-dialog modal-dialog-centered" id="visitorStatisticsError" tabindex="-1" style="z-index:9999;"><div class="modal-dialog"><div class="modal-content">
<div class="modal-header bg-danger"><h5 class="modal-title">' . $L->get('ERROR') . '</h5>
<button type="button" class="close" data-dismiss="modal" aria-label="' . $L->get('Close') . '">
<span aria-hidden="true">&times;</span></button></div><div class="modal-body">
<p>' . $L->get('Plugin Simple stats is active. Deactivate it!') . '</p>
<p>' . $L->get('You cannot have the Simple stats and Visitor Statistics plugins active at the same time.') . '</p>
</div><div class="modal-footer"><button type="button" class="btn btn-danger" data-dismiss="modal">' . $L->get('Close') . '</button>
</div></div></div></div>';
		return $html;
	}


	/** Message if new version of this plugin is avalaible
	 *
	 * @return string HTML
	 */
	private function getUpdateMessage()
	{
		global $L;

		$html = '';
		$html .= '<div class="alert alert-warning mt-2 mb-2" id="visitorStatisticsUpdate" style="display:none">';
		$html .= '<h4 class="border-bottom border-warning">' . $L->get('New version of plugin') . ' ' . $L->get('Visitor Statistics') . ' ' . $L->get('is avalaible.') . '</h4>';
		$html .= '<p>' . $L->get('We recommend downloading and install the new version from') . ' <a href="' . $this->githubUrl . '" rel="noreferer" title="Github">Github</a> ' . $L->get('or from') . ' <a href="' . $this->pluginUrl . '" title="' . $L->get('author website') . '">' . $L->get('author website') . '</a>.</p>';
		$html .= '</div>';

		return $html;
	}



	/** Return JS script for checking new version of this plugin
	 *
	 * @return string HTML
	 */
	private function getUpdateScript()
	{
		$visitor_statistics_version = $this->build;
		$update_url = 'https://elix.mzf.cz/versions/visitor-statistics.json';
		$script = <<<EOF
<script>
$.ajax({
		url: "$update_url",
		method: "GET",
		async: true,
		cache: false,
		dataType: 'json',
		headers: {
              "accept": "application/json",
              "Access-Control-Allow-Origin":"*"
          },
		success: function(json) {
			if (json.stable.build > $visitor_statistics_version) {
				$("#visitorStatisticsUpdate").show();
			}
		},
		error: function(json) {
			console.log("[WARN] [PLUGIN Visitor Statistics] An error occurred while downloading information about the new version of the plugin.");
		}
	});
</script>
EOF;

		return $script;
	}



    /** Adding footer for admin page
     *
     * @return string HTML
     */
    private function getPluginFooter()
    {
		global $L;
		require __DIR__ . $this->libPath . 'PluginFooter.php';
		$tmp = new PluginFooter( array('name' => 'Visitor Statistics', 'url' => 'https://github.com/Elixcz/visitor-statistics', 'language' => $L ) );
		return $tmp->renderFooter();
	}



	/** Adding title for admin page
	 *
	 * @return void
	 */
    public function adminController()
    {
        global $layout;
        global $L;
        $layout["title"] = $L->get('Visitor Statistics') . ' | Bludit';
    }



    /** Return link in admin menu
	 *
	 * @return string
	 */
	public function adminSidebar()
	{
		global $L;
		$pluginName = Text::lowercase(__CLASS__);
		$url = HTML_PATH_ADMIN_ROOT . 'plugin/' . $pluginName;
		$html = PHP_EOL . '<!-- Plugin Visitor Statistics -->' . PHP_EOL;
		$html .= '<a id="visitor-statistics" class="nav-link" href="' . $url . '" title="' . $L->get('Visitor Statistics') . '"><img src="' . DOMAIN_PLUGINS . basename( dirname( __FILE__ ) ) . '/img/chart.png" alt="" width="16" height="16" class="mr-1 me-1">' . $L->get('Visitor Statistics') . '</a>' . PHP_EOL;
		$html .= '<!-- /Plugin Visitor Statistics -->' . PHP_EOL;
		return $html;
	}



    /** Add new visitor from administration page in to log
	 *
	 * @return void
	 */
	public function adminBodyEnd()
	{
		$this->addNewVisit();
	}

}// End class
