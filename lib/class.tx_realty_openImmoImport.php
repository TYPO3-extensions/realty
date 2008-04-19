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
 * Class 'tx_realty_openImmoImport' for the 'realty' extension.
 *
 * This class imports ZIPs containing OpenImmo records.
 *
 * @package		TYPO3
 * @subpackage	tx_realty
 *
 * @author		Saskia Metzler <saskia@merlin.owl.de>
 */

require_once(t3lib_extMgm::extPath('oelib').'tx_oelib_commonConstants.php');
require_once(t3lib_extMgm::extPath('oelib').'class.tx_oelib_templatehelper.php');
require_once(t3lib_extMgm::extPath('oelib').'class.tx_oelib_configurationProxy.php');

require_once(t3lib_extMgm::extPath('realty').'lib/class.tx_realty_object.php');
require_once(t3lib_extMgm::extPath('realty').'lib/class.tx_realty_cacheManager.php');
require_once(t3lib_extMgm::extPath('realty').'lib/class.tx_realty_domDocumentConverter.php');

class tx_realty_openImmoImport {
	/** stores the complete log entry */
	private $logEntry = '';

	/** stores the complete error log */
	private $errorLog = '';

	/**
	 * Stores log information to be written to '$logEntry'. So it is possible to
	 * use only parts of the entire log e.g. to send e-mails only about the
	 * import of certain records to a certain contact address.
	 */
	private $temporaryLogEntry = '';

	/**
	 * Stores log information to be written to '$errorLog'. So it is possible to
	 * use only parts of the entire log.
	 */
	private $temporaryErrorLog = '';

	/** DOMDocments of XML files are written to this */
	private $importedXml = null;

	/** instance of tx_oelib_configuration_proxy to access the EM configuration */
	private $globalConfiguration = null;

	/** instance of 'tx_realty_object' which inserts OpenImmo records to database */
	private $realtyObject = null;

	/** the upload directory for images */
	private $uploadDirectory = '';

	/**
	 * ZIP archives which are deleted at the end of import and folders which
	 * were created during the import.
	 * Archives are added to this array if they contain exactly one XML file as
	 * this is the criterion for trying to import the XML file as an OpenImmo
	 * record.
	 */
	private $filesToDelete = array();

	/** whether the class is tested and only dummy records should be created */
	private $isTestMode = false;

	/**
	 * Constructor.
	 *
	 * @param	boolean		whether the class ist tested and therefore only
	 * 						dummy records should be inserted into the database
	 */
	public function __construct($isTestMode = false) {
		global $TYPO3_CONF_VARS, $LANG;

		$this->isTestMode = $isTestMode;
		libxml_use_internal_errors(true);
		$this->globalConfiguration = tx_oelib_configurationProxy::getInstance('realty');
		$this->setUploadDirectory(PATH_site.'uploads/tx_realty/');

		// Needed as only templating functions of tx_oelib_templatehelper are
		// usable outside FE mode.
		$this->initializeLanguage();
	}

	/**
	 * Extracts ZIP archives from an absolute path of a directory and inserts
	 * realty records to database:
	 * If the directory, specified in the EM configuration, exists and ZIP
	 * archives are found, folders are created. They are named like the ZIP
	 * archives without the suffix '.zip'. The ZIP archives are unpacked to
	 * these folders. Then for each ZIP file the following is done: The validity
	 * of the XML file found in the ZIP archive is checked by using the XSD
	 * file defined in the EM. The realty records are fetched and inserted to
	 * database. The validation failures are logged.
	 * If the records of one XML could be inserted to database, images found
	 * in the extracted ZIP archive are copied to the uploads folder.
	 * Afterwards the extraction folders are removed and a log string about the
	 * proceedings of import is passed back.
	 * Depending on the configuration in EM the log or only the errors are sent
	 * via e-mail to the contact addresses of each realty record if they are
	 * available. Else the information goes to the address configured in EM. If
	 * no e-mail address is configured, the sending of e-mails is disabled.
	 *
	 * @return	string		log entry with information about the proceedings of
	 * 						ZIP import, will not be empty, contains at least a
	 *						timestamp
	 */
	public function importFromZip() {
		global $LANG;

		$this->addToLogEntry(date('Y-m-d G:i:s').LF);

		// Aborts the import if the class for ZIP extraction is not available.
		if (!in_array('zip', get_loaded_extensions())) {
			$this->addToErrorLog('message_zip_archive_not_installed');
			$this->storeLogsAndClearTemporaryLog();

			return $this->logEntry;
		}

		$emailData = array();
		$checkedImportDirectory = $this->unifyPath(
			$this->globalConfiguration->getConfigurationValueString('importFolder')
		);
		$zipsToExtract = $this->getPathsOfZipsToExtract($checkedImportDirectory);

		$this->storeLogsAndClearTemporaryLog();

		if (!empty($zipsToExtract)) {
			foreach ($zipsToExtract as $currentZip) {
				$this->extractZip($currentZip);
				$this->loadXmlFile($currentZip);
				$emailData = array_merge(
					$emailData,
					$this->processRealtyRecordInsertion($currentZip)
				);
			}
			$this->sendEmails($this->prepareEmails($emailData));
		} else {
			$this->addToErrorLog($LANG->getLL('message_no_zips'));
		}

		$this->cleanUp($checkedImportDirectory);
		tx_realty_cacheManager::clearFrontEndCacheForRealtyPages();

		$this->storeLogsAndClearTemporaryLog();

		return $this->logEntry;
	}

