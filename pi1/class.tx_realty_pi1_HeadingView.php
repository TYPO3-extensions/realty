<?php
/***************************************************************
* Copyright notice
*
* (c) 2009-2013 Saskia Metzler <saskia@merlin.owl.de>
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
 * This class renders the heading of a single realty object.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Saskia Metzler <saskia@merlin.owl.de>
 */
class tx_realty_pi1_HeadingView extends tx_realty_pi1_FrontEndView {
	/**
	 * Returns the heading view as HTML.
	 *
	 * @param array $piVars piVars array, must contain the key "showUid" with a valid
	 *              realty object UID as value
	 *
	 * @return string HTML for the heading view or an empty string if the
	 *                realty object with the provided UID has no title
	 */
	public function render(array $piVars = array()) {
		$title = htmlspecialchars(
			tx_oelib_MapperRegistry::get('tx_realty_Mapper_RealtyObject')
				->find($piVars['showUid'])->getProperty('title')
		);

		$this->setOrDeleteMarkerIfNotEmpty('heading', $title, '', 'field_wrapper');

		return $this->getSubpart('FIELD_WRAPPER_HEADING');
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/pi1/class.tx_realty_pi1_HeadingView.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/pi1/class.tx_realty_pi1_HeadingView.php']);
}