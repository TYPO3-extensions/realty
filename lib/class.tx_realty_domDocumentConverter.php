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
 * Class 'tx_realty_domDocumentConverter' for the 'realty' extension.
 * It converts DOMDocuments of OpenImmo data to arrays which have the columns of
 * the database table 'tx_realty_objects' as keys.
 *
 * @package		TYPO3
 * @subpackage	tx_realty
 *
 * @author		Saskia Metzler <saskia@merlin.owl.de>
 */

require_once(t3lib_extMgm::extPath('oelib') . 'class.tx_oelib_configurationProxy.php');

require_once(t3lib_extMgm::extPath('realty') . 'lib/tx_realty_constants.php');
require_once(t3lib_extMgm::extPath('realty') . 'lib/class.tx_realty_translator.php');

class tx_realty_domDocumentConverter {
	/**
	 * Associates database column names with their correspondings used in
	 * OpenImmo records.
	 * In OpenImmo, nested tags are used to describe values. The beginning of
	 * these tags is the same for all columns in this array:
	 * '<openimmo><anbieter><immobilie>...</immobilie></anbieter></openimmo>'.
	 * The last tags, each property's name and the category where to find this
	 * name differ.
	 * The database column names are the keys of the outer array, their values
	 * are arrays which have the category as key and the property as value.
	 */
	private $propertyArray = array(
		// OpenImmo tag for 'starttime' could possibly be 'beginn_bietzeit'.
		'starttime' => array('bieterverfahren' => 'beginn_angebotsphase'),
		'endtime' => array('bieterverfahren' => 'ende_bietzeit'),
		'object_number' => array('verwaltung_techn' => 'objektnr_extern'),
		'title' => array('freitexte' => 'objekttitel'),
		'street' => array('geo' => 'strasse'),
		'zip' => array('geo' => 'plz'),
		'city' => array('geo' => 'ort'),
		'district' => array('geo' => 'regionaler_zusatz'),
		'show_address' => array('verwaltung_objekt' => 'objektadresse_freigeben'),
		'number_of_rooms' => array('flaechen' => 'anzahl_zimmer'),
		'living_area' => array('flaechen' => 'wohnflaeche'),
		'total_area' => array('flaechen' => 'gesamtflaeche'),
		'estate_size' => array('flaechen' => 'grundstuecksflaeche'),
		'rent_excluding_bills' => array('preise' => 'kaltmiete'),
		'extra_charges' => array('preise' => 'nebenkosten'),
		'heating_included' => array('preise' => 'heizkosten_enthalten'),
		'deposit' => array('preise' => 'kaution'),
		'provision' => array('preise' => 'aussen_courtage'),
		// OpenImmo tag for 'usable_from' could possibly be 'abdatum'.
		'usable_from' => array('verwaltung_objekt' => 'verfuegbar_ab'),
		'buying_price' => array('preise' => 'kaufpreis'),
		'hoa_fee' => array('preise' => 'hausgeld'),
		'year_rent' => array('preise' => 'mieteinnahmen_ist'),
		'rented' => array('verwaltung_objekt' => 'vermietet'),
		'floor' => array('geo' => 'etage'),
		'floors' => array('geo' => 'anzahl_etagen'),
		'bedrooms' => array('flaechen' => 'anzahl_schlafzimmer'),
		'bathrooms' => array('flaechen' => 'anzahl_badezimmer'),
		'pets' => array('verwaltung_objekt' => 'haustiere'),
		'construction_year' => array('zustand_angaben' => 'baujahr'),
		'balcony' => array('flaechen' => 'anzahl_balkon_terrassen'),
		'garden' => array('ausstattung' => 'gartennutzung'),
		'barrier_free' => array('ausstattung' => 'rollstuhlgerecht'),
		'description' => array('freitexte' => 'objektbeschreibung'),
		'equipment' => array('freitexte' => 'ausstatt_beschr'),
		'location' => array('freitexte' => 'lage'),
		'misc' => array('freitexte' => 'sonstige_angaben'),
		'openimmo_obid' => array('verwaltung_techn' => 'openimmo_obid'),
		'contact_person' => array('kontaktperson' => 'name'),
		'contact_email' => array('kontaktperson' => 'email_zentrale'),
		'contact_phone' => array('kontaktperson' => 'tel_zentrale'),
	);