	/**
	 * Processes the insertion of realty records to database. Tries to fetch the
	 * data from the currently loaded XML file. If there is data, it is
	 * checked whether the record should be inserted or set to deleted.
	 * Success and failures are logged and an array with data for e-mails about
	 * the proceedings is returned.
	 *
	 * @param	string		path of the current ZIP file, only used for log, may
	 * 						be empty
	 *
	 * @return	array		Two dimensional array of e-mail data. Each inner
	 * 						array has the elements 'recipient', 'objectNumber',
	 * 						'logEntry' and 'errorLog'. Will be empty if there
	 * 						are no records to insert.
	 */
	private function processRealtyRecordInsertion($currentZip) {
		global $LANG;

		$recordsToInsert = $this->convertDomDocumentToArray(
			$this->getImportedXml()
		);
		$emailData = array();

		$overridePid = $this->getOverridePidForZip($currentZip);

		if (!empty($recordsToInsert)) {
			foreach ($recordsToInsert as $record) {
				$this->writeToDatabase($record, $overridePid);

				$emailData[] = $this->createEmailRawDataArray(
					$this->getContactEmailFromRealtyObject(),
					$this->getObjectNumberFromRealtyObject()
				);

				$this->storeLogsAndClearTemporaryLog();
			}

			$this->copyImagesFromExtractedZip($currentZip);
		} else {
			$emailData = $this->createWrappedEmailRawDataArray(
				$this->findContactEmails($currentZip)
			);
		}

		return $emailData;
	}

	/**
	 * Finds out whether a particular PID is configured for objects in the ZIP
	 * with the given file name $fileName.
	 *
	 * @param	string		path of the ZIP to check
	 *
	 * @return	integer		PID of the system folder to store the realty record
	 * 						in or 0 if the default folder should be used
	 */
	private function getOverridePidForZip($fileName) {
		if (($fileName == '')
			|| ($this->globalConfiguration->getConfigurationValueString(
				'pidsForRealtyObjectsAndImagesByFileName'
			) == '')
		) {
			return 0;
		}

		$overridePid = 0;
		$matches = array();
		$baseName = basename($fileName, '.zip');

		if (preg_match_all(
			'/(^|;)([^\:]+)\:(\d+)/',
			$this->globalConfiguration->getConfigurationValueString(
				'pidsForRealtyObjectsAndImagesByFileName'
			),
			$matches
		)) {
			$fileNamePatterns = $matches[2];
			$pids = $matches[3];

			foreach ($fileNamePatterns as $index => $pattern) {
				if (preg_match('/'.$pattern.'/', $baseName)) {
					$overridePid = $pids[$index];
					break;
				}
			}
		}

		return $overridePid;
	}

