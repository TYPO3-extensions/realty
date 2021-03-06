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

require_once(t3lib_extMgm::extPath('realty') . 'lib/tx_realty_constants.php');

/**
 * Test case.
 *
 * @package TYPO3
 * @subpackage tx_realty
 *
 * @author Saskia Metzler <saskia@merlin.owl.de>
 */
class tx_realty_Service_AccessCheckTest extends tx_phpunit_testcase {
	/**
	 * @var tx_realty_pi1_AccessCheck
	 */
	private $fixture;
	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	/**
	 * @var integer UID of the dummy object
	 */
	private $dummyObjectUid;

	public function setUp() {
		tx_oelib_headerProxyFactory::getInstance()->enableTestMode();

		$this->testingFramework = new tx_oelib_testingFramework('tx_realty');
		$this->testingFramework->createFakeFrontEnd();
		$this->dummyObjectUid = $this->testingFramework->createRecord(
			REALTY_TABLE_OBJECTS
		);

		$this->fixture = new tx_realty_pi1_AccessCheck();
	}

	public function tearDown() {
		$this->testingFramework->cleanUp();

		unset($this->fixture, $this->testingFramework);
	}


	//////////////////////////////////////////////
	// Tests concerning access to the FE editor.
	//////////////////////////////////////////////

