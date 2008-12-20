<?php
/***************************************************************
* Copyright notice
*
* (c) 2008 Saskia Metzler <saskia@merlin.owl.de>
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

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_Autoloader.php');

require_once(t3lib_extMgm::extPath('realty') . 'lib/tx_realty_constants.php');
require_once(t3lib_extMgm::extPath('realty') . 'lib/class.tx_realty_object.php');
require_once(t3lib_extMgm::extPath('realty') . 'pi1/class.tx_realty_pi1.php');
require_once(t3lib_extMgm::extPath('realty') . 'pi1/class.tx_realty_frontEndForm.php');

/**
 * Unit tests for the tx_realty_frontEndForm class in the 'realty' extension.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Saskia Metzler <saskia@merlin.owl.de>
 */
class tx_realty_frontEndForm_testcase extends tx_phpunit_testcase {
	/** FE form object to be tested */
	private $fixture;
	/** instance of tx_realty_pi1 */
	private $pi1;
	/** instance of tx_oelib_testingFramework */
	private $testingFramework;

	/** dummy FE user UID */
	private $feUserUid;
	/** UID of the dummy object */
	private $dummyObjectUid = 0;

	public function setUp() {
		tx_oelib_headerProxyFactory::getInstance()->enableTestMode();
		$this->testingFramework = new tx_oelib_testingFramework('tx_realty');
		$this->testingFramework->createFakeFrontEnd();

		$this->createDummyRecords();

		$this->pi1 = new tx_realty_pi1();
		$this->pi1->init(array(
			'templateFile' => 'EXT:realty/pi1/tx_realty_pi1.tpl.htm',
			'feEditorTemplateFile'
				=> 'EXT:realty/pi1/tx_realty_frontEndEditor.html',
		));
		$this->pi1->getTemplateCode();

		$this->fixture = new tx_realty_frontEndForm($this->pi1, 0, '', true);
	}

	public function tearDown() {
		tx_oelib_headerProxyFactory::getInstance()->discardInstance();
		$this->testingFramework->logoutFrontEndUser();
		$this->testingFramework->cleanUp();

		$this->fixture->__destruct();
		$this->pi1->__destruct();
		unset($this->fixture, $this->pi1, $this->testingFramework);
	}


	///////////////////////
	// Utility functions.
	///////////////////////

	/**
	 * Creates dummy records in the DB.
	 */
	private function createDummyRecords() {
		$this->feUserUid = $this->testingFramework->createFrontEndUser();
		$this->dummyObjectUid = $this->testingFramework->createRecord(
			REALTY_TABLE_OBJECTS
		);
	}


	///////////////////////////////////////////////
	// Tests concerning access and authorization.
	///////////////////////////////////////////////

	public function testCheckAccessReturnsObjectDoesNotExistMessageForAnInvalidUidAndNoUserLoggedIn() {
		$this->fixture->setRealtyObjectUid(
			$this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		);

		$this->assertContains(
			$this->pi1->translate('message_noResultsFound_fe_editor'),
			$this->fixture->checkAccess()
		);
	}

	public function testCheckAccessReturnsObjectDoesNotExistMessageForAnInvalidUidAndAUserLoggedIn() {
		$this->testingFramework->loginFrontEndUser($this->feUserUid);
		$this->fixture->setRealtyObjectUid(
			$this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		);

		$this->assertContains(
			$this->pi1->translate('message_noResultsFound_fe_editor'),
			$this->fixture->checkAccess()
		);
	}