	/** raw data of an OpenImmo record */
	private $rawRealtyData = null;

	/** data which is the same for all realty records of one DOMDocument */
	private $universalRealtyData = array();

	/** imported data of a realty record */
	private $importedData = array();

	/**
	 * Number of the current record. Sometimes there are several realties in one
	 * OpenImmo record.
	 */
	private $recordNumber = 0;

	/** @var	array		cached countries */
	private static $cachedCountries = array();

	/**
	 * Frees as much memory that has been used by this object as possible.
	 */
	public function __destruct() {
		unset($this->rawRealtyData);
	}

	/**
	 * Handles the conversion of a DOMDocument and returns the realty records
	 * found in the DOMDocument as values of an array. Each of this values is an
	 * array with column names like in the database table 'tx_realty_objects' as
	 * keys and their corresponding values fetched from the DOMDocument.
	 * As images need to be inserted to a separate database table, all image
	 * data is stored in an inner array in the element 'images' of each record.
	 * The returned array is empty if the given DOMDocument could not be
	 * converted.
	 *
	 * @param	DOMDocument		data to convert, must not be null
	 *
	 * @return	array		data of each realty record, will be empty if the
	 * 						passed DOMDocument could not be converted
	 */
	public function getConvertedData(DOMDocument $domDocument) {
		$this->setRawRealtyData($domDocument);
		if (!$this->hasValidRootNode()) {
			return array();
		}

		$result = array();

		$this->fetchUniversalData();
		$numberOfRecords = $this->getNumberOfRecords();

		for (
			$this->recordNumber = 0;
			$this->recordNumber < $numberOfRecords;
			$this->recordNumber++
		) {
			$realtyRecordArray = $this->getRealtyArray();
			$this->addUniversalData($realtyRecordArray);
			$result[] = $realtyRecordArray;

			$this->resetImportedData();
		}

		return $result;
	}

	/**
	 * Loads the raw data from a DOMDocument.
	 *
	 * @param	DOMDocument		raw data to load, must not be null
	 */
	protected function setRawRealtyData(DOMDocument $rawRealtyData) {
		$this->rawRealtyData = new DOMXPath($rawRealtyData);
	}

	/**
	 * Resets the imported data.
	 */
	private function resetImportedData() {
		$this->importedData = array();
	}

	/**
	 * Appends realty data found in $this->universalRealtyData to the given
	 * array. This data is the same for all realty records in one DOMDocument
	 * and is fetched by $this->fetchUniversalData().
	 *
	 * @param	array		realty data, may be empty
	 */
	private function addUniversalData(array &$realtyDataArray) {
		$realtyDataArray = array_merge(
			$realtyDataArray,
			$this->universalRealtyData
		);
	}

	/**
	 * Fetches data which is the same for the whole set of realty records in
	 * the current OpenImmo record and stores it to $this->universalRealtyData.
	 */
	private function fetchUniversalData() {
		$this->universalRealtyData = $this->fetchEmployerAndAnid();
	}

	/**
	 * Fetches 'employer' and 'openimmo_anid' from an OpenImmo record and
	 * returns them in an array. These nodes must only occur once in an OpenImmo
	 * record.
	 *
	 * @return	array		contains the elements 'employer' and
	 * 						'openimmo_anid', will be empty if the nodes were not
	 * 						found
	 */
	private function fetchEmployerAndAnid() {
		$result = array();

		foreach (array(
			'firma' => 'employer',
			'openimmo_anid' => 'openimmo_anid'
		) as $grandchild => $columnName) {
			$nodeList = $this->getNodeListFromRawData('anbieter', $grandchild);
			$this->addElementToArray(
				$result,
				$columnName,
				$nodeList->item(0)->nodeValue
			);
		}

		return $result;
	}

	/**
	 * Substitutes XML namespaces from a node name and returns the name.
	 *
	 * @param	DOMNode		node, may be null
	 *
	 * @return	string		node name without namespaces, may be empty
	 */
	protected function getNodeName($domNode) {
		if (!is_a($domNode, 'DOMNode')) {
			return '';
		}

		return preg_replace('/(.*)\:/', '', $domNode->nodeName);
	}

	/**
	 * Gets the number of realties included in the current OpenImmo record.
	 *
	 * @return	integer		number of realties in the current OpenImmo record, 0
	 * 						if no realty data was found
	 */
	private function getNumberOfRecords() {
		$nodeList = $this->getListedRealties();

		if ($nodeList) {
			$result = $nodeList->length;
		} else {
			$result = 0;
		}

		return $result;
	}