	/**
	 * Tries to write an imported record to the database and checks the contact
	 * e-mail address. If the address is invalid, it is replaced by the default
	 * address as configured in EM.
	 * Note: There is no check for the validity of the default address. If the
	 * DOMDocument cannot be loaded, or if required fields are missing, the
	 * record will not be inserted to the database. Success and failures are
	 * logged.
	 *
	 * @param	array		record to insert, may be empty
	 * @param	integer		PID for new records (omit this parameter to use
	 * 						the PID set in the global configuration)
	 */
	protected function writeToDatabase(array $realtyRecord, $overridePid = 0) {
 		global $LANG;

		$this->loadRealtyObject($realtyRecord);
		$this->ensureContactEmail();

		$pleaseValidateMessage = '';
		if ($this->globalConfiguration->
			getConfigurationValueString('openImmoSchema') != ''
		) {
			$pleaseValidateMessage = $LANG->getLL('message_please_validate');
		}

		$errorMessage = $this->realtyObject->writeToDatabase($overridePid);

		switch ($errorMessage) {
			case '':
			$this->addToLogEntry(
				$LANG->getLL('message_written_to_database').LF
			);
			break;
		case 'message_deleted_flag_set':
			// A set deleted flag is no real error, so is not stored in the
			// error log.
			$this->addToLogEntry($LANG->getLL($errorMessage).LF);
			break;
		case 'message_fields_required':
			$this->addToErrorLog($LANG->getLL($errorMessage).': '
				.implode(', ', $this->realtyObject->checkForRequiredFields())
				.'. '.$pleaseValidateMessage.LF
			);
			break;
		default:
			$this->addToErrorLog(
				$LANG->getLL($errorMessage).' '.$pleaseValidateMessage.LF
			);
			break;
		}
	}

	/**
	 * Logs information about the proceedings of the import.
	 * This function is to be used for positive information only. Errors should
	 * get logged through 'addToErrorLog()' instead.
	 *
	 * @param	string		message to log, may be empty
	 */
	private function addToLogEntry($logFraction) {
		$this->temporaryLogEntry .= $logFraction.LF;
	}

	/**
	 * Logs errors to a special error log and also provides 'addToLogEntry()'
	 * with the given string.
	 *
	 * @param	string		error message to log, may be empty
	 */
	private function addToErrorLog($errorMessage) {
		$this->temporaryErrorLog .= $errorMessage.LF;
		$this->addToLogEntry($errorMessage);
	}

	/**
	 * Stores available log messages to be returned at the end of the import.
	 * This function is needed to use only parts of the log.
	 */
	private function storeLogsAndClearTemporaryLog() {
		$this->errorLog .= $this->temporaryErrorLog;
		$this->temporaryErrorLog = '';

		$this->logEntry .= $this->temporaryLogEntry;
		$this->temporaryLogEntry = '';
	}

	/**
	 * Sets the path for the upload directory. This path must be valid and
	 * absolute and may end with a trailing slash.
	 *
	 * @param	string		absolute path of the upload directory, must not be
	 * 						empty
	 */
	protected function setUploadDirectory($path) {
		$this->uploadDirectory = $this->unifyPath($path);
	}

	/**
	 * Checks if the configuration in the EM enables sending errors only.
	 *
	 * @return	boolean		true if 'onlyErrors' is enabled, false otherwise
	 */
	private function isErrorLogOnlyEnabled() {
		return $this->globalConfiguration->getConfigurationValueBoolean(
			'onlyErrors'
		);
	}

	/**
	 * Returns the default e-mail address, configured in the EM.
	 *
	 * @return	string		default e-mail address, may be empty
	 */
	private function getDefaultEmailAddress() {
		return $this->globalConfiguration->getConfigurationValueString(
			'emailAddress'
		);
	}

	/**
	 * Checks whether contact persons of each record should receive e-mails
	 * about the import of their records.
	 *
	 * @return	boolean		true if contact persons should receive e-mails,
	 * 						false otherwise
	 */
	private function isNotifyContactPersonsEnabled() {
		return $this->globalConfiguration->getConfigurationValueBoolean(
			'notifyContactPersons'
		);
	}

	/**
	 * Stores all information for an e-mail to an array with the keys
	 * 'recipient', 'objectNumber', 'logEntry' and 'errorLog'.
	 *
	 * @param	string		e-mail address, may be empty
	 * @param	string		object number, may be empty
	 *
	 * @return	array		e-mail raw data, contains the elements 'recipient',
	 * 						'objectNumber', 'logEntry' and 'errorLog', will not
	 * 						be empty
	 */
	private function createEmailRawDataArray($email, $objectNumber) {
		return array(
			'recipient' => $email,
			'objectNumber' => $objectNumber,
			'logEntry' => $this->temporaryLogEntry,
			'errorLog' => $this->temporaryErrorLog
		);
	}