	public function testHeaderIsSentWhenCheckAccessReturnsObjectDoesNotExistMessage() {
		$this->testingFramework->loginFrontEndUser($this->feUserUid);
		$this->fixture->setRealtyObjectUid(
			$this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		);

		$this->assertContains(
			$this->pi1->translate('message_noResultsFound_fe_editor'),
			$this->fixture->checkAccess()
		);
		$this->assertEquals(
			'Status: 404 Not Found',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	public function testCheckAccessReturnsPleaseLoginMessageForANewObjectIfNoUserIsLoggedIn() {
		$this->fixture->setRealtyObjectUid(0);

		$this->assertContains(
			$this->pi1->translate('message_please_login'),
			$this->fixture->checkAccess()
		);
	}

	public function testCheckAccessReturnsPleaseLoginMessageForAnExistingObjectIfNoUserIsLoggedIn() {
		$this->fixture->setRealtyObjectUid($this->dummyObjectUid);

		$this->assertContains(
			$this->pi1->translate('message_please_login'),
			$this->fixture->checkAccess()
		);
	}

	public function testCheckAccessReturnsAccessDeniedMessageWhenLoggedInUserAttemptsToEditAnObjectHeDoesNotOwn() {
		$this->testingFramework->createAndLoginFrontEndUser();
		$this->fixture->setRealtyObjectUid($this->dummyObjectUid);

		$this->assertContains(
			$this->pi1->translate('message_access_denied'),
			$this->fixture->checkAccess()
		);
	}

	public function testHeaderIsSentWhenCheckAccessReturnsAccessDeniedMessage() {
		$this->testingFramework->createAndLoginFrontEndUser();
		$this->fixture->setRealtyObjectUid($this->dummyObjectUid);

		$this->assertContains(
			$this->pi1->translate('message_access_denied'),
			$this->fixture->checkAccess()
		);
		$this->assertEquals(
			'Status: 403 Forbidden',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	public function testCheckAccessReturnsAnEmptyStringIfTheObjectExistsAndTheUserIsAuthorized() {
		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->dummyObjectUid,
			array('owner' => $this->feUserUid)
		);
		$this->testingFramework->loginFrontEndUser($this->feUserUid);
		$this->fixture->setRealtyObjectUid($this->dummyObjectUid);

		$this->assertEquals(
			'',
			$this->fixture->checkAccess()
		);
	}

	public function testCheckAccessReturnsAnEmptyStringIfTheObjectIsNewAndTheUserIsAuthorized() {
		$this->testingFramework->loginFrontEndUser($this->feUserUid);
		$this->fixture->setRealtyObjectUid(0);

		$this->assertEquals(
			'',
			$this->fixture->checkAccess()
		);
	}


	//////////////////////////////////////
	// Functions to be used by the form.
	//////////////////////////////////////
	// * getRedirectUrl().
	////////////////////////

	public function testGetRedirectUrlReturnsUrlWithRedirectPidForConfiguredRedirectPid() {
		$fePageUid = $this->testingFramework->createFrontEndPage();
		$this->pi1->setConfigurationValue('feEditorRedirectPid', $fePageUid);

		$this->assertContains(
			'?id=' . $fePageUid,
			$this->fixture->getRedirectUrl()
		);
	}

	public function testGetRedirectUrlReturnsUrlWithoutRedirectPidForMisconfiguredRedirectPid() {
		$nonExistingFePageUid = $this->testingFramework->createFrontEndPage(
			0, array('deleted' => 1)
		);
		$this->pi1->setConfigurationValue(
			'feEditorRedirectPid', $nonExistingFePageUid
		);

		$this->assertNotContains(
			'?id=' . $nonExistingFePageUid,
			$this->fixture->getRedirectUrl()
		);
	}

	public function testGetRedirectUrlReturnsUrlWithoutRedirectPidForNonConfiguredRedirectPid() {
		$this->pi1->setConfigurationValue('feEditorRedirectPid', '0');

		$this->assertNotContains(
			'?id=0',
			$this->fixture->getRedirectUrl()
		);
	}


	///////////////////////////////////////
	// Tests concerning the HTML template
	///////////////////////////////////////

	public function testGetTemplatePathReturnsAbsolutePathFromTheConfiguration() {
		$this->assertRegExp(
			'/\/typo3conf\/ext\/realty\/pi1\/tx_realty_frontEndEditor\.html$/',
			$this->fixture->getTemplatePath()
		);
	}
}
?>