	/**
	 * Converts the realty data to an array. The array values are fetched from
	 * the DOMDocument to convert. The keys are the same as the column names of
	 * the database table 'tx_realty_objects'. The result is an empty array if
	 * the given data is of an invalid format.
	 *
	 * @return	array		data of the realty object, empty if the DOMNode is
	 * 						not convertible
	 */
	private function getRealtyArray() {
		$this->fetchNodeValues();
		$this->fetchImages();
		$this->fetchEquipmentAttributes();
		$this->fetchCategoryAttributes();
		$this->fetchState();
		$this->fetchValueForOldOrNewBuilding();
		$this->fetchAction();
		$this->fetchHeatingType();
		$this->fetchGarageType();
		$this->fetchGaragePrice();
		$this->fetchCurrency();
		$this->fetchLanguage();
		$this->fetchGeoCoordinates();
		$this->fetchCountry();

		$this->replaceImportedBooleanLikeStrings();
		$this->substitudeSurplusDecimals();

		return $this->importedData;

	}

	/**
	 * Replaces the strings 'true' and 'false' of the currently imported data
	 * with real booleans. This replacement is not case sensitive.
	 */
	private function replaceImportedBooleanLikeStrings() {
		foreach ($this->importedData as $key => $value) {
			if ($this->isBooleanLikeStringTrue($value)) {
				$this->importedData[$key] = true;
			} elseif ($this->isBooleanLikeStringFalse($value)) {
				$this->importedData[$key] = false;
			}
		}
	}

	/**
	 * Returns true if a string equals 'true'. In any other case false is
	 * returned.
	 *
	 * @param	string		string to compare with 'true', may also be also
	 * 						uppercased, surrounded by quotes or empty
	 * @return	boolean		true if the input value was the string 'true', false
	 * 						otherwise
	 */
	private function isBooleanLikeStringTrue($booleanLikeString) {
		return strtolower(trim($booleanLikeString, '"')) == 'true';
	}

	/**
	 * Returns true if a string equals 'false'. In any other case false is
	 * returned.
	 *
	 * @param	string		string to compare with 'false', may also be also
	 * 						uppercased, surrounded by quotes or empty
	 * @return	boolean		true if the input value was the string 'true', false
	 * 						otherwise
	 */
	private function isBooleanLikeStringFalse($booleanLikeString) {
		return strtolower(trim($booleanLikeString, '"')) == 'false';
	}


	/**
	 * Substitutes decimals from the currently imported data if they are zero.
	 *
	 * Handles the "zip" column as a special case since here leading zeros are
	 * allowed. So the ZIP will not be intvaled.
	 */
	private function substitudeSurplusDecimals() {
		foreach ($this->importedData as $key => $value) {
			if (is_numeric($value) && ((int) $value) == $value
				&& ($key != 'zip')
			) {
				$this->importedData[$key] = intval($value);
			}
		}
	}

	/**
	 * Fetches node values and stores them with their corresponding database
	 * column names as keys in $this->importedData.
	 */
	private function fetchNodeValues() {
		foreach ($this->propertyArray as $databaseColumnName => $openImmoNames) {
			$currentDomNode = $this->findFirstGrandchild(
				key($openImmoNames),
				implode($openImmoNames)
			);
			$this->addImportedData(
				$databaseColumnName,
				$currentDomNode->nodeValue
			);
		}

		$this->appendStreetNumber();
		$this->setTitleForPets();
		$this->trySecondContactEmailIfEmailNotFound();
		$this->trySecondContactPhoneNumberIfPhoneNumberNotFound();
	}

	/**
	 * Fetches 'hausnummer' and appends it to the string in the array element
	 * 'street' of $this->importedData. If 'street' is empty or does not exist,
	 * nothing is changed at all.
	 */
	private function appendStreetNumber() {
		if (!$this->importedData['street']
			|| ($this->importedData['street'] == '')
		) {
			return;
		}

		$streetNumberNode = $this->findFirstGrandchild('geo', 'hausnummer');
		if ($streetNumberNode) {
			$this->addImportedData(
				'street',
				$this->importedData['street'].' '.$streetNumberNode->nodeValue
			);
		}
	}

