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
class tx_realty_FrontEnd_ImageUploadTest extends tx_phpunit_testcase {
	/**
	 * @var tx_realty_frontEndImageUpload
	 */
	private $fixture;

	/**
	 * @var tx_oelib_testingFramework
	 */
	private $testingFramework;

	/**
	 * UID of the dummy object
	 *
	 * @var integer
	 */
	private $dummyObjectUid = 0;

	/**
	 * title for the first dummy image
	 *
	 * @var string
	 */
	private static $firstImageTitle = 'first test image';

	/**
	 * file name for the first dummy image
	 *
	 * @var string
	 */
	private static $firstImageFileName = 'first.jpg';

	/**
	 * title for the second dummy image
	 *
	 * @var string
	 */
	private static $secondImageTitle = 'second test image';

	/**
	 * file name for the second dummy image
	 *
	 * @var string
	 */
	private static $secondImageFileName = 'second.jpg';

	/**
	 * backup of $GLOBALS['TYPO3_CONF_VARS']['GFX']
	 *
	 * @var array
	 */
	private $graphicsConfigurationBackup;

	public function setUp() {
		$this->graphicsConfigurationBackup = $GLOBALS['TYPO3_CONF_VARS']['GFX'];
		$GLOBALS['TYPO3_CONF_VARS']['GFX']['imagefile_ext']
			= 'gif,jpg,jpeg,tif,tiff,bmp,pcx,tga,png,pdf,ai';

		$this->testingFramework = new tx_oelib_testingFramework('tx_realty');
		$this->testingFramework->createFakeFrontEnd();

		tx_oelib_MapperRegistry::getInstance()
			->activateTestingMode($this->testingFramework);

		$this->createDummyRecords();

		$this->fixture = new tx_realty_frontEndImageUpload (
			array('feEditorTemplateFile'
				=> 'EXT:realty/pi1/tx_realty_frontEndEditor.html'
			),
			$GLOBALS['TSFE']->cObj,
			0,
			'',
			TRUE
		);
		$this->fixture->setRealtyObjectUid($this->dummyObjectUid);
	}

	public function tearDown() {
		$this->testingFramework->cleanUp();

		unset($this->fixture, $this->testingFramework);

		$GLOBALS['TYPO3_CONF_VARS']['GFX'] = $this->graphicsConfigurationBackup;
	}


	///////////////////////
	// Utility functions.
	///////////////////////

	/**
	 * Creates dummy records in the DB and logs in a front-end user.
	 *
	 * @return void
	 */
	private function createDummyRecords() {
		$userUid = $this->testingFramework->createFrontEndUser();

		$this->dummyObjectUid = $this->testingFramework->createRecord(
			'tx_realty_objects', array('owner' => $userUid)
		);
		$this->createImageRecords();
	}

	/**
	 * Creates dummy image records in the DB.
	 *
	 * @return void
	 */
	private function createImageRecords() {
		$realtyObject = new tx_realty_Model_RealtyObject(TRUE);
		$realtyObject->loadRealtyObject($this->dummyObjectUid);

		$realtyObject->addImageRecord(self::$firstImageTitle, self::$firstImageFileName);
		$realtyObject->addImageRecord(self::$secondImageTitle, self::$secondImageFileName);
		$realtyObject->writeToDatabase();

		$this->testingFramework->markTableAsDirty(REALTY_TABLE_IMAGES);
	}


