<?php
/***************************************************************
* Copyright notice
*
* (c) 2009-2013 Bernd Schönbach <bernd@oliverklee.de>
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

require_once(t3lib_extMgm::extPath('realty') . 'lib/tx_realty_constants.php');

/**
 * Test case.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Oliver Klee <typo3-coding@oliverklee.de>
 * @author Bernd Schönbach <bernd@oliverklee.de>
 */
class tx_realty_FrontEnd_MyObjectsListViewTest extends tx_phpunit_testcase {
	/**
	 * @var tx_realty_pi1_MyObjectsListView
	 */
	private $fixture;

	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	/**
	 * @var integer UID of the dummy realty object
	 */
	private $realtyUid = 0;

	/**
	 * @var string title for the dummy realty object
	 */
	private static $objectTitle = 'a title';

	/**
	 * @var integer system folder PID
	 */
	private $systemFolderPid = 0;

	public function setUp() {
		tx_oelib_headerProxyFactory::getInstance()->enableTestMode();
		$this->testingFramework = new tx_oelib_testingFramework('tx_realty');
		$this->testingFramework->createFakeFrontEnd();
		$this->systemFolderPid = $this->testingFramework->createSystemFolder(1);

		$this->fixture = new tx_realty_pi1_MyObjectsListView(
			array(
				'templateFile' => 'EXT:realty/pi1/tx_realty_pi1.tpl.htm',
				'pages' => $this->systemFolderPid,
			),
			$GLOBALS['TSFE']->cObj,
			TRUE
		);
	}

	public function tearDown() {
		$this->testingFramework->cleanUp();

		unset($this->fixture, $this->testingFramework);
	}


	///////////////////////
	// Utility functions.
	///////////////////////

	/**
	 * Prepares the "my objects" list: Creates and logs in a front-end user and
	 * creates a dummy object with the front-end user as owner.
	 *
	 * @param array $userData
	 *        data with which the user should be created, may be empty
	 *
	 * @return void
	 */
	private function prepareMyObjects(array $userData = array()) {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData($userData);
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->cityUid = $this->testingFramework->createRecord(
			REALTY_TABLE_CITIES,
			array('title' => 'Bonn')
		);
		$this->realtyUid = $this->testingFramework->createRecord(
			REALTY_TABLE_OBJECTS,
			array(
				'title' => self::$objectTitle,
				'object_number' => '1',
				'pid' => $this->systemFolderPid,
				'city' => $this->cityUid,
				'teaser' => '',
				'has_air_conditioning' => '0',
				'has_pool' => '0',
				'has_community_pool' => '0',
				'object_type' => REALTY_FOR_RENTING,
				'owner' => $user->getUid(),
			)
		);
	}


	////////////////////////////////////
	// Tests for the utility functions
	////////////////////////////////////

	/**
	 * @test
	 */
	public function prepareMyObjectsLogsInFrontEndUser() {
		$this->prepareMyObjects();

		$this->assertTrue(
			tx_oelib_FrontEndLoginManager::getInstance()->isLoggedIn()
		);
	}

	/**
	 * @test
	 */
	public function prepareMyObjectsCreatesDummyObject() {
		$this->prepareMyObjects();

		$this->assertTrue(
			$this->testingFramework->existsRecordWithUid(
				REALTY_TABLE_OBJECTS, $this->realtyUid
			)
		);
	}

	/**
	 * @test
	 */
	public function prepareMyObjectsMakesUserOwnerOfOneObject() {
		$this->prepareMyObjects();

		$this->assertTrue(
			$this->testingFramework->existsRecordWithUid(
				REALTY_TABLE_OBJECTS, $this->realtyUid, ' AND owner != 0'
			)
		);
	}

	/**
	 * @test
	 */
	public function prepareMyObjectsCanStoreUsernameForUser() {
		$this->prepareMyObjects(array('username' => 'foo'));

		$this->assertEquals(
			'foo',
			tx_oelib_FrontEndLoginManager::getInstance()->getLoggedInUser()
				->getUserName()
		);
	}