	/**
	 * Replaces the value for 'pets' with a describtive string.
	 * 'pets' is boolean in OpenImmo records. But in the database the value for
	 * 'pets' is inserted to a separate table and displayed with this value as
	 * title in the FE.
	 */
	private function setTitleForPets() {
		if (!isset($this->importedData['pets'])) {
			return;
		}

		$translator = t3lib_div::makeInstance('tx_realty_translator');

		$petsValue = strtolower($this->importedData['pets']);
		if (($petsValue == 1) || $this->isBooleanLikeStringTrue($petsValue)) {
			$this->importedData['pets'] = $translator->translate('label_allowed');
		} else {
			$this->importedData['pets'] = $translator->translate('label_not_allowed');
		}
	}

	/**
	 * Fetches the contact e-mail from the tag 'email_direct' if the e-mail
	 * address has not been imported yet.
	 */
	private function trySecondContactEmailIfEmailNotFound() {
		if (isset($this->importedData['contact_email'])) {
			return;
		}

		$contactEmailNode = $this->findFirstGrandchild(
			'kontaktperson',
			'email_direkt'
		);
		$this->addImportedDataIfValueIsNonEmpty(
			'contact_email',
			$contactEmailNode->nodeValue
		);
	}

	/**
	 * Fetches the contact phone number from the tag 'tel_privat' if the phone
	 * number has not been imported yet.
	 */
	private function trySecondContactPhoneNumberIfPhoneNumberNotFound() {
		if (isset($this->importedData['contact_phone'])) {
			return;
		}

		$contactEmailNode = $this->findFirstGrandchild(
			'kontaktperson',
			'tel_privat'
		);
		$this->addImportedDataIfValueIsNonEmpty(
			'contact_phone',
			$contactEmailNode->nodeValue
		);
	}

	/**
	 * Fetches information about images from $openImmoNode of an OpenImmo record
	 * and stores them as an inner array in $this->importedData.
	 */
	private function fetchImages() {
		$this->addImportedDataIfValueIsNonEmpty(
			'images',
			$this->createRecordsForImages()
		);
	}

	/**
	 * Creates an array of image records for one realty record.
	 *
	 * @return	array		image records, may be empty
	 */
	protected function createRecordsForImages() {
		$images = array();
		$listedRealties = $this->getListedRealties();
		if (!$listedRealties) {
			return array();
		}

		$annexes = $this->getNodeListFromRawData(
			'anhang',
			'',
			$listedRealties->item($this->recordNumber)
		);

		foreach ($annexes as $contextNode) {
			$titleNodeList = $this->getNodeListFromRawData(
				'anhangtitel',
				'',
				$contextNode
			);

			$title = '';
			if ($titleNodeList->item(0)) {
				$title = $titleNodeList->item(0)->nodeValue;
			}

			$fileNameNodeList = $this->getNodeListFromRawData(
				'daten',
				'pfad',
				$contextNode
			);

			if ($fileNameNodeList->item(0)) {
				$fileName = basename($fileNameNodeList->item(0)->nodeValue);
			}

			if ($fileName != '') {
				$images[] = array(
					'caption' => $title,
					'image' => $fileName
				);
			}
		}

		return $images;
	}

	/**
	 * Fetches attributes about equipment and stores them with their
	 * corresponding database column names as keys in $this->importedData.
	 */
	private function fetchEquipmentAttributes() {
		$rawAttributes = array();

		foreach (array(
			'serviceleistungen',
			'fahrstuhl',
			'kueche',
		) as $grandchildName) {
			$nodeWithAttributes = $this->findFirstGrandchild(
				'ausstattung', $grandchildName
			);
			$rawAttributes[$grandchildName] = $this->fetchLowercasedDomAttributes(
				$nodeWithAttributes
			);
		}

		foreach (array(
			'assisted_living' => $rawAttributes['serviceleistungen']['betreutes_wohnen'],
			'fitted_kitchen' => $rawAttributes['kueche']['ebk'],
			// For realty records, the type of elevator is not relevant.
			'elevator' => $rawAttributes['fahrstuhl']['lasten'],
			'elevator' => $rawAttributes['fahrstuhl']['personen'],
		) as $key => $value) {
			if (isset($value)) {
				$this->addImportedDataIfValueIsNonEmpty($key, $value);
			}
		}
	}