	/**
	 * @test
	 */
	public function checkAccessForFeEditorThrowsExceptionWithPleaseLoginMessageForAnInvalidUidAndNoUserLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('fe_editor', array(
			'showUid' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorThrowsExceptionWithObjectDoesNotExistMessageForAnInvalidUidAndAUserLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_noResultsFound_fe_editor'
		);

		$this->fixture->checkAccess('fe_editor', array(
			'showUid' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function header404IsSentWhenCheckAccessForFeEditorThrowsExceptionWithObjectDoesNotExistMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess('fe_editor', array(
				'showUid' => $this->testingFramework->createRecord(
					REALTY_TABLE_OBJECTS, array('deleted' => 1)
				)
			));
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 404 Not Found',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorThrowsExceptionWithPleaseLoginMessageForNewObjectIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('fe_editor', array('showUid' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorThrowsExceptionWithPleaseLoginMessageForAnExistingObjectIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess(
			'fe_editor', array('showUid' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorThrowsExceptionWithAccessDeniedMessageWhenLoggedInUserAttemptsToEditAnObjectHeDoesNotOwn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_access_denied'
		);

		$this->fixture->checkAccess(
			'fe_editor', array('showUid' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function header403IsSentWhenCheckAccessForFeEditorThrowsExceptionWithAccessDeniedMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess(
				'fe_editor', array('showUid' => $this->dummyObjectUid)
			);
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 403 Forbidden',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorDoesNotThrowAnExceptionIfTheObjectExistsAndTheUserIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->dummyObjectUid,
			array('owner' => $user->getUid())
		);

		$this->fixture->checkAccess(
			'fe_editor', array('showUid' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorDoesNotThrowAnExceptionIfTheNonPublishedObjectExistsAndTheUserIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->dummyObjectUid,
			array(
				'owner' => $user->getUid(),
				'hidden' => TRUE,
			)
		);

		$this->fixture->checkAccess(
			'fe_editor', array('showUid' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorDoesNotThrowAnExceptionIfTheObjectIsNewAndTheUserIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getLoadedTestingModel(array());
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->checkAccess('fe_editor', array('showUid' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorForLoggedInUserWithNoObjectsLeftToEnterThrowsExceptionWithNoObjectsLeftMessage() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_no_objects_left'
		);

		$this->fixture->checkAccess('fe_editor', array('showUid' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorForLoggedInUserWithNoObjectsLeftToEnterAndEditingAnExistingObjectDoesNotThrowException() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(1));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$objectUid = $this->testingFramework->createRecord(
			REALTY_TABLE_OBJECTS, array('owner' => $user->getUid())
		);

		$this->fixture->checkAccess('fe_editor', array('showUid' => $objectUid));
	}

	/**
	 * @test
	 */
	public function checkAccessForFeEditorForLoggedInUserWithObjectsLeftToEnterThrowsNoException() {
		$user = $this->getMock(
			'tx_realty_Model_FrontEndUser', array('getNumberOfObjects')
		);
		$user->setData(array('tx_realty_maximum_objects' => 1));
		$user->expects($this->any())->method('getNumberOfObjects')
			->will($this->returnValue(0));
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->checkAccess('fe_editor', array('showUid' => 0));
	}


	/////////////////////////////////////////////////
	// Tests concerning access to the image upload.
	/////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithPleaseLoginMessageForAnInvalidUidAndNoUserLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('image_upload', array(
			'showUid' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithObjectDoesNotExistMessageForZeroObjectUidAndAUserLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_noResultsFound_image_upload'
		);

		$this->fixture->checkAccess('image_upload', array('showUid' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithObjectDoesNotExistMessageForAnInvalidObjectUidAndAUserLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_noResultsFound_fe_editor'
		);

		$this->fixture->checkAccess('image_upload', array(
			'showUid' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function header404IsSentWhenCheckAccessForImageUploadThrowsExceptionWithObjectDoesNotExistMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess('image_upload', array(
				'showUid' => $this->testingFramework->createRecord(
					REALTY_TABLE_OBJECTS, array('deleted' => 1)
				)
			));
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 404 Not Found',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithPleaseLoginMessageForNewObjectIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('image_upload', array('showUid' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithPleaseLoginMessageForAnExistingObjectIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('image_upload', array(
			'showUid' => $this->dummyObjectUid
		));
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadThrowsExceptionWithAccessDeniedMessageWhenLoggedInUserAttemptsToEditAnObjectHeDoesNotOwn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_access_denied'
		);

		$this->fixture->checkAccess(
			'image_upload', array('showUid' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function header403IsSentWhenCheckAccessForImageUploadThrowsExceptionWithAccessDeniedMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess(
				'image_upload', array('showUid' => $this->dummyObjectUid)
			);
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 403 Forbidden',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForImageUploadDoesNotThrowAnExceptionIfTheObjectExistsAndTheUserIsOwner() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->dummyObjectUid,
			array('owner' => $user->getUid())
		);

		$this->fixture->checkAccess(
			'image_upload', array('showUid' => $this->dummyObjectUid)
		);
	}


	////////////////////////////////////////////////////
	// Tests concerning access to the my-objects view.
	////////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsThrowsExceptionWithPleaseLoginMessageForAnInvalidUidAndNoUserLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('my_objects', array(
			'delete' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsThrowsExceptionWithObjectDoesNotExistMessageForAnInvalidObjectToDeleteUidAndAUserLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_noResultsFound_fe_editor'
		);

		$this->fixture->checkAccess('my_objects', array(
			'delete' => $this->testingFramework->createRecord(
				REALTY_TABLE_OBJECTS, array('deleted' => 1)
			)
		));
	}

	/**
	 * @test
	 */
	public function header404IsSentWhenCheckAccessForMyObjectsThrowsExceptionWithObjectDoesNotExistMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess('my_objects', array(
				'delete' => $this->testingFramework->createRecord(
					REALTY_TABLE_OBJECTS, array('deleted' => 1)
				)
			));
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 404 Not Found',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsThrowsExceptionWithPleaseLoginMessageIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('my_objects', array('delete' => 0));
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsThrowsExceptionWithPleaseLoginMessageWhenNotLoggedInUserAttemptsToDeleteAnObject() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess(
			'my_objects', array('delete' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsThrowsExceptionWithAccessDeniedMessageWhenLoggedInUserAttemptsToDeleteAnObjectHeDoesNotOwn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_access_denied'
		);

		$this->fixture->checkAccess(
			'my_objects', array('delete' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function header403IsSentWhenCheckAccessForMyObjectsThrowsExceptionWithAccessDeniedMessage() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		try {
			$this->fixture->checkAccess(
				'my_objects', array('delete' => $this->dummyObjectUid)
			);
		} catch (tx_oelib_Exception_AccessDenied $exception) {
		}

		$this->assertEquals(
			'Status: 403 Forbidden',
			tx_oelib_headerProxyFactory::getInstance()->getHeaderProxy()->getLastAddedHeader()
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsDoesNotThrowAnExceptionIfTheObjectToDeleteExistsAndTheOwnerIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->testingFramework->changeRecord(
			REALTY_TABLE_OBJECTS,
			$this->dummyObjectUid,
			array('owner' => $user->getUid())
		);

		$this->fixture->checkAccess(
			'my_objects', array('delete' => $this->dummyObjectUid)
		);
	}

	/**
	 * @test
	 */
	public function checkAccessForMyObjectsDoesNotThrowAnExceptionIfNoObjectToDeleteIsSetAndTheUserIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->checkAccess('my_objects', array('delete' => 0));
	}


	////////////////////////////////////////////////
	// Tests concerning access to the single view.
	////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function checkAccessForSingleViewThrowsExceptionWithPleaseLoginMessageForNewObjectIfNoUserIsLoggedIn() {
		$this->setExpectedException(
			'tx_oelib_Exception_AccessDenied', 'message_please_login'
		);

		$this->fixture->checkAccess('single_view', array());
	}

	/**
	 * @test
	 */
	public function checkAccessForSingleViewDoesNotThrowAnExceptionIfTheObjectIsNewAndTheUserIsLoggedIn() {
		$user = tx_oelib_MapperRegistry::get('tx_realty_Mapper_FrontEndUser')
			->getNewGhost();
		tx_oelib_FrontEndLoginManager::getInstance()->logInUser($user);

		$this->fixture->checkAccess('single_view', array());
	}


	//////////////////////////////////////////////
	// Test concerning access to any other view.
	//////////////////////////////////////////////

	/**
	 * @test
	 */
	public function checkAccessForOtherViewDoesNotThrowAnException() {
		$this->fixture->checkAccess('other', array());
	}
}