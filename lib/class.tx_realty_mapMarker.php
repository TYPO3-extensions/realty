<?php
/***************************************************************
* Copyright notice
*
* (c) 2008-2013 Oliver Klee <typo3-coding@oliverklee.de>
* All rights reserved
*
* This script is part of the TYPO3 project. The TYPO3 project is
* free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* The GNU General Public License can be found at
* http://www.gnu.org/copyleft/gpl.html.
*
* This script is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

/**
 * This class represents a marker on a Google Map.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 */
class tx_realty_mapMarker {
	/**
	 * @var string this marker's latitude
	 */
	private $latitude = '';

	/**
	 * @var string this marker's longitude
	 */
	private $longitude = '';

	/**
	 * @var boolean
	 */
	protected $coordinatesHaveBeenSet = FALSE;

	/**
	 * @var string this marker's title, quote- and tag-safe
	 */
	private $title = '';

	/**
	 * @var string this marker's info window HTML
	 */
	private $infoWindowHtml = '';

	/**
	 * Renders the JavaScript for creating this marker and adding it to an
	 * object 'map'.
	 *
	 * @return string JavaScript snippet for the this marker, will be
	 *                empty if this marker has no coordinates
	 */
	public function render() {
		if (!$this->hasCoordinates()) {
			return '';
		}

		$result = 'var marker = new google.maps.Marker({' . LF .
			'position: ' . $this->getCoordinates() . ',' . LF .
			'map: map,' . LF .
			'title: "' . $this->title . '"});' . LF .
			'markersArray.push(marker);' . LF .
			'google.maps.event.addListener(marker, \'click\', function() {' . LF .
			'myInfoWindow.setContent(\'' . $this->infoWindowHtml . '\');' . LF .
			'myInfoWindow.open(map,this);});' . LF;

		return $result;
	}

	/**
	 * Sets this marker's coordinates.
	 *
	 * @param float $latitude latitude
	 * @param float $longitude longitude
	 *
	 * @return void
	 */
	public function setCoordinates($latitude, $longitude) {
		$this->latitude = $latitude;
		$this->longitude = $longitude;

		$this->coordinatesHaveBeenSet = TRUE;
	}

	/**
	 * Gets this marker's coordinates as a JavaScript GLatLng instantiation.
	 *
	 * @return string this marker's coordinates as a GLatLng instantiation
	 *                JavaScript code snippet, an empty string if this
	 *                marker has no coordinates.
	 */
	public function getCoordinates() {
		if (!$this->hasCoordinates()) {
			return '';
		}

		return 'new google.maps.LatLng(' . number_format($this->latitude, 6, '.', '') . ',' .
			number_format($this->longitude, 6, '.', '') . ')';
	}

	/**
	 * Sets this marker's title.
	 *
	 * @param string $title title, may be empty, must not be HTML-safe
	 *
	 * @return void
	 */
	public function setTitle($title) {
		$this->title = trim(addslashes(strip_tags($title)));
	}

	/**
	 * Sets this marker's info window HTML
	 *
	 * @param string $html info window HTML, may be empty
	 *
	 * @return void
	 */
	public function setInfoWindowHtml($html) {
		// 1. escapes \ to \\
		// 2. escapes ' to \'
		// 3. escapes </ to <\/ (because this is embedded JavaScript)
		// Note: We cannot use addslashes because " must not be escaped.
		$this->infoWindowHtml = str_replace(
			array('\\', '\'', '</'),
			array('\\\\', '\\\'', '<\/'),
			$html
		);
	}

	/**
	 * Checks whether this marker has both latitude and longitude.
	 *
	 * @return boolean TRUE if this marker has coordinates, FALSE otherwise
	 */
	private function hasCoordinates() {
		return $this->coordinatesHaveBeenSet;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_mapMarker.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_mapMarker.php']);
}