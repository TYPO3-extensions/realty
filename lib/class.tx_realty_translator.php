<?php
/***************************************************************
* Copyright notice
*
* (c) 2008-2013 Saskia Metzler <saskia@merlin.owl.de>
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
 * This class translates localized strings used in this extension's lib/
 * directory.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Saskia Metzler <saskia@merlin.owl.de>
 */
class tx_realty_translator {
	/**
	 * Constructor.
	 */
	public function __construct() {
		// expected by the LANG object
		global $TYPO3_CONF_VARS;

		if (!is_object($GLOBALS['LANG'])) {
			$GLOBALS['LANG'] = t3lib_div::makeInstance('language');
		}
		$cliLanguage = tx_oelib_configurationProxy::getInstance('realty')->getAsString('cliLanguage');
		// "default" is used as language key if the configured language key is not within the set of available language keys.
		if (t3lib_utility_VersionNumber::convertVersionNumberToInteger(TYPO3_version) < 4006000) {
			$languageKey = (strpos(TYPO3_languages, '|' . $cliLanguage . '|') !== FALSE) ? $cliLanguage : 'default';
		} else {
			/** @var $locales t3lib_l10n_Locales */
			$locales = t3lib_div::makeInstance('t3lib_l10n_Locales');
			$languageKey = in_array($cliLanguage, $locales->getLocales())? $cliLanguage : 'default';
		}

		$GLOBALS['LANG']->init($languageKey);
		$GLOBALS['LANG']->includeLLFile('EXT:realty/lib/locallang.xml');
	}

	/**
	 * Retrieves the localized string for the local language key $key.
	 *
	 * @param string $key the local language key for which to return the value, must not be empty
	 *
	 * @return string the localized string for $key or just the key if
	 *                there is no localized string for the requested key
	 */
	public function translate($key) {
		if ($key == '') {
			throw new InvalidArgumentException('$key must not be empty.', 1333035608);
		}

		$result = $GLOBALS['LANG']->getLL($key);

		return ($result != '') ? $result : $key;
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_translator.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_translator.php']);
}