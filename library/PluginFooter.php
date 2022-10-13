<?php defined('BLUDIT') or die('Unauthorized access!');

/** Plugin footer with support and update information
 *  -------------------------------------------------
 */
class PluginFooter{

	private $pluginName      = '';
	private $pluginUrl       = '';
	private $pluginAuthor    = 'Elix';
	private $pluginAuthorUrl = 'https://elix.mzf.cz';
	private $headerColor     = '#E9E9E9';
	private $bodyColor       = '#FAFFFA';



	/** Initialize class
	 *
	 * @param array Settings of this plugin
	 * @return void
	 */
	public function __construct( $pluginData )
	{
		$this->pluginName = $pluginData['name'];
		$this->pluginUrl  = $pluginData['url'];
		$this->pluginLang = $pluginData['language'];
	}



	/** Render footer
	 *
	 * @return string HTML
	 */
	public function renderFooter(): string
	{
		$html  = '<!-- Plugin ' . $this->pluginName . ' -->' . PHP_EOL;
		$html .= '<div class="row mb-5"><div class="col-sm-12">' . PHP_EOL;
		$html .= '<div class="card shadow mt-2">' . PHP_EOL;
		$html .= '<div class="card-header" style="background:' . $this->headerColor . ';text-shadow: 1px 1px 0 #fff;">' . PHP_EOL;
		$html .= '<strong>' . $this->pluginLang->get('Support and updates') . '</strong>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;
		$html .= '<div class="card-body" style="background:' . $this->bodyColor . ';">' . PHP_EOL;
		$html .= '<p>' . $this->pluginLang->get('Please support the development and updates of this plugin.') . ' ' . $this->pluginLang->get('Every donation will increase the motivation to continue working on this plugin.') . '</p><p class="text-center pt-1 pb-1 "><a href="bitcoin://bc1q03v5la7uvcwxr7z4qn03ex6n5edju6zv4n6ppt" title="BTC" target="_blank" class="btn btn-success btn-large">DONATE BTC</a><br><br><code>bc1q03v5la7uvcwxr7z4qn03ex6n5edju6zv4n6ppt</code></p>' . PHP_EOL;
		$html .= '<div class="row my-2 border-top pt-1"><div class="col-sm-12 col-lg-4 pt-1"><strong>' . $this->pluginLang->get('Source code') . ':</strong><br><a href="' . $this->pluginUrl . '" target="_blank">' . $this->pluginUrl . '</a></div><div class="col-sm-12 col-lg-4 pt-1"><strong>' . $this->pluginLang->get('Author') . ':</strong><br><a href="' . $this->pluginAuthorUrl . '" target="_blank">' . $this->pluginAuthorUrl . '</a></div><div class="col-sm-12 col-lg-4 pt-1"><strong>' . $this->pluginLang->get('Other plugins and themes') . ':</strong><br><a href="' . $this->pluginAuthorUrl . '" target="_blank">' . $this->pluginAuthorUrl . '</a></div></div>' . PHP_EOL;
		$html .= '</div>' . PHP_EOL;
		$html .= '</div></div></div>' . PHP_EOL;
		$html .= '<p class="clearfix"></p>' . PHP_EOL;
		$html .= '<!-- /Plugin ' . $this->pluginName . ' -->' . PHP_EOL;

		return $html;
	}



}// End class