	/**
	 * Fetches attributes about 'objektkategorie' and stores them with their
	 * corresponding database column names as keys in $this->importedData.
	 */
	private function fetchCategoryAttributes() {
		$this->fetchHouseType();

		$nodeWithAttributes = $this->findFirstGrandchild(
			'objektkategorie',
			'vermarktungsart'
		);

		$objectTypeAttributes = $this->fetchLowercasedDomAttributes(
			$nodeWithAttributes
		);

		// It must be ensured that the key 'object_type' is set as soon as there
		// are attributes provided, because 'object_type' is a required field.
		if (!empty($objectTypeAttributes)) {
			if (isset($objectTypeAttributes['kauf'])) {
				$this->addImportedData('object_type', $objectTypeAttributes['kauf']);
			} else {
				$this->addImportedData('object_type', 0);
			}
		}

		$nodeWithAttributes = $this->findFirstGrandchild(
			'objektkategorie',
			'nutzungsart'
		);
		$utilizationAttributes = $this->fetchDomAttributes($nodeWithAttributes);
		if (!empty($utilizationAttributes)) {
			$this->addImportedData(
				'utilization',
				$this->getFormattedString(array_keys($utilizationAttributes))
			);
		}
	}

	/**
	 * Fetches the 'objektart' and stores it with the corresponding database
	 * column name 'house_type' as key in $this->importedData.
	 */
	private function fetchHouseType() {
		$nodeContainingAttributeNode = $this->findFirstGrandchild(
			'objektkategorie',
			'objektart'
		);
		if (!$nodeContainingAttributeNode) {
			return;
		}

		$nodeWithAttributes = $this->rawRealtyData->query(
			'.//*[not(starts-with(local-name(), "#"))]',
			$nodeContainingAttributeNode
		);

		$value = $this->getNodeName($nodeWithAttributes->item(0));

		if ($value != '') {
			$attributes = $this->fetchDomAttributes($nodeWithAttributes);

			if (!empty($attributes)) {
				$value .= ': '
					.$this->getFormattedString(array_values($attributes));
			}

			$this->addImportedData(
				'house_type',
				$this->getFormattedString(array($value))
			);
		}
	}

	/**
	 * Fetches the 'heizungsart' and stores it with the corresponding database
	 * column name 'heating_type' as key in $this->importedData.
	 */
	private function fetchHeatingType() {
		$heatingTypeNode = $this->findFirstGrandchild('ausstattung', 'heizungsart');
		$firingTypeNode = $this->findFirstGrandchild('ausstattung', 'befeuerung');
		$attributes = array_merge(
			array_keys($this->fetchLowercasedDomAttributes($heatingTypeNode)),
			array_keys($this->fetchLowercasedDomAttributes($firingTypeNode))
		);

		// The fetched heating types are always German. In the database they
		// are stored as a sorted list of keys which refer to localized strings.
		$keys = array_keys(array_intersect(
			array(
				1 => 'fern',
				2 => 'zentral',
				3 => 'elektro',
				4 => 'fussboden',
				5 => 'gas',
				6 => 'alternativ',
				7 => 'erdwaerme',
				8 => 'oel',
				9 => 'etage',
				10 => 'solar',
				11 => 'ofen',
				12 => 'block',
			),
			$attributes
		));
		$this->addImportedDataIfValueIsNonEmpty(
			'heating_type', implode(',', $keys)
		);
	}

	/**
	 * Fetches the 'stellplatzart' and stores it with the corresponding database
	 * column name 'garage_type' as key in $this->importedData.
	 */
	private function fetchGarageType() {
		$nodeWithAttributes = $this->findFirstGrandchild(
			'ausstattung', 'stellplatzart'
		);
		$attributes = $this->fetchLowercasedDomAttributes($nodeWithAttributes);

		$this->addImportedDataIfValueIsNonEmpty(
			'garage_type', $this->getFormattedString(array_keys($attributes))
		);
	}