	////////////////////////////////////////
	// Tests concering basic functionality
	////////////////////////////////////////

	/**
	 * @test
	 */
	public function renderForLoggedInUserWhoHasNoObjectsDisplaysNoResultsFoundMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getLoadedTestingModel(array());
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertContains(
			$this->fixture->translate('message_noResultsFound_my_objects'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderDisplaysObjectsTheLoggedInUserOwns() {
		$this->prepareMyObjects();

		$this->assertContains(
			self::$objectTitle,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderNotDisplaysObjectsOfOtherOwner() {
		$this->prepareMyObjects();
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getLoadedTestingModel(array());
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertNotContains(
			self::$objectTitle,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderNotDisplaysObjectsWithoutOwner() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getLoadedTestingModel(array());
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->testingFramework->createRecord(
			REALTY_TABLE_OBJECTS,
			array(
				'title' => 'another object',
				'object_number' => '1',
				'pid' => $this->systemFolderPid,
				'city' => $this->cityUid,
			)
		);

		$this->assertNotContains(
			'another object',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderHasNoUnreplacedMarkers() {
		$this->prepareMyObjects();

		$this->assertNotContains(
			'###',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderContainsEditButton() {
		$this->prepareMyObjects();

		$this->fixture->setConfigurationValue(
			'editorPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertContains(
			'button edit',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function editButtonInTheMyObjectsViewIsLinkedToTheFeEditor() {
		$this->prepareMyObjects();

		$editorPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue('editorPID', $editorPid);

		$this->assertContains(
			'?id=' . $editorPid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function editButtonInTheMyObjectsViewContainsTheRecordUid() {
		$this->prepareMyObjects();

		$this->fixture->setConfigurationValue(
			'editorPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertEquals(
			1,
			substr_count(
				$this->fixture->render(),
				'tx_realty_pi1[showUid]='.$this->realtyUid
			)
		);
	}

	/**
	 * @test
	 */
	public function renderForDeleteUidSentDeletesObjectFromMyObjectsList() {
		$this->prepareMyObjects();

		$this->assertContains(
			self::$objectTitle,
			$this->fixture->render()
		);

		$this->assertNotContains(
			self::$objectTitle,
			$this->fixture->render(array('delete' => $this->realtyUid))
		);
		$this->assertFalse(
			tx_oelib_db::existsRecordWithUid(
				REALTY_TABLE_OBJECTS, $this->realtyUid, ' AND deleted = 0'
			)
		);
	}

	/**
	 * @test
	 */
	public function renderForLoggedInUserWithoutLimitContainsCreateNewObjectLink() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 0));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);


		$this->fixture->setConfigurationValue(
			'editorPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertContains(
			'button newRecord',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForLoggedInUserWithLimitButLessObjectsThanLimitContainsCreateNewObjectLink() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 2));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->setConfigurationValue(
			'editorPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertContains(
			'button newRecord',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForLoggedInUserNoObjectsLeftToEnterHidesCreateNewObjectLink() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->setConfigurationValue(
			'editorPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertNotContains(
			'button newRecord',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function createNewObjectLinkInTheMyObjectsViewContainsTheEditorPid() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array());
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(0));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$editorPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue('editorPID', $editorPid);

		$this->assertContains(
			'?id=' . $editorPid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderDisplaysStatePublished() {
		$this->prepareMyObjects();

		$this->assertContains(
			$this->fixture->translate('label_published'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderDisplaysStatePending() {
		$this->prepareMyObjects();
		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS, $this->realtyUid, array('hidden' => 1)
		);

		$this->assertContains(
			$this->fixture->translate('label_pending'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderHidesLimitHeadingForUserWithMaximumObjectsSetToZero() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 0));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);


		$this->assertNotContains(
			$this->fixture->translate('label_objects_already_entered'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderShowsLimitHeadingForUserWithMaximumObjectsSetToOne() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertContains(
			sprintf($this->fixture->translate('label_objects_already_entered'), 1, 1),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForUserWithOneObjectAndMaximumObjectsSetToOneShowsNoObjectsLeftLabel() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertContains(
			$this->fixture->translate('label_no_objects_left'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForUserWithTwoObjectsAndMaximumObjectsSetToOneShowsNoObjectsLeftLabel() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(2));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertContains(
			$this->fixture->translate('label_no_objects_left'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForUserWithOneObjectAndMaximumObjectsSetToTwoShowsOneObjectLeftLabel() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 2));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);


		$this->assertContains(
			$this->fixture->translate('label_one_object_left'),
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function renderForUserWithNoObjectAndMaximumObjectsSetToTwoShowsMultipleObjectsLeftLabel() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 2));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(0));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->assertContains(
			sprintf($this->fixture->translate('label_multiple_objects_left'), 2),
			$this->fixture->render()
		);
	}


	////////////////////////////////////////////
	// Tests concerning the "advertise" button
	////////////////////////////////////////////

	/**
	 * @test
	 */
	public function myItemWithAdvertisePidAndNoAdvertisementDateHasAdvertiseButton() {
		$this->prepareMyObjects();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $this->testingFramework->createFrontEndPage()
		);

		$this->assertContains(
			'class="button advertise"',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithoutAdvertisePidNotHasAdvertiseButton() {
		$this->prepareMyObjects();

		$this->assertNotContains(
			'class="button advertise"',
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithAdvertisePidLinksToAdvertisePid() {
		$this->prepareMyObjects();
		$advertisementPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $advertisementPid
		);

		$this->assertContains(
			'?id=' . $advertisementPid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithAdvertiseParameterUsesParameterWithObjectUid() {
		$this->prepareMyObjects();
		$advertisementPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $advertisementPid
		);
		$this->fixture->setConfigurationValue(
			'advertisementParameterForObjectUid', 'foo'
		);

		$this->assertContains(
			'foo=' . $this->realtyUid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithPastAdvertisementDateAndZeroExpiryNotHasLinkToAdvertisePid() {
		$this->prepareMyObjects();

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->realtyUid,
			array('advertised_date' => $GLOBALS['SIM_ACCESS_TIME'] - tx_oelib_Time::SECONDS_PER_DAY)
		);

		$this->fixture->setConfigurationValue(
			'advertisementExpirationInDays', 0
		);
		$advertisementPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $advertisementPid
		);

		$this->assertNotContains(
			'?id=' . $advertisementPid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithPastAdvertisementDateAndNonZeroSmallEnoughExpiryHasLinkToAdvertisePid() {
		$this->prepareMyObjects();

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->realtyUid,
			array('advertised_date' => $GLOBALS['SIM_ACCESS_TIME'] - 10)
		);

		$this->fixture->setConfigurationValue(
			'advertisementExpirationInDays', 1
		);
		$advertisementPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $advertisementPid
		);

		$this->assertContains(
			'?id=' . $advertisementPid,
			$this->fixture->render()
		);
	}

	/**
	 * @test
	 */
	public function myItemWithPastAdvertisementDateAndNonZeroTooBigExpiryNotHasLinkToAdvertisePid() {
		$this->prepareMyObjects();

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->realtyUid,
			array('advertised_date' => $GLOBALS['SIM_ACCESS_TIME'] - 2 * tx_oelib_Time::SECONDS_PER_DAY)
		);

		$this->fixture->setConfigurationValue(
			'advertisementExpirationInDays', 1
		);
		$advertisementPid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue(
			'advertisementPID', $advertisementPid
		);

		$this->assertNotContains(
			'?id=' . $advertisementPid,
			$this->fixture->render()
		);
	}
}