	/**
	 * Stores all information for an e-mail to an array with the keys
	 * 'recipient', 'objectNumber', 'logEntry' and 'errorLog'.
	 * This function is used, when the e-mail addresses were not fetched by the
	 * regular way. So the element 'objectNumber' will stay empty and log entry
	 * and error log will be the same for each recipient, as they are all
	 * fetched from the same not insertable XML file.
	 *
	 * @param	array		e-mail addresses, may be empty
	 *
	 * @return	array		array with the elements 'recipient', 'objectNumber',
	 * 						'logEntry' and 'errorLog' as values of an outer
	 * 						array, will not be empty
	 */
	private function createWrappedEmailRawDataArray(array $emails) {
		$collectedRawData = array();

		foreach ($emails as $address) {
			$collectedRawData[] = $this->createEmailRawDataArray($address, '');
		}

		return $collectedRawData;
	}

	/**
	 * Prepares the sending of e-mails. Resorts $emailData. Sets the value for
	 * 'recipient' to the default e-mail address wherever there is no e-mail
	 * address given. Sets the value for 'objectNumber' to '------' if is not
	 * set. Purges empty records, so no empty messages are sent.
	 * If 'onlyErrors' is enabled in EM, the messages will just contain error
	 * messages and no information about success.
	 *
	 * @param	array		Two dimensional array of e-mail data. Each inner
	 * 						array has the elements 'recipient', 'objectNumber',
	 * 						'logEntry' and 'errorLog'. May be empty.
	 *
	 * @return	array		Three dimensional array with e-mail addresses as
	 * 						keys of the outer array. Innermost there is an array
	 *						with only one element: Object number as key and the
	 *						corresponding log information as value. This array
	 *						is wrapped by a numeric array as object numbers are
	 *						not necessarily unique. Empty if the input array is
	 *						empty or invalid.
	 */
	protected function prepareEmails(array $emailData) {
		if (!$this->validateEmailDataArray($emailData)) {
			return array();
		}

		$result = array();
		$emailDataToPrepare = $emailData;
		if ($this->isErrorLogOnlyEnabled()) {
			$log = 'errorLog';
		} else {
			$log = 'logEntry';
		}

		foreach ($emailDataToPrepare as $recordNumber => $record) {
			if (!$this->isNotifyContactPersonsEnabled()
				|| ($record['recipient'] == '')
			) {
				$record['recipient'] = $this->getDefaultEmailAddress();
			}

			if ($record['objectNumber'] == '') {
				$record['objectNumber'] = '------';
			}

			$result[$record['recipient']][] = array(
				$record['objectNumber'] => $record[$log]
			);
		}

		$this->purgeRecordsWithoutLogMessages($result);
		$this->purgeRecipientsForEmptyMessages($result);

		return $result;
	}

	/**
	 * Validates an e-mail data array which is used to prepare e-mails. Returns
	 * true if the structure is correct, false otherwise.
	 * The structure is correct, if there are arrays as values for each numeric
	 * key and if those arrays contain the elements 'recipient', 'objectNumber',
	 * 'logEntry' and 'errorLog' as keys.
	 *
	 * @param	array		e-mail data array to validate with arrays as values
	 * 						for each numeric key and if those arrays contain the
	 * 	 					elements 'recipient', 'objectNumber', 'logEntry' and
	 * 						 'errorLog' as keys, may be empty
	 *
	 * @return	boolean		true if the structure of the array is valid, false
	 * 						otherwise
	 */
	private function validateEmailDataArray(array $emailData) {
		$isValidDataArray = true;
		$requiredKeys = array(
			'recipient',
			'objectNumber',
			'logEntry',
			'errorLog'
		);

		foreach ($emailData as $key => $dataArray) {
			if (!is_array($dataArray)) {
				$isValidDataArray = false;
				break;
			} else {
				$numberOfValidArrays = count(
					array_intersect(array_keys($dataArray),	$requiredKeys)
				);

				if ($numberOfValidArrays != 4) {
					$isValidDataArray = false;
					break;
				}
			}
		}

		return $isValidDataArray;
	}

	/**
	 * Deletes object numbers from $emailData if there is no message to report.
	 * Messages could only be empty if 'onlyErrors' is activated in the EM
	 * configuration.
	 *
	 * @param	array		prepared e-mail data, must not be empty
	 */
	private function purgeRecordsWithoutLogMessages(array &$emailData) {
		foreach ($emailData as $recipient => $data) {
			foreach ($data as $key => $emailContent) {
				if (implode(array_values($emailContent)) == '') {
					unset($emailData[$recipient][$key]);
				}
			}
		}
	}

	/**
	 * Deletes e-mail recipients from a $emailData if are no records to report
	 * about.
	 *
	 * @param	array		prepared e-mail data, must not be empty
	 */
	private function purgeRecipientsForEmptyMessages(array &$emailData) {
		foreach ($emailData as $recipient => $data) {
			if (empty($data)) {
				unset($emailData[$recipient]);
			}
		}
	}

	/**
	 * Fills a template file, which has already been included, with data for one
	 * e-mail.
	 *
	 * @param		array		Wrapped message content for one e-mail: Each
	 *			 				object number-message pair is wrapped by a
	 *							numeric key as object numbers are not necessarily
	 *							unique. Must not be empty
	 *
	 * @return		string		e-mail body
	 */
	private function fillEmailTemplate($recordsForOneEmail) {
		global $LANG;

		$templateHelper = t3lib_div::makeInstance('tx_oelib_templatehelper');
		$templateHelper->init(
			array(
				'templateFile' =>
					$this->globalConfiguration->getConfigurationValueString(
						'emailTemplate'
					)
			)
		);
		$templateHelper->getTemplateCode();

		$contentItem = array();

		// collects data for the subpart 'CONTENT_ITEM'
		$templateHelper->setMarkerContent(
			'label_object_number',
			$LANG->getLL('label_object_number')
		);
		foreach ($recordsForOneEmail as $recordNumber => $record) {
			// $record is an array of the object number associated with the log
			$templateHelper->setMarkerContent(
				'object_number',
				key($record)
			);
			$templateHelper->setMarkerContent(
				'log',
				implode($record)
			);
			$contentItem[] = $templateHelper->getSubpart('CONTENT_ITEM');
		}

		// fills the subpart 'EMAIL_BODY'
		$templateHelper->setMarkerContent(
			'header',
			$LANG->getLL('label_introduction')
		);
		$templateHelper->setSubpartContent(
			'CONTENT_ITEM',
			implode(LF, $contentItem)
		);
		$templateHelper->setMarkerContent(
			'footer',
			$LANG->getLL('label_explanation')
		);

		return $templateHelper->getSubpart('EMAIL_BODY');
	}

	/**
	 * Sends an e-mail with log information to each address given as a key of
	 * $addressesAndMessages.
	 * If there is no default address configured in the EM, no messages will be
	 * sent at all.
	 *
	 * @param	array		Three dimensional array with e-mail addresses as
	 * 						keys of the outer array. Innermost there is an array
	 *						with only one element: Object number as key and the
	 *						corresponding log information as value. This array
	 *						is wrapped by a numeric array as object numbers are
	 *						not necessarily unique. Must not be empty.
	 */
	private function sendEmails(array $addressesAndMessages) {
		global $LANG;

		if ($this->getDefaultEmailAddress() == '') {
			return;
		}

		foreach ($addressesAndMessages as $address => $content) {
			t3lib_div::plainMailEncoded(
				$address,
				'OpenImmo import',
				$this->fillEmailTemplate($content)
			);
		}

		if (!empty($addressesAndMessages)) {
			$this->addToLogEntry(
				$LANG->getLL('message_log_sent_to').': '
				.implode(', ', array_keys($addressesAndMessages))
			);
		}
	}

	/**
	 * Ensures a contact e-mail address for the current realty record. Checks
	 * whether there is a valid contact e-mail for the current record. Inserts
	 * the default address configured in EM if 'contact_email' if the current
	 * record's contact e-mail is empty or invalid.
	 */
	protected function ensureContactEmail() {
		$address = $this->getContactEmailFromRealtyObject();
		$isValid = ($address != '') && (t3lib_div::validEmail($address));

		if (!$isValid) {
			$this->setContactEmailOfRealtyObject($this->getDefaultEmailAddress());
		}
	}

	/**
	 * Finds out the contact e-mail addresses independently, no matter whether a
	 * record is loaded or not e. g. due to failed validation.
	 *
	 * @param	string		Path of ZIP archive which contains the XML file with
	 * 						the e-mail address to fetch. This archive must have
	 * 						been extracted before.
	 *
	 * @return	array		contact e-mails on success, an empty array otherwise
	 */
	protected function findContactEmails($pathOfZip) {
		$xmlPath = $this->getPathForXml($pathOfZip, true);
		if ($xmlPath == '') {
			return array();
		}

		$domDocument = DOMDocument::load($xmlPath);
		if (!$domDocument) {
			return array();
		}

		$recordsArray = $this->convertDomDocumentToArray($domDocument);

		$emails = array();
		foreach ($recordsArray as $record) {
			$emails[] = $record['contact_email'];
		}

		return $emails;
	}

