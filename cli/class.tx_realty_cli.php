<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2013 Saskia Metzler <saskia@merlin.owl.de>
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

if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

setlocale(LC_NUMERIC, 'C');

/**
 * This class provides access via command-line interface.
 *
 * To run this script, use the following command in a console: '/[absolute path
 * of the TYPO3 installation]/typo3/cli_dispatch.phpsh openImmoImport'.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Saskia Metzler <saskia@merlin.owl.de>
 */
class tx_realty_cli {
	/**
	 * Calls the OpenImmo importer.
	 *
	 * @return void
	 */
	public function main() {
		try {
			$importer = t3lib_div::makeInstance('tx_realty_openImmoImport');
			echo $importer->importFromZip();
		} catch (Exception $exception) {
			echo $exception->getMessage() . LF . LF .
				$exception->getTraceAsString() . LF .LF;
		}
	}
}

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/cli/class.tx_realty_cli.php']) {
	include_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/realty/cli/class.tx_realty_cli.php']);
}

t3lib_div::makeInstance('tx_realty_cli')->main();