	/**
	 * Fetches the attribute for 'stellplatzmiete' and 'stellplatzkaufpreis' and
	 * stores them with the corresponding database column name as key in
	 * $this->importedData.
	 */
	private function fetchGaragePrice() {
		$nodeWithAttributes = $this->findFirstGrandchild(
			'preise',
			// 'stp_*' exists for each defined type of 'stellplatz'
			'stp_garage'
		);
		$attributes = $this->fetchLowercasedDomAttributes($nodeWithAttributes);

		if (isset($attributes['stellplatzmiete'])) {
			$this->addImportedDataIfValueIsNonEmpty(
				'garage_rent', $attributes['stellplatzmiete']
			);
		}
		if (isset($attributes['stellplatzkaufpreis'])) {
			$this->addImportedDataIfValueIsNonEmpty(
				'garage_price', $attributes['stellplatzkaufpreis']
			);
		}
	}

	/**
	 * Fetches the attribute 'currency' and stores it in $this->importedData.
	 */
	private function fetchCurrency() {
		$nodeWithAttributes = $this->findFirstGrandchild('preise', 'waehrung');
		$attributes = $this->fetchLowercasedDomAttributes($nodeWithAttributes);

		if (isset($attributes['iso_waehrung'])) {
			$this->addImportedDataIfValueIsNonEmpty(
				'currency', strtoupper($attributes['iso_waehrung'])
			);
		}
	}

	/**
	 * Fetches the attributes for 'zustand' and stores them with the
	 * corresponding database column name as key in $this->importedData.
	 */
	private function fetchState() {
		$nodeWithAttributes = $this->findFirstGrandchild(
			'zustand_angaben', 'zustand'
		);
		$attributes = $this->fetchLowercasedDomAttributes($nodeWithAttributes);
		$possibleStates = array(
			'rohbau' => 1,
			'nach_vereinbarung' => 2,
			'baufaellig' => 3,
			'erstbezug' => 4,
			'abrissobjekt' => 5,
			'entkernt' => 6,
			'modernisiert' => 7,
			'gepflegt' => 8,
			'teil_vollrenovierungsbed' => 9,
			'neuwertig' => 10,
			'teil_vollrenoviert' => 11,
			'teil_vollsaniert' => 12,
			'projektiert' => 13,
		);

		if (isset($attributes['zustand_art'])
			&& isset($possibleStates[$attributes['zustand_art']])
		) {
			$this->addImportedData(
				'state', $possibleStates[$attributes['zustand_art']]
			);
		}
	}

	/**
	 * Fetches the value for 'old_or_new_building' and stores it in
	 * $this->importedData.
	 */
	private function fetchValueForOldOrNewBuilding() {
		$attributesArray = $this->fetchLowercasedDomAttributes(
			$this->findFirstGrandchild('zustand_angaben', 'alter')
		);

		if ($attributesArray['alter_attr'] == 'neubau') {
			$this->addImportedData('old_or_new_building', 1);
		} elseif ($attributesArray['alter_attr'] == 'altbau') {
			$this->addImportedData('old_or_new_building', 2);
		}
	}

	/**
	 * Fetches the attribute 'aktion' and stores it with the corresponding
	 * database column name as key in $this->importedData.
	 */
	private function fetchAction() {
		$nodeWithAttributes = $this->findFirstGrandchild(
			'verwaltung_techn',
			'aktion'
		);
		// The node is valid when there is a node name, it does not need to
		// have attributes.
		if ($this->getNodeName($nodeWithAttributes)) {
 			$this->addImportedData(
				'deleted',
				in_array(
					'delete',
					$this->fetchLowercasedDomAttributes($nodeWithAttributes)
				)
 			);
 		}
	}

	/**
	 * Fetches the value for 'language' and stores it in $this->importedData.
	 */
	private function fetchLanguage() {
		$userDefinedAnyfieldNode = $this->findFirstGrandchild(
			'verwaltung_objekt',
			'user_defined_anyfield'
		);

		if ($userDefinedAnyfieldNode) {
			$languageNode = $this->getNodeListFromRawData(
				'sprache',
				'',
				$userDefinedAnyfieldNode
			);
			$this->addImportedData(
				'language',
				$languageNode->item(0)->nodeValue
			);
		}
	}

	/**
	 * Fetches the values for the geo coordinates and stores it in
	 * $this->importedData.
	 */
	private function fetchGeoCoordinates() {
		$geoCoordinatesNode = $this->findFirstGrandchild(
			'geo', 'geokoordinaten'
		);
		$attributes = $this->fetchLowercasedDomAttributes($geoCoordinatesNode);

		if ($this->isElementSetAndNonEmpty('laengengrad', $attributes)
			&& $this->isElementSetAndNonEmpty('breitengrad', $attributes)
		) {
			$this->addImportedData('exact_coordinates_are_cached', true);
			$this->addImportedData('exact_longitude', $attributes['laengengrad']);
			$this->addImportedData('exact_latitude', $attributes['breitengrad']);
		}
	}

