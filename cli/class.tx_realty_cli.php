<?php
/***************************************************************
* Copyright notice
*
* (c) 2007-2008 Saskia Metzler <saskia@merlin.owl.de>
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
 * Class 'tx_realty_cli' for the 'realty' extension.
 *
 * This class provides access via command line interface.
 *
 * To run this script, use the following command in a console: '/[absolute path
 * of the TYPO3 installation]/typo3/cli_dispatch.phpsh openImmoImport'.
 *
 * @package		TYPO3
 * @subpackage	tx_realty
 * @author		Saskia Metzler <saskia@merlin.owl.de>
 */
if (!defined('TYPO3_cliMode')) {
	die('You cannot run this script directly!');
}

require_once(PATH_t3lib.'class.t3lib_cli.php');
require_once(t3lib_extMgm::extPath('realty').'lib/class.tx_realty_openImmoImport.php');

class tx_realty_cli {
	/**
	 * Calls the OpenImmo importer.
	 */
	public function main() {
		$importer = t3lib_div::makeInstance('tx_realty_openImmoImport');
		echo $importer->importFromZip();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/cli/class.tx_realty_cli.php']) {
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/cli/class.tx_realty_cli.php']);
}

t3lib_div::makeInstance('tx_realty_cli')->main();

?>