	////////////////////////////////////////////////////
	// Tests for the functions called in the XML form.
	////////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function processImageUploadWritesNewImageRecordForCurrentObjectToTheDatabase() {
		$this->fixture->processImageUpload(
			array(
				'caption' => 'test image',
				'image' => array('name' => 'image.jpg')
			)
		);

		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				REALTY_TABLE_IMAGES,
				'image = "image.jpg" AND caption = "test image"'
			)
		);
	}

	/**
	 * @test
	 */
	public function processImageUploadStoresCurrentObjectUidAsParentForTheImage() {
		$this->fixture->processImageUpload(
			array(
				'caption' => 'test image',
				'image' => array('name' => 'image.jpg')
			)
		);

		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				REALTY_TABLE_IMAGES,
				'object=' . $this->dummyObjectUid .
					' AND caption="test image" AND image="image.jpg"'
			)
		);
	}

	/**
	 * @test
	 */
	public function processImageUploadDoesNotInsertAnImageIfOnlyACaptionProvided() {
		$this->fixture->processImageUpload(
			array(
				'caption' => 'test image',
				'image' => array('name' => '')
			)
		);

		$this->assertEquals(
			0,
			$this->testingFramework->countRecords(
				REALTY_TABLE_IMAGES,
				'object=' . $this->dummyObjectUid .
					' AND caption="test image"'
			)
		);
	}

	/**
	 * @test
	 */
	public function processImageUploadDeletesImageRecordForCurrentObjectFromTheDatabase() {
		$this->fixture->processImageUpload(
			array('imagesToDelete' => 'attached_image_0,')
		);

		$this->assertEquals(
			1,
			$this->testingFramework->countRecords(
				REALTY_TABLE_IMAGES,
				'1=1' . tx_oelib_db::enableFields(REALTY_TABLE_IMAGES)
			)
		);
	}

	/**
	 * @test
	 */
	public function processImageUploadDeletesImageTwoRecordsForCurrentObjectFromTheDatabase() {
		$this->fixture->processImageUpload(
			array('imagesToDelete' => 'attached_image_0,attached_image_1,')
		);

		$this->assertEquals(
			0,
			$this->testingFramework->countRecords(
				REALTY_TABLE_IMAGES,
				'1=1' . tx_oelib_db::enableFields(REALTY_TABLE_IMAGES)
			)
		);
	}


	/////////////////////////////////
	// Tests concerning validation.
	/////////////////////////////////

	/**
	 * @test
	 */
	public function checkFileForNoImageReturnsTrue() {
		$this->assertTrue(
			$this->fixture->checkFile(array('value' => array('name')))
		);
	}

	/**
	 * @test
	 */
	public function checkFileForGifFileReturnsTrue() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertTrue(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.gif', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForPngFileReturnsTrue() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertTrue(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.png', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForJpgFileReturnsTrue() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertTrue(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.jpg', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForJpegFileReturnsTrue() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertTrue(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.jpeg', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForPdfFileReturnsFalse() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertFalse(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.pdf', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForPsFileReturnsFalse() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertFalse(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.ps', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileWithoutCaptionReturnsFalse() {
		$this->assertFalse(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.jpg', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForTooLargeImageReturnsFalse() {
		$tooLarge = ($GLOBALS['TYPO3_CONF_VARS']['BE']['maxFileSize'] * 1024) + 1;
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertFalse(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.jpg', 'size' => $tooLarge))
			)
		);
	}

	/**
	 * @test
	 */
	public function checkFileForInvalidFooExtensionReturnsFalse() {
		$this->fixture->setFakedFormValue('caption', 'foo');

		$this->assertFalse(
			$this->fixture->checkFile(
				array('value' => array('name' => 'foo.foo', 'size' => 1))
			)
		);
	}

	/**
	 * @test
	 */
	public function getImageUploadErrorMessageForEmptyCaption() {
		$this->fixture->checkFile(
			array('value' => array('name' => 'foo.jpg', 'size' => 1))
		);

		$this->assertEquals(
			$this->fixture->translate('message_empty_caption'),
			$this->fixture->getImageUploadErrorMessage()
		);
	}

	/**
	 * @test
	 */
	public function getImageUploadErrorMessageForInvalidExtension() {
		$this->fixture->setFakedFormValue('caption', 'foo');
		$this->fixture->checkFile(
			array('value' => array('name' => 'foo.foo', 'size' => 1))
		);

		$this->assertEquals(
			$this->fixture->translate('message_invalid_type'),
			$this->fixture->getImageUploadErrorMessage()
		);
	}

	/**
	 * @test
	 */
	public function getImageUploadErrorMessageForTooLargeImage() {
		$tooLarge = ($GLOBALS['TYPO3_CONF_VARS']['BE']['maxFileSize'] * 1024) + 1;
		$this->fixture->setFakedFormValue('caption', 'foo');
		$this->fixture->checkFile(
			array('value' => array('name' => 'foo.jpg', 'size' => $tooLarge))
		);

		$this->assertEquals(
			$this->fixture->translate('message_image_too_large'),
			$this->fixture->getImageUploadErrorMessage()
		);
	}


	//////////////////////////////////////////////////
	// Tests concerning functions used after submit.
	//////////////////////////////////////////////////

	/**
	 * @test
	 */
	public function getRedirectUrlReturnsUrlWithCurrentPageIdAsTargetPageIfProceedUploadWasTrue() {
		$pageUid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue('feEditorRedirectPid', $pageUid);
		$this->fixture->setFakedFormValue('proceed_image_upload', 1);

		$this->assertContains(
			'?id=' . $GLOBALS['TSFE']->id,
			$this->fixture->getRedirectUrl()
		);
	}

	/**
	 * @test
	 */
	public function getRedirectUrlReturnsUrlShowUidInUrlIfProceedUploadWasTrue() {
		$pageUid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue('feEditorRedirectPid', $pageUid);
		$this->fixture->setFakedFormValue('proceed_image_upload', 1);

		$this->assertContains(
			'tx_realty_pi1[showUid]',
			$this->fixture->getRedirectUrl()
		);
	}

	/**
	 * @test
	 */
	public function getRedirectUrlReturnsUrlWithCurrentConfiguredRedirectPageIdAsTargetPageIfProceedUploadWasFalse() {
		$pageUid = $this->testingFramework->createFrontEndPage();
		$this->fixture->setConfigurationValue('feEditorRedirectPid', $pageUid);
		$this->fixture->setFakedFormValue('proceed_image_upload', 0);

		$this->assertContains(
			'?id=' . $pageUid,
			$this->fixture->getRedirectUrl()
		);
	}
}