	/**
	 * Checks the correct punctuation of a path to a directory. Adds a slash if
	 * missing and strips whitespaces.
	 *
	 * @param	string		path to be checked, must not be empty
	 *
	 * @return	string		checked path, possibly modified
	 */
	protected function unifyPath($directory) {
		$checkedPath = trim($directory);
		if (strpos($checkedPath, '/', strlen($checkedPath) - 1) === false) {
			$checkedPath .= '/';
		}
		return $checkedPath;
	}

	/**
	 * Gets an array of the paths of all ZIP archives in the import folder.
	 *
	 * @param	string		absolute path of the directory which contains the
	 * 						ZIPs, must not be empty
	 *
	 * @return	array		absolute paths of ZIPs in the import folder,
	 * 						might be empty
	 */
	protected function getPathsOfZipsToExtract($importDirectory) {
		$result = array();
		if (is_dir($importDirectory)) {
			$result = glob($importDirectory.'*.zip');
		}

		return $result;
	}

	/**
	 * Extracts each ZIP archive into a directory in the import folder which is
	 * named like the ZIP archive without the suffix '.zip'.
	 * Logs success and failures.
	 *
	 * @param	string		path to the ZIP archive to extract, must not be
	 * 						empty
	 */
	public function extractZip($zipToExtract) {
		global $LANG;

		if (!file_exists($zipToExtract)) {
			return;
		}

		$zip = new ZipArchive();
		if ($zip->open($zipToExtract)) {
			$extractionDirectory = $this->createExtractionFolder($zipToExtract);
			if ($extractionDirectory != '') {
				$zip->extractTo($extractionDirectory);
				$this->addToLogEntry(
					$zipToExtract.': '.$LANG->getLL(
						'message_extracted_successfully'
					)
				);
			}
			$zip->close();
		} else {
			$this->addToErrorLog(
				$zipToExtract.': '.$LANG->getLL('message_extraction_failed'));
		}
	}

	/**
	 * Gets a name for a folder according to the ZIP archive to extract to it.
	 *
	 * @param	string		path of a ZIP archive, must not be empty
	 *
	 * @return	string		path for a folder named like the ZIP archive, empty
	 * 						if the passed string is empty
	 */
	protected function getNameForExtractionFolder($pathOfZip) {
		return str_replace('.zip', '/', $pathOfZip);
	}

	/**
	 * Creates a folder to extract a ZIP archive to.
	 *
	 * @param	string		path of a ZIP archive to get the folders name, must
	 * 						not be empty
	 *
	 * @return	string		path for folder named like the ZIP archive without
	 * 						the suffix '.zip', may be empty if the provided ZIP
	 * 						file does not exists or if the folder to create
	 * 						already exists
	 */
	public function createExtractionFolder($pathOfZip) {
		global $LANG;

		if (!file_exists($pathOfZip)) {
			return '';
		}

		$folderForZipExtraction = $this->getNameForExtractionFolder($pathOfZip);
		if (!is_dir($folderForZipExtraction)) {
			mkdir($folderForZipExtraction);
			$this->filesToDelete[] = $folderForZipExtraction;
		} else {
			$this->addToErrorLog(
				$folderForZipExtraction.': '.$LANG->getLL('message_surplus_folder')
			);
			$folderForZipExtraction = '';
		}

		return $folderForZipExtraction;
	}

	/**
	 * Finds an XML file in the folder named like $pathOfZip without the suffix
	 * '.zip' and returns its path. The ZIP archive must have been extracted
	 * before. In case no or several XML files are found, an empty string is
	 * returned and the error is logged.
	 *
	 * @param	string		absolute path where to find the ZIP archive which
	 * 						includes an XML file, must not be empty
	 * @param	boolean		whether this function logs occurring errors or not
	 *
	 * @return	string		absolute path of the XML file, empty string on error
	 */
	protected function getPathForXml($pathOfZip, $processSilently = false) {
		global $LANG;

		$result = '';

		$errorMessage = '';
		$folderWithXml = $this->getNameForExtractionFolder($pathOfZip);
		$pathOfXml = array();

		if (is_dir($folderWithXml)) {
			$pathOfXml = glob($folderWithXml.'*.xml');

			if (count($pathOfXml) == 1) {
				$result = implode($pathOfXml);
				$this->filesToDelete[] = $pathOfZip;
			} else {
				if (count($pathOfXml) > 1) {
					$errorMessage = 'message_too_many_xml';
				} else {
					$errorMessage = 'message_no_xml';
				}
			}
		} else {
			$errorMessage = 'message_invalid_xml_path';
		}

		// logs an error message if necessary
		if ($errorMessage != '' && !$processSilently
			&& in_array($folderWithXml, $this->filesToDelete)
		) {
			$this->addToErrorLog(
				basename($pathOfZip).': '.$LANG->getLL($errorMessage)
			);
		}

		return $result;
	}