	/**
	 * Fetches the value for country, finds the corresponding UID in the static
	 * countries table and stores it in $this->importedData.
	 *
	 * @throws	Exception		if the database query fails
	 */
	private function fetchCountry() {
		$nodeWithAttributes = $this->findFirstGrandchild('geo', 'land');
		$attributes = $this->fetchLowercasedDomAttributes($nodeWithAttributes);

		if (!isset($attributes['iso_land']) || ($attributes['iso_land'] == '')) {
			return;
		}

		$country = strtoupper($attributes['iso_land']);

		if (isset(self::$cachedCountries[$country])) {
			$uid = self::$cachedCountries[$country];
		} else {
			$dbResult = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'uid',
				STATIC_COUNTRIES,
				'cn_iso_3="' .
					$GLOBALS['TYPO3_DB']->quoteStr($country, STATIC_COUNTRIES) .
					'"'
			);
			if (!$dbResult) {
				throw new Exception(DATABASE_QUERY_ERROR);
			}
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($dbResult);
			$GLOBALS['TYPO3_DB']->sql_free_result($dbResult);
			$uid = $row ? $row['uid'] : 0;
			$this->cacheCountry($country, $uid);
		}

		$this->addImportedDataIfValueIsNonEmpty('country', $uid);
	}

	/**
	 * Returns a comma-separated list of an array. The first letter of each word
	 * is uppercased.
	 *
	 * @param	array		data to format, must not be empty
	 *
	 * @return	string		formatted string
	 */
	private function getFormattedString(array $dataToFormat) {
		return ucwords(strtolower(implode(', ', $dataToFormat)));
	}

	/**
	 * Returns DOMNodeList from the raw data. This list consists of all
	 * elements from raw data which apply to $nodeName and to $childNodeName. If
	 * $childNodeName is empty, $nodeName is the only criteria. $nodeName and
	 * $childNodeName must not contain namespaces.
	 * If $contextNode is set, the elements are fetched relatively from this
	 * node.
	 *
	 * @param	string		node name, must not be empty
	 * @param	string		child node name, may be empty, the elements are taken
	 * 						from the node named $nodeName then
	 * @param 	DOMNode		subnode to fetch a relative result, may be null, the
	 * 						query is made on the root node then
	 *
	 *
	 * @return	DOMNodeList		all nodes which are named $childNodeName,
	 * 						$nodeName if $childNodeName is not set, can be
	 * 						empty if these names do not exist
	 */
	private function getNodeListFromRawData(
		$nodeName,
		$childNodeName = '',
		$contextNode = null
	) {
		$queryString = '';
		$isContextNodeValid = false;
		if ($contextNode && (get_parent_class($contextNode) == 'DOMNode')) {
			$isContextNodeValid = true;
			$queryString = '.';
		}

		$queryString .= '//*[local-name()="'.$nodeName.'"]';
		if ($childNodeName != '') {
			$queryString .= '/*[local-name()="'.$childNodeName.'"]';
		}

		if ($isContextNodeValid) {
			$result = $this->rawRealtyData->query($queryString, $contextNode);
		} else {
			$result = $this->rawRealtyData->query($queryString);
		}

		return $result;
	}

	/**
	 * Returns the first grandchild of an element inside the realty record
	 * with the current record number specified by the child's and the
	 * grandchild's name. If one of these names can not be found or there are no
	 * realty records, null is returned.
	 *
	 * @param	string		name of child, must not be empty
	 * @param	string		name of grandchild, must not be empty
	 *
	 * @return	DOMNode		first grandchild with this name, null if it does not
	 * 						exist
	 */
	protected function findFirstGrandchild(
		$nameOfChild,
		$nameOfGrandchild
	) {
		$listedRealties = $this->getListedRealties();

		if (!$listedRealties) {
			return null;
		}

		$contextNode = $listedRealties->item($this->recordNumber);

		$queryResult = $this->getNodeListFromRawData(
			$nameOfChild,
			$nameOfGrandchild,
			$contextNode
		);

		if ($queryResult) {
			$result = $queryResult->item(0);
		} else {
			$result = null;
		}

		return $result;
	}

	/**
	 * Checks whether the OpenImmo record has a valid root node. The node must
	 * be named 'openimmo' or 'immoxml'.
	 *
	 * @return	boolean		true if the root node is named 'openimmo' or
	 * 						'immoxml', false otherwise
	 */
	private function hasValidRootNode() {
		$rootNode = $this->rawRealtyData->query(
			'//*[local-name()="openimmo"] | //*[local-name()="immoxml"]'
		);

		return (boolean) $rootNode->item(0);
	}

	/**
	 * Returns a DOMNodeList of the realty records found in $realtyData or null
	 * if there are none.
	 *
	 * @return   	DOMNodeList		list of nodes named 'immobilie', null if
	 * 							none were found
	 */
	private function getListedRealties() {
		return $this->getNodeListFromRawData('immobilie');
	}

	/**
	 * Adds a new element $value to the array $arrayExpand, using the key $key.
	 * The element will not be added if is null.
	 *
	 * @param	array		into which the new element should be inserted, may
	 *						be empty
	 * @param	string		key to insert
	 * @param	mixed		value to insert
	 */
	protected function addElementToArray(array &$arrayToExpand, $key, $value) {
		if (!is_null($value)) {
			$arrayToExpand[$key] = $value;
		}
	}

	/**
	 * Adds an element to $this->importedData.
	 *
	 * @param	string		key to insert, must not be empty
	 * @param	mixed		value to insert
	 */
	private function addImportedData($key, $value) {
		$this->addElementToArray($this->importedData, $key, $value);
	}

	/**
	 * Adds an element to $this->importedData if $value is non-empty.
	 *
	 * @param	string		key for the element to add, must not be empty
	 * @param	mixed		value for the element to add, will not be added if
	 * 						it is empty
	 */
	private function addImportedDataIfValueIsNonEmpty($key, $value) {
		if (empty($value)) {
			return;
		}

		$this->addImportedData($key, $value);
	}

	/**
	 * Checks whether an element exists in an array and is non-empty.
	 *
	 * @param	string		key of the element that should be checked to exist
	 * 						and being non-empty, must not be empty
	 * @param	array		array in which the existance of an element should be
	 * 						checked, may be empty
	 *
	 * @return	boolean		true if the the element exists and is non-empty,
	 * 						false otherwise
	 */
	private function isElementSetAndNonEmpty($key, array $array) {
		return (isset($array[$key]) && !empty($array[$key]));
	}

	/**
	 * Fetches an attribute from a given node and returns name/value pairs as an
	 * array. If there are no attributes, the returned array will be empty.
	 *
	 * @param	DOMNode		node from where to fetch the attribute, may be null
	 *
	 * @return	array		attributes and attribute values, empty if there are
	 * 						no attributes
	 */
	protected function fetchDomAttributes($nodeWithAttributes) {
		if (!$nodeWithAttributes) {
			return array();
		}

		$fetchedValues = array();
		$attributeToFetch = $nodeWithAttributes->attributes;
		if ($attributeToFetch) {
			foreach ($attributeToFetch as $domObject) {
				$fetchedValues[$domObject->name] = $domObject->value;
			}
		}

		return $fetchedValues;
	}

	/**
	 * Fetches an attribute from a given node and returns lowercased name/value
	 * pairs as an array. If there are no attributes, the returned array will be
	 * empty.
	 *
	 * @param	DOMNode		node from where to fetch the attribute, may be null
	 *
	 * @return	array		lowercased attributes and attribute values, empty if
	 * 						there are no attributes
	 */
	private function fetchLowercasedDomAttributes($nodeWithAttributes) {
		$result = array();

		foreach ($this->fetchDomAttributes($nodeWithAttributes) as $key => $value) {
			$result[strtolower($key)] = strtolower($value);
		}

		return $result;
	}

	/**
	 * Caches the fetched countries in order to reduce the the number of
	 * database queries.
	 *
	 * @param	string		ISO3166 code for the country, must not be empty
	 * @param	integer		UID of the country, must match the UID in the static
	 * 						countries table, must be >= 0
	 */
	private function cacheCountry($key, $value) {
		self::$cachedCountries[$key] = $value;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_domDocumentConverter.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/realty/lib/class.tx_realty_domDocumentConverter.php']);
}
?>