	/**
	 * Loads and validates an XML file from a ZIP archive as a DOMDocument which
	 * is stored in an array.
	 * The ZIP archive must have been extracted to a folder named like the ZIP
	 * without the suffix '.zip' before.
	 *
	 * @param	string		absolute path where to find the ZIP archive which
	 * 						includes an XML file, must not be empty
	 */
	protected function loadXmlFile($pathOfZip) {
		$xmlPath = $this->getPathForXml($pathOfZip);

		if ($xmlPath == '') {
			return;
		}

		$this->importedXml = DOMDocument::load($xmlPath);
		$this->validateXml();
	}

	/**
	 * Returns the current content of the currently loaded XML file as a
	 * DOMDocument.
	 *
	 * @return	DOMDocument		loaded XML file, may be null if no document was
	 * 							loaded e.g. due to validation errors
	 */
	protected function getImportedXml() {
		return $this->importedXml;
	}

	/**
	 * Initializes the global variable $LANG needed for localized strings. Uses
	 * the EM configuration to set the language.
	 */
	private function initializeLanguage() {
		global $LANG;

		if (!is_object($LANG)) {
			$LANG = t3lib_div::makeInstance('language');
		}

		$cliLanguage = $this->globalConfiguration->getConfigurationValueString(
			'cliLanguage'
		);
		if ($cliLanguage == '') {
			$LANG->init('default');
		} else {
			$LANG->init($cliLanguage);
		}

		$LANG->includeLLFile('EXT:realty/lib/locallang.xml');
	}

	/**
	 * Validates an XML file and writes the validation result to the log.
	 * The XML file must have been loaded before. The schema to validate
	 * against is taken from the path in '$this- >schemaFile'. If this path is
	 * empty or invalid, validation is considered to be successful and the
	 * absence of a schema file is logged.
	 */
	private function validateXml() {
		global $LANG;

		$validationResult = '';
		$schemaFile = $this->globalConfiguration->getConfigurationValueString(
			'openImmoSchema'
		);

		if ($schemaFile == '') {
			$validationResult = 'message_no_schema_file';
		} elseif (!file_exists($schemaFile)) {
			$validationResult = 'message_invalid_schema_file_path';
		} elseif (!$this->getImportedXml()) {
			$validationResult = 'message_validation_impossible';
		} elseif (!$this->importedXml->schemaValidate($schemaFile)) {
			$errors = libxml_get_errors();
			foreach ($errors as $error) {
				$validationResult .= $LANG->getLL('message_line').' '.
					$error->line.': '.$error->message;
			}
		}

		$this->logValidationResult($validationResult);
	}

	/**
	 * Logs the validation result of the XML file.
	 *
	 * @param	string		result of the validation, can be either one of the
	 * 						locallang keys 'message_no_schema_file',
	 * 						'message_invalid_schema_file_path' or
	 * 						'message_validation_impossible' or an already
	 * 						localizeded error message or an empty string if
	 * 						success should be logged
	 */
	private function logValidationResult($validationResult) {
		global $LANG;

		switch ($validationResult) {
			case '':
				$this->addToLogEntry(
					$LANG->getLL('message_successful_validation').LF
				);
				break;
			case 'message_no_schema_file':
				$this->addToLogEntry(
					$LANG->getLL($validationResult).' '
						.$LANG->getLL('message_import_without_validation')
				);
				break;
			case 'message_invalid_schema_file_path':
				$this->addToErrorLog(
					$LANG->getLL($validationResult).' '
						.$LANG->getLL('message_import_without_validation')
				);
				break;
			case 'message_validation_impossible':
				$this->addToErrorLog($LANG->getLL($validationResult));
				break;
			default:
				$this->addToErrorLog($validationResult);
				break;
		}
	}

	/**
	 * Copies images for OpenImmo records to the local upload folder.
	 *
	 * @param	string		path of the extracted ZIP archive, must not be empty
	 */
	public function copyImagesFromExtractedZip($pathOfZip) {
		$folderWithImages = $this->getNameForExtractionFolder($pathOfZip);

		foreach (array('jpg', 'JPG', 'jpeg', 'JPEG', 'png', 'PNG', 'gif', 'GIF')
			as $pattern
		) {
			$images = glob($folderWithImages.'*.'.$pattern);
			foreach ($images as $image) {
				copy(
					$image,
					$this->uploadDirectory.basename($image)
				);
			}
		}
	}

	/**
	 * Removes the ZIP archives which have been imported and the folders which
	 * have been created to extract the ZIP archives.
	 * Logs which ZIP archives have been deleted.
	 *
	 * @param	string		absolute path of the folder which contains the ZIP
	 * 						archives, must not be empty
	 */
	public function cleanUp($importDirectory) {
		global $LANG;

		if (!is_dir($importDirectory)) {
			return;
		}

		$removedFiles = array();
		$deleteImportedZips = $this->globalConfiguration->getConfigurationValueBoolean(
			'deleteZipsAfterImport'
		);

		foreach ($this->getPathsOfZipsToExtract($importDirectory) as $currentPath) {
			if ($deleteImportedZips) {
				$removedZipArchive = $this->deleteFile($currentPath);
				if ($removedZipArchive != '') {
					$removedFiles[] = $removedZipArchive;
				}
			}
			$this->deleteFile($this->getNameForExtractionFolder($currentPath));
		}

		if (!empty($removedFiles)) {
			$this->addToLogEntry(
				$LANG->getLL('message_files_removed')
				.': '.implode(', ', $removedFiles)
			);
		}
	}

	/**
	 * Removes a file if it occurs in the list of files for which deletion is
	 * allowed.
	 *
	 * @param	string		path of the file to delete, must not be empty
	 *
	 * @return	string		basename of the deleted file or an empty string if
	 * 						no file was deleted
	 */
	private function deleteFile($pathOfFile) {
		$removedFile = '';
		if (in_array($pathOfFile, $this->filesToDelete)) {
			exec('rm -rf '.$pathOfFile);
			$removedFile = basename($pathOfFile);
		}

		return $removedFile;
	}

	/**
	 * Converts a DOMDocument to an array.
	 *
	 * @param	DOMDocument		which contains realty records, can be null
	 *
	 * @return	array		realty records in an array, may be empty if the data
	 * 						was not convertible
	 */
	protected function convertDomDocumentToArray($realtyRecords) {
		if (!$realtyRecords) {
			return;
		}

		$domDocumentConverter = t3lib_div::makeInstance(
			'tx_realty_domDocumentConverter'
		);

		return $domDocumentConverter->getConvertedData($realtyRecords);
	}

	/**
	 * Loads a realty object.
	 * The data can either be a database result row or an array which has
	 * database column names as keys (may be empty). The data can also be a UID
	 * of an existent realty object to load from the database. If the data is of
	 * an invalid type the realty object stays empty.
	 *
	 * @param	mixed		data for the realty object as an array, a database
	 * 						result row, or UID of an existing record
	 */
	protected function loadRealtyObject($data) {
		$this->realtyObject = new tx_realty_object($this->isTestMode);
		$this->realtyObject->loadRealtyObject($data);
	}

	/**
	 * Returns the object number of a realty object if it is set.
	 *
	 * @return	string		object number, may be empty if no object number
	 * 						was set or if the realty object is not initialized
	 */
	private function getObjectNumberFromRealtyObject() {
		if (!is_object($this->realtyObject)) {
			return '';
		}

		return $this->realtyObject->getProperty('object_number');
	}

	/**
	 * Returns the contact e-mail address of a realty object if it is set.
	 *
	 * @return	string		contact e-mail address, may be empty if no e-mail
	 * 						address was found or if the realty object is not
	 * 						initialized
	 */
	protected function getContactEmailFromRealtyObject() {
		if (!is_object($this->realtyObject)) {
			return '';
		}

		return $this->realtyObject->getProperty('contact_email');
	}

	/**
	 * Sets the contact e-mail address of a realty object.
	 *
	 * @param	string		contact e-mail address, must not be empty
	 */
	private function setContactEmailOfRealtyObject($address) {
		if (!is_object($this->realtyObject)) {
			return;
		}

		$this->realtyObject->setProperty('contact_email', $address);
	}

	/**
	 * Gets the required fields of a realty object.
	 * This function is needed for unit testing only.
	 *
	 * @return	array		required fields, may be empty if no fields are
	 * 						required or if the realty object is not initialized
	 */
	protected function getRequiredFields() {
		if (!is_object($this->realtyObject)) {
			return array();
		}

		return $this->realtyObject->getRequiredFields();
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_openImmoImport.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_openImmoImport.php']);
}
?>