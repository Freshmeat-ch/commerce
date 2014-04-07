<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 2005-2012 Volker Graubaum <vg@e-netconsulting.de>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Product list and single view
 */
class Tx_Commerce_Controller_ListController extends Tx_Commerce_Controller_BaseController {
	/**
	 * Same as class name
	 *
	 * @var string
	 */
	public $prefixId = 'tx_commerce_pi1';

	/**
	 * @var boolean
	 */
	public $pi_checkCHash = TRUE;

	/**
	 * @var Boolean, will be set to TRUE, if the plugin was inserted as plugin to show one produkt, or one product was set via TypoScript.
	 */
	public $singleViewAsPlugin = FALSE;

	/**
	 * @var integer
	 */
	public $master_cat;

	/**
	 * @var Tx_Commerce_Domain_Model_Category
	 */
	public $masterCategoryObj;

	/**
	 * @var array
	 */
	public $category_array = array();

	/**
	 * @var string
	 */
	public $templateFolder = '';

	/**
	 * @var array
	 */
	public $markerArray = array();

	/**
	 * @var string
	 */
	public $content = '';

	/**
	 * @var array
	 */
	public $product_array = array();

	/**
	 * Inits the main params for using in the script
	 *
	 * @param array $conf Configuration
	 * @return void
	 */
	public function init($conf) {
		parent::init($conf);

			// Merge default vars, if other prefix_id
		if ($this->prefixId <> 'tx_commerce_pi1') {
			$generellRequestVars = t3lib_div::_GP('tx_commerce');
			if (is_array($generellRequestVars)) {
				foreach ($generellRequestVars as $key => $value) {
					if (empty($this->piVars[$key])) {
						$this->piVars[$key] = $value;
					}
				}
			}
		}

		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi1/class.tx_commerce_pi1.php']['init'])) {
			t3lib_div::deprecationLog('
				hook
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/pi1/class.tx_commerce_pi1.php\'][\'init\']
				is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0, please use instead
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/Classes/Controller/ListController.php\'][\'init\']
			');
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi1/class.tx_commerce_pi1.php']['init'] as $classRef) {
				$hookObj = &t3lib_div::getUserObj($classRef);
				if (method_exists($hookObj, 'preInit')) {
					$hookObj->preInit($this);
				}
			}
		}
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['init'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['init'] as $classRef) {
				$hookObj = &t3lib_div::getUserObj($classRef);
				if (method_exists($hookObj, 'preInit')) {
					$hookObj->preInit($this);
				}
			}
		}

		$this->templateFolder = 'uploads/tx_commerce/';
		$this->pi_USER_INT_obj = 0;

		$this->conf['singleProduct'] = (int) $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'product_id', 's_product');

		if ($this->conf['singleProduct'] > 0) {
			// product UID was set by Plugin or TS
			$this->singleViewAsPlugin = TRUE;
		}

		// Unset variable, if smaller than 0, as -1 is returend
		// when no product is selcted in form.
		if ($this->conf['singleProduct'] < 0) {
			$this->conf['singleProduct'] = FALSE;
		}

		$this->piVars['showUid'] = intval($this->piVars['showUid']) ?: 0;
		$this->piVars['showUid'] = $this->piVars['showUid'] ?: $this->conf['singleProduct'];
		$this->handle = $this->piVars['showUid'] ? 'singleView' : 'listView';

			// Define the currency
			// Use of curency is depricated as it was only a typo :-)
		if ($this->conf['curency'] > '') {
			$this->currency = $this->conf['curency'];
		}
		if ($this->conf['currency'] > '') {
			$this->currency = $this->conf['currency'];
		}
		if (empty($this->currency)) {
			$this->currency = 'EUR';
		}

			// Set some flexform values
		$this->master_cat = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'StartCategory', 's_product');
		if (!$this->master_cat) {
			$this->master_cat = $this->conf['catUid'];
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'displayPID', 's_template')) {
			$this->conf['overridePid'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'displayPID', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'numberOfTopproducts', 's_product')) {
			$this->conf['numberOfTopproducts'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'numberOfTopproducts', 's_product');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showPageBrowser', 's_template')) {
			$this->conf['showPageBrowser'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showPageBrowser', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxRecords', 's_template')) {
			$this->conf['maxRecords'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxRecords', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxPages', 's_template')) {
			$this->conf['maxPages'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'maxPages', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'basketPid', 's_template')) {
			$this->conf['basketPid'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'basketPid', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'dontLinkActivePage', 's_template')) {
			$this->conf['pageBrowser.']['dontLinkActivePage'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'dontLinkActivePage', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showFirstLast', 's_template')) {
			$this->conf['pageBrowser.']['showFirstLast'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showFirstLast', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showRange', 's_template')) {
			$this->conf['pageBrowser.']['showRange'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showRange', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showItemCount', 's_template')) {
			$this->conf['pageBrowser.']['showItemCount'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'showItemCount', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'hscText', 's_template')) {
			$this->conf['pageBrowser.']['hscText'] = $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'hscText', 's_template');
		}
		if ($this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template', 's_template') && file_exists($this->templateFolder . $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template', 's_template'))) {
			$this->conf['templateFile'] = $this->templateFolder . $this->pi_getFFvalue($this->cObj->data['pi_flexform'], 'template', 's_template');
			if ($this->cObj->fileResource($this->conf['templateFile'])) {
				$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);
			}
		}

		$accessible = FALSE;
		/** @var Tx_Commerce_Domain_Model_Category $tmpCategory */
		$tmpCategory = NULL;
		if ($this->piVars['catUid']) {
			$this->cat = (int) $this->piVars['catUid'];
			$tmpCategory = t3lib_div::makeinstance('Tx_Commerce_Domain_Model_Category', $this->cat);
			$tmpCategory->init($this->cat, $GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']);
			$accessible = $tmpCategory->isAccessible();
		}

			// Validate given catUid, if it's given and accessible
		if (!$this->piVars['catUid'] || !$accessible) {
			$this->cat = (int) $this->master_cat;
			$tmpCategory = t3lib_div::makeinstance('Tx_Commerce_Domain_Model_Category', $this->cat);
			$tmpCategory->init($this->cat, $GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']);
		}
		if (!isset($this->piVars['catUid'])) {
			$this->piVars['catUid'] = $this->master_cat;
		}
		if (is_object($tmpCategory)) {
		$tmpCategory->loadData();
		}
		$this->category = $tmpCategory;

		$categorySubproducts = $this->category->getProductUids();

		/** @var tslib_fe $frontend */
		$frontend = & $GLOBALS['TSFE'];

		if ((!$this->conf['singleProduct']) && ((int)$this->piVars['showUid'] > 0) && (!$GLOBALS['TSFE']->beUserLogin)) {
			if (is_array($categorySubproducts)) {
				if (!in_array($this->piVars['showUid'], $categorySubproducts)) {
					$categoryAllSubproducts = $this->category->getProducts();
					if (!in_array((int)$this->piVars['showUid'], $categoryAllSubproducts)) {
							// The requested product is not beblow the selected category
							// So exit with page not found
						$frontend->pageNotFoundAndExit($this->pi_getLL('error.productNotFound', 'Product not found', 1));
					}
				}
			} else {
				$categoryAllSubproducts = $this->category->getProducts();
				if (!in_array($this->piVars['showUid'], $categoryAllSubproducts)) {
						// The requested product is not beblow the selected category
						// So exit with page not found
					$frontend->pageNotFoundAndExit($this->pi_getLL('error.productNotFound', 'Product not found', 1));
				}
			}
		}

		if (($this->piVars['catUid']) && ($this->conf['checkCategoryTree'] == 1)) {
				// Validate given CAT UID, if is below master_cat
			/** @var Tx_Commerce_Domain_Model_Category masterCategoryObj */
			$this->masterCategoryObj = t3lib_div::makeinstance('Tx_Commerce_Domain_Model_Category', $this->master_cat, $GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']);
			$this->masterCategoryObj->loadData();
			$masterCategorySubCategories = $this->masterCategoryObj->getChildCategoriesUidlist();
			if (in_array($this->piVars['catUid'], $masterCategorySubCategories)) {
				$this->cat = (int)$this->piVars['catUid'];
			} else {
					// Wrong UID, so start with page not found
				$frontend->pageNotFoundAndExit($this->pi_getLL('error.categoryNotFound', 'Product not found', 1));
			}
		} elseif (!isset($this->piVars['catUid'])) {
			$this->cat = (int) $this->master_cat;
		}

		if ($this->cat <> $this->category->getUid()) {
				// Only, if the category has been changed
			unset($this->category);
			/** @var Tx_Commerce_Domain_Model_Category category */
			$this->category = t3lib_div::makeinstance('Tx_Commerce_Domain_Model_Category', $this->cat, $GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']);
			$this->category->loadData();
		}

		$this->internal['results_at_a_time'] = $this->conf['maxRecords'];
		$this->internal['maxPages'] = $this->conf['maxPages'];

			// Going the long way ??? Just for list view
		$long = 1;
		switch ($this->handle) {
			case 'singleView':
				if ($this->initSingleView($this->piVars['showUid'])) {
					$long = 0;
				}
				break;

			default:
		}

		if ($this->cat > 0) {
			$this->category_array = $this->category->returnAssocArray();

			$catConf = $this->category->getTyposcriptConfig();
			if (is_array($catConf['catTS.'])) {
				$this->conf = t3lib_div::array_merge_recursive_overrule($this->conf, $catConf['catTS.']);
			}

			if ($long) {
				$this->category->setPageTitle();
				$this->category->getChildCategories();
				if ($this->conf['groupProductsByCategory']) {
					$this->category_products = $this->category->getProducts(0);
				} elseif ($this->conf['showProductsRecLevel']) {
					$this->category_products = $this->category->getProducts($this->conf['showProductsRecLevel']);
				} else {
					$this->category_products = $this->category->getProducts(0);
				}
				if ($this->conf['useStockHandling'] == 1) {
					$this->category_products = Tx_Commerce_Utility_GeneralUtility::removeNoStockProducts($this->category_products, $this->conf['products.']['showWithNoStock']);
				}
				$this->internal['res_count'] = count($this->category_products);
			}
		} else {
			$this->content = $this->cObj->stdWrap($this->conf['emptyCOA'], $this->conf['emptyCOA.']);
			$this->handle = FALSE;
		}

		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi1/class.tx_commerce_pi1.php']['postInit'])) {
			t3lib_div::deprecationLog('
				hook
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/pi1/class.tx_commerce_pi1.php\'][\'postInit\']
				is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0, please use instead
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/Classes/Controller/ListController.php\'][\'postInit\']
			');
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/pi1/class.tx_commerce_pi1.php']['postInit'] as $classRef) {
				$hookObj = &t3lib_div::getUserObj($classRef);
				if (method_exists($hookObj, 'postInit')) {
					$hookObj->postInit($this);
				}
			}
		}
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['postInit'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['postInit'] as $classRef) {
				$hookObj = &t3lib_div::getUserObj($classRef);
				if (method_exists($hookObj, 'postInit')) {
					$hookObj->postInit($this);
				}
			}
		}
	}

	/**
	 * Main function called by insert plugin
	 *
	 * @param string $content Content
	 * @param string $conf Configuration
	 * @return string HTML-Content
	 */
	public function main($content, $conf) {
		// If product or categorie is inserted by insert record use uid
		// from insert record cObj
		if (!empty($conf['insertRecord'])) {
			if ($conf['insertRecord'] == 'products') {
				$this->piVars['showUid'] = $this->cObj->data['uid'];
				$this->piVars['catUid'] = $this->cObj->data['categories'];
			} else {
				$this->piVars['catUid'] = $this->cObj->data['uid'];
			}
		}

		$this->init($conf);

			// Get the template
		$this->templateCode = $this->cObj->fileResource($this->conf['templateFile']);

		$this->template = array();
		$this->markerArray = array();

		if ($this->handle == 'singleView') {
			$subpartName = '###' . strtoupper($this->conf['templateMarker.']['productView']) . '###';
			$subpartNameNostock = '###' . strtoupper($this->conf['templateMarker.']['productView']) . '_NOSTOCK###';
			$this->content = $this->renderSingleView($this->product, $this->category, $subpartName, $subpartNameNostock);
			$this->content = $this->cObj->substituteMarkerArray($this->content, $this->languageMarker);
			$this->content = $this->cObj->substituteMarkerArray($this->content, $this->addFormMarker(array()), '###|###', 1);
			$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_commerce_lastproducturl', $this->pi_linkTP_keepPIvars_url());
		} elseif ($this->handle == 'listView') {
			$this->content = $this->makeListView($this->cat);
		}

		$content .= $this->content;

		return $this->conf['wrapInBaseClass'] ? $this->pi_wrapInBaseClass($content) : $content;
	}

	/**
	 * Init the singleView for one product
	 *
	 * @param integer $productId ProductID for single view
	 * @return boolean
	 */
	public function initSingleView($productId) {
		$productId = (int) $productId;

		if ($productId > 0) {
			/** @var t3lib_db $database */
			$database = $GLOBALS['TYPO3_DB'];

				// Get not localized product
			$mainProductRes = $database->exec_SELECTquery('l18n_parent', 'tx_commerce_products', 'uid = ' . $productId);
			if ($database->sql_num_rows($mainProductRes) == 1 AND $row = $database->sql_fetch_assoc($mainProductRes) AND $row['l18n_parent'] != 0) {
				$productId = $row['l18n_parent'];
			}

			$this->product = t3lib_div::makeInstance('Tx_Commerce_Domain_Model_Product', $productId, $GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']);
			$this->product->loadData();

			if ($this->product->isAccessible()) {
				$this->selectAttributes = $this->product->getAttributes(array(ATTRIB_SELECTOR));
				$this->product_attributes = $this->product->getAttributes(array(ATTRIB_PRODUCT));
				$this->can_attributes = $this->product->getAttributes(array(ATTRIB_CAN));
				$this->shall_attributes = $this->product->getAttributes(array(ATTRIB_SHAL));
				$this->product_array = $this->product->returnAssocArray();
				$this->product->loadArticles();

					// Check if the product was inserted as plugin on a page,
					// or if it was rendered as a leaf from the category view
				if ($this->conf['singleView.']['renderProductNameAsPageTitle'] == 1) {
					$this->product->setPageTitle();
				} elseif (($this->conf['singleView.']['renderProductNameAsPageTitle'] == 2) && ($this->singleViewAsPlugin === FALSE)) {
					$this->product->setPageTitle();
				}

				$this->master_cat = $this->product->getMasterparentCategory();

					// Write the current page to the session to have a back to last product link
				$GLOBALS['TSFE']->fe_user->setKey('ses', 'tx_commerce_lastproducturl', $this->pi_linkTP_keepPIvars_url());
				return TRUE;
			} else {
					// If product ist not valid (url manipulation) go to listview
				$this->handle = 'listView';
			}
		}
		return FALSE;
	}

	/**
	 * Render the single view for the current products
	 *
	 * @param Tx_Commerce_Domain_Model_Product $product Product object
	 * @param Tx_Commerce_Domain_Model_Category $category Category object
	 * @param string $subpartName name of a subpart
	 * @param string $subpartNameNostock name of a subpart for product without stock
	 * @return string The content for a single product
	 */
	public function renderSingleView($product, $category, $subpartName, $subpartNameNostock) {
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/lib/class.tx_commerce_pibase.php']['singleview'])) {
			t3lib_div::deprecationLog('
				hook
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/lib/class.tx_commerce_pibase.php\'][\'singleview\']
				is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0, please use instead
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/Classes/Controller/ListController.php\'][\'renderSingleView\']
			');
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/lib/class.tx_commerce_pibase.php']['singleview'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['renderSingleView'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['renderSingleView'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		$result = NULL;
		foreach ($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'preRenderSingleView')) {
				$result = $hookObj->preRenderSingleView($product, $category, $subpartName, $subpartNameNostock, $this);
			}
		}
		if ($result) {
			return $result;
		}

		$template = $this->cObj->getSubpart($this->templateCode, $subpartName);
		if ($this->conf['useStockHandling'] == 1 && !$product->hasStock()) {
			$productTypoScript = $this->conf['singleView.']['products.']['nostock.'];
			$noStockTemplate = $this->cObj->getSubpart($this->templateCode, $subpartNameNostock);
			if ($noStockTemplate != '') {
				$template = $noStockTemplate;
			}
		} else {
			$productTypoScript = $this->conf['singleView.']['products.'];
		}

		$content = $this->renderProduct(
			$product,
			$template,
			$productTypoScript,
			$this->conf['templateMarker.']['basketSingleView.'],
			$this->conf['templateMarker.']['basketSingleViewMarker']
		);

		// Get category data
		$category->loadData();

		// Render category for content
		$categorySubpart = $this->renderCategory(
			$category,
			'###' . strtoupper($this->conf['templateMarker.']['categorySingleViewMarker']) . '###',
			$this->conf['singleView.']['products.']['categories.'],
			'ITEM',
			$content
		);

		// Substitude the subpart
		$content = $this->cObj->substituteSubpart($content, '###' . strtoupper($this->conf['templateMarker.']['categorySingleViewMarker']) . '###', $categorySubpart);

		// Build the link to the category
		$categoryLinkContent = $this->cObj->getSubpart($content, '###CATEGORY_ITEM_DETAILLINK###');
		if ($categoryLinkContent) {
			$link = $this->pi_linkTP($categoryLinkContent, array('tx_commerce_pi1[catUid]' => $category->getUid()), TRUE);
		} else {
			$link = '';
		}
		$content = $this->cObj->substituteSubpart($content, '###CATEGORY_ITEM_DETAILLINK###', $link);

		// Render related products
		$relatedProductsSubpart = '';
		$relatedProductsParentSubpart = $this->cObj->getSubpart($template, '###' . strtoupper($this->conf['templateMarker.']['relatedProductList']) . '###');
		$relatedProductsSubpartTemplateStock = $this->cObj->getSubpart($relatedProductsParentSubpart, '###' . strtoupper($this->conf['templateMarker.']['relatedProductSingle']) . '###');
		$relatedProductsSubpartTemplateNoStock = $this->cObj->getSubpart($relatedProductsParentSubpart, '###' . strtoupper($this->conf['templateMarker.']['relatedProductSingle']) . '_NOSTOCK###');
		/** @var Tx_Commerce_Domain_Model_Product $relatedProduct */
		foreach ($product->getRelatedProducts() as $relatedProduct) {
			if ($this->conf['useStockHandling'] == 1 && !$relatedProduct->hasStock()) {
				$localTemplate = $relatedProductsSubpartTemplateNoStock;
				$localTypoScript = $this->conf['singleView.']['products.']['relatedProducts.']['nostock.'];
			} else {
				$localTemplate = $relatedProductsSubpartTemplateStock;
				$localTypoScript = $this->conf['singleView.']['products.']['relatedProducts.'];
			}

			// Related products don't have articles here, to save render time
			$relatedProductsSubpart .= $this->renderProduct(
				$relatedProduct,
				$localTemplate,
				$localTypoScript,
				$this->conf['templateMarker.']['basketSingleView.'],
				$this->conf['templateMarker.']['basketSingleViewMarker']
			);
		}

		// Additional headers for "related products" are overwritten by subparts
		// So we will change this here. In thought of sorting, we can't split entries.
		if ($relatedProductsSubpart != '') {
			// Set first subpart empty
			$content = $this->cObj->substituteSubpart($content, '###' . strtoupper($this->conf['templateMarker.']['relatedProductSingle']) . '###', $relatedProductsSubpart);
			// Fill the second with our data
			$content = $this->cObj->substituteSubpart($content, '###' . strtoupper($this->conf['templateMarker.']['relatedProductSingle']) . '_NOSTOCK###', '');
		} else {
			// When we have no related products, then overwrite the header
			$content = $this->cObj->substituteSubpart($content, '###' . strtoupper($this->conf['templateMarker.']['relatedProductList']) . '###', '');
		}

		$markerArray = array();
		foreach ($hookObjectsArr as $hookObj) {
			if (method_exists($hookObj, 'additionalMarker')) {
				$markerArray = $hookObj->additionalMarker($markerArray, $this);
			}
		}

		$content = $this->cObj->substituteMarkerArray($content, $markerArray);
		return $content;
	}

	/**
	 * Makes the rendering for all articles for a given product
	 * Renders different view, based on viewKind and number of articles
	 *
	 * @param string $viewKind Kind of view for choosing the right template
	 * @param array $conf TSconfig for handling the articles
	 * @param Tx_Commerce_Domain_Model_Product $product The parent product
	 * @param array|string $templateMarkerArray Current template marker array
	 * @param string $template Template text
	 * @return string the content for a single product
	 */
	public function makeArticleView($viewKind, $conf = array(), $product, $templateMarkerArray = '', $template = '') {
		$hookObjectsArr = array();
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/lib/class.tx_commerce_pibase.php']['articleview'])) {
			t3lib_div::deprecationLog('
				hook
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/lib/class.tx_commerce_pibase.php\'][\'articleview\']
				is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0, please use instead
				$GLOBALS[\'TYPO3_CONF_VARS\'][\'EXTCONF\'][\'commerce/Classes/Controller/ListController.php\'][\'articleView\']
			');
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/lib/class.tx_commerce_pibase.php']['articleview'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}
		if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['articleView'])) {
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['commerce/Classes/Controller/ListController.php']['articleView'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
		}

		$count = is_array($product->getArticleUids()) ? count($product->getArticleUids()) : FALSE;

			// do nothing if no articles, BE-user-error, should not happen
		if (strlen($template) < 1) {
			$template = $this->templateCode;
		}

		$templateMarker = array();
		$templateMarkerNostock = array();
		$templateMarkerMoreThanMax = array();
		if (is_array($templateMarkerArray)) {
			foreach ($templateMarkerArray as $v) {
				$templateMarker[] = '###' . strtoupper($v) . '###';
				$templateMarkerNostock[] = '###' . strtoupper($v) . '_NOSTOCK###';
				$templateMarkerMoreThanMax[] = '###' . strtoupper($v) . '_MORETHANMAX###';
			}
		} else {
			$templateMarker[] = '###' . strtoupper($this->conf['templateMarker.'][$viewKind . '_productArticleList']) . '###';
			$templateMarker[] = '###' . strtoupper($this->conf['templateMarker.'][$viewKind . '_productArticleList2']) . '###';
			$templateMarkerNostock[] = '###' . strtoupper($this->conf['templateMarker.'][$viewKind . '_productArticleList']) . '_NOSTOCK###';
			$templateMarkerNostock[] = '###' . strtoupper($this->conf['templateMarker.'][$viewKind . '_productArticleList2']) . '_NOSTOCK###';
		}

		$content = '';
		$markerArray = array();
		if ($product->getRenderMaxArticles() > $product->getArticlesCount()) {
				// Only if the number of articles is smaller than defined
			$templateAttrSelectorDropdown = $this->cObj->getSubpart($this->templateCode, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorDropdown']) . '###');
			$templateAttrSelectorDropdownItem = $this->cObj->getSubpart($templateAttrSelectorDropdown, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorDropdown']) . '_ITEM###');
			$templateAttrSelectorRadiobutton = $this->cObj->getSubpart($this->templateCode, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorRadiobutton']) . '###');
			$templateAttrSelectorRadiobuttonItem = $this->cObj->getSubpart($templateAttrSelectorRadiobutton, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorRadiobutton']) . '_ITEM###');

			$templateCount = count($templateMarker);

			$templateAttr = array();
			if (is_array($this->conf['templateMarker.'][$viewKind . '_selectAttributes.'])) {
				foreach ($this->conf['templateMarker.'][$viewKind . '_selectAttributes.'] as $oneMarker) {
					$templateMarkerAttr = '###' . strtoupper($oneMarker) . '###';
					$tCode = $this->cObj->getSubpart($this->templateCode, $templateMarkerAttr);
					if ($tCode) {
						$templateAttr[] = $tCode;
					}
				}
			} elseif ($this->conf['templateMarker.'][$viewKind . '_selectAttributes']) {
				$templateMarkerAttr = '###' . strtoupper($this->conf['templateMarker.'][$viewKind . '_selectAttributes']) . '###';
				$templateAttr[] = $this->cObj->getSubpart($this->templateCode, $templateMarkerAttr);
			}

			$countTemplateInterations = count($templateAttr);
			if ($this->conf['showHiddenValues'] == 1) {
				$showHiddenValues = TRUE;
			} else {
				$showHiddenValues = FALSE;
			}

			// Parse piVars for values and names of selected attributes
			// define $arrAttSubmit for finding the right article later
			$arrAttSubmit = array();
			foreach ($this->piVars as $key => $val) {
				if (strstr($key, 'attsel_') && $val) {
						// set only if it is the selected product - for listing mode
					if ($this->piVars['changedProductUid'] == $product->getUid() || $this->piVars['showUid'] == $product->getUid()) {
						$arrAttSubmit[(int) substr($key, 7)] = (int) $val;
						if ($this->piVars['attList_' . $product->getUid() . '_changed'] == (int) substr($key, 7)) {
							break;
						}
					}
				}
			}
			if (is_array($arrAttSubmit)) {
				$attributeMatrix = $product->getSelectAttributeValueMatrix($arrAttSubmit);
			} else {
				$attributeMatrix = $product->getSelectAttributeValueMatrix($arrAttSubmit);
			}

			if ($this->conf['allArticles'] || $count == 1) {
				for ($i = 0; $i < $count; $i++) {
					$attributeArray = $product->getAttributeMatrix(array($product->getArticleUid($i)), $this->selectAttributes, $showHiddenValues);

					$attCode = '';
					if (is_array($attributeArray)) {
						$ct = 0;
						foreach ($attributeArray as $attributeUid => $myAttribute) {
							/** @var Tx_Commerce_Domain_Model_Attribute $attributeObj */
							$attributeObj = t3lib_div::makeInstance(
								'Tx_Commerce_Domain_Model_Attribute', $attributeUid,
								$GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']
							);
							$attributeObj->loadData();

							$markerArray['###SELECT_ATTRIBUTES_TITLE###'] = $myAttribute['title'];
							$markerArray['###SELECT_ATTRIBUTES_ICON###'] = $myAttribute['icon'];
							// @todo check where the attribute values are
							if (count($myAttribute['values'])) {
								$v = current(array_splice(each($myAttribute['values']), 1, 1));
								if (is_array($v) && isset($v['value']) && $v['value'] != '') {
									$v = $v['value'];
								}
							} else {
								$v = '';
							}
							$markerArray['###SELECT_ATTRIBUTES_VALUE###'] = $v;
							$markerArray['###SELECT_ATTRIBUTES_UNIT###'] = $myAttribute['unit'];
							$numTemplate = $ct % $countTemplateInterations;
							$attCode .= $this->cObj->substituteMarkerArray($templateAttr[$numTemplate], $markerArray);
							$ct++;
						}
					}

					$markerArray = (array) $this->getArticleMarker($product->getArticle($product->getArticleUid($i)));
					$markerArray['SUBPART_ARTICLE_ATTRIBUTES'] = $this->cObj->stdWrap(
						$this->makeArticleAttributList($product, array($product->getArticleUid($i))),
						$this->conf['singleView.']['articleAttributesList.']
					);
					$markerArray['ARTICLE_SELECT_ATTRIBUTES'] = $this->cObj->stdWrap(
						$attCode,
						$this->conf['singleView.']['articleAttributesSelectList.']
					);

					foreach ($hookObjectsArr as $hookObj) {
						if (method_exists($hookObj, 'additionalMarker')) {
							$markerArray = (array) $hookObj->additionalMarker($markerArray, $this,
								$product->getArticle($product->getArticleUid($i)));
						}
					}
					$templateAttributes = $this->cObj->getSubpart($template, $templateMarker[($i % $templateCount)]);
					/** @var Tx_Commerce_Domain_Model_Article $article */
					$article = $product->getArticle($product->getArticleUid($i));
					if ($this->conf['useStockHandling'] == 1 and $article->getStock() <= 0) {
						$tempTemplate = $this->cObj->getSubpart($template, $templateMarkerNostock[($i % $templateCount)]);
						if ($tempTemplate != '') {
							$templateAttributes = $tempTemplate;
						}
					}

					$content .= $this->cObj->substituteMarkerArray($templateAttributes, $markerArray, '###|###', 1);
				}
			} else {
				$sortedAttributeArray = array();
				$i = 0;
				foreach ($arrAttSubmit as $attrUid => $attrValUid) {
					$sortedAttributeArray[$i]['AttributeUid'] = $attrUid;
					$sortedAttributeArray[$i]['AttributeValue'] = $attrValUid;
					$i++;
				}

				$artId = array_shift($product->getArticlesByAttributeArray($sortedAttributeArray));
				$attCode = '';
				if (is_array($attributeMatrix)) {
					$getVarList = array('catUid','showUid','pointer');
					$getVars = array();
					foreach ($getVarList as $getVar) {
						if (isset($this->piVars[$getVar])) {
							$getVars[$this->prefixId . '[' . $getVar . ']'] = $this->piVars[$getVar];
						}
					}
						// Makes pi1 a user int so form values are updated as one selects an attribute
					$getVars['commerce_pi1_user_int'] = 1;
					$attCode = '<form name="attList_' . $product->getUid() . '" id="attList_' . $product->getUid() . '" action="' .
							$this->pi_getPageLink($GLOBALS['TSFE']->id, '_self', $getVars) . '#att"  method="post">' .
						'<input type="hidden" name="' . $this->prefixId . '[changedProductUid]" value="' . $product->getUid() . '" />' .
						'<input type="hidden" name="' . $this->prefixId . '[attList_' . $product->getUid() . '_changed]" id="attList_' . $product->getUid() . '_changed" value="1" />' .
						'<input type="hidden" name="tx_commerce_pi1[catUid]" value="' . $this->piVars['catUid'] . '" />';
					$markerArray = array();
					foreach ($attributeMatrix as $attrUid => $values) {
						/** @var Tx_Commerce_Domain_Model_Attribute $attributeObj */
						$attributeObj = t3lib_div::makeInstance(
							'Tx_Commerce_Domain_Model_Attribute', $attrUid,
							$GLOBALS['TSFE']->tmpl->setup['config.']['sys_language_uid']
						);
						$attributeObj->loadData();

						// disable the icon mode by default
						$iconMode = FALSE;

						// if the icon mode is enabled in TS check if the iconMode is also enabled
						// for this attribute
						if ($this->conf[$this->handle . '.']['products.']['productAttributes.']['iconMode'] == '1') {
							$iconMode = $attributeObj->isIconmode();
						}
						if ($iconMode) {
							$templateAttrSelector = $templateAttrSelectorRadiobutton;
							$templateAttrSelectorItem = $templateAttrSelectorRadiobuttonItem;
						} else {
							$templateAttrSelector = $templateAttrSelectorDropdown;
							$templateAttrSelectorItem = $templateAttrSelectorDropdownItem;
						}

						$markerArray['###SELECT_ATTRIBUTES_TITLE###'] = $attributeObj->getTitle();
						$markerArray['###SELECT_ATTRIBUTES_ON_CHANGE###'] = 'document.getElementById(\'attList_' . $product->getUid() .
							'_changed\').value = ' . $attrUid . ';document.getElementById(\'attList_' . $product->getUid() . '\').submit();';
						$markerArray['###SELECT_ATTRIBUTES_HTML_ELEMENT_KEY###'] = $this->prefixId . '_' . $attrUid;
						$markerArray['###SELECT_ATTRIBUTES_HTML_ELEMENT_NAME###'] = $this->prefixId . '[attsel_' . $attrUid . ']';

						if (strpos($templateAttrSelector, '###SELECT_ATTRIBUTES_ITEM_TEXT_ALL###') !== FALSE) {
							t3lib_div::deprecationLog('marker ###SELECT_ATTRIBUTES_ITEM_TEXT_ALL### is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0');
						}
						$markerArray['###SELECT_ATTRIBUTES_ITEM_TEXT_ALL###'] = '';
						$markerArray['###SELECT_ATTRIBUTES_UNIT###'] = $attributeObj->getUnit();

						$itemsContent = '';
						$i = 1;
						$attributeValues = $attributeObj->getAllValues(TRUE, $product);
						/** @var Tx_Commerce_Domain_Model_AttributeValue $val */
						foreach ($attributeValues as $val) {
							$markerArrayItem = $markerArray;
							$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_VALUE###'] = $val->getUid();
							$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_NAME###'] = $val->getValue();
							$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_ICON###'] = $this->renderValue(
								$val->getIcon(),
								'IMAGE',
								$this->conf[$this->handle . '.']['products.']['productAttributes.']['fields.']['icon.']
							);

							if ($values[$val->getUid()] == 'selected') {
								$attributeArray[$attrUid] = $val->getUid();
								if ($iconMode) {
									$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_STATUS###'] = 'checked="checked"';
								} else {
									$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_STATUS###'] = 'selected="selected"';
								}
								$i++;
							} elseif ($values[$val->getUid()] == 'disabled') {
								$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_STATUS###'] = 'disabled="disabled"';
							} else {
								$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_STATUS###'] = '';
							}

							if (strpos($templateAttrSelector, '###SELECT_ATTRIBUTES_ITEM_TEXT_ALL###') !== FALSE) {
								t3lib_div::deprecationLog('marker ###SELECT_ATTRIBUTES_VALUE_SELECTED### is deprecated since commerce 1.0.0, it will be removed in commerce 1.4.0, please use ###SELECT_ATTRIBUTES_VALUE_STATUS### instead');
							}
							$markerArrayItem['###SELECT_ATTRIBUTES_VALUE_SELECTED###'] = $markerArrayItem['###SELECT_ATTRIBUTES_VALUE_STATUS###'];
							foreach ($hookObjectsArr as $hookObj) {
								if (method_exists($hookObj, 'additionalAttributeMarker')) {
									$markerArrayItem = $hookObj->additionalAttributeMarker($markerArrayItem, $this, $val->getUid());
								}
							}
							$itemsContent .= $this->cObj->substituteMarkerArray($templateAttrSelectorItem, $markerArrayItem);
							$i++;
						}
						$attributeContent = $this->cObj->substituteMarkerArray($templateAttrSelector, $markerArray);

						if ($iconMode) {
							$attCode .= $this->cObj->substituteSubpart($attributeContent, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorRadiobutton']) . '_ITEM###', $itemsContent);
						} else {
							$attCode .= $this->cObj->substituteSubpart($attributeContent, '###' . strtoupper($this->conf['templateMarker.']['productAttributesSelectorDropdown']) . '_ITEM###', $itemsContent);
						}
					}
					$attCode .= '</form>';
				}

				$markerArray = (array) $this->getArticleMarker($product->getArticle($artId));
				$markerArray['SUBPART_ARTICLE_ATTRIBUTES'] = $this->makeArticleAttributList($product, array($artId));
				$markerArray['ARTICLE_SELECT_ATTRIBUTES'] = $attCode;

				foreach ($hookObjectsArr as $hookObj) {
					if (method_exists($hookObj, 'additionalMarker')) {
						$markerArray = (array) $hookObj->additionalMarker($markerArray, $this, $product->getArticle($artId));
					}
				}

				$templateAttributes = $this->cObj->getSubpart($template, $templateMarker[0]);
				/** @var Tx_Commerce_Domain_Model_Article $article */
				$article = $product->getArticle($artId);
				if ($this->conf['useStockHandling'] == 1 and $article->getStock() <= 0) {
					$tempTemplate = $this->cObj->getSubpart($template, $templateMarkerNostock[0]);
					if ($tempTemplate != '') {
						$templateAttributes = $tempTemplate;
					}
				}

				$content .= $this->cObj->substituteMarkerArray($templateAttributes, $markerArray, '###|###', 1);
			}
		} else {
			// Special Marker and rendering when more articles are existing than
			// are allowed to render
			// @see tx_commerce_products->getNumberOfArticles
			$localContent = $this->cObj->getSubpart($template, reset($templateMarkerMoreThanMax));

			$cat = $this->cat;
			$productCategories = $product->getParentCategories();
			if (! in_array($cat, $productCategories, FALSE)) {
				$cat = $productCategories[0];
			}

			/**
			 * Generate TypoLink Configuration and ad to fields by addTypoLinkToTs
			 */
			if ($this->conf['overridePid']) {
				$typoLinkConf['parameter'] = $this->conf['overridePid'];
			} else {
				$typoLinkConf['parameter'] = $this->pid;
			}
			$typoLinkConf['useCacheHash'] = 1;
			$typoLinkConf['additionalParams'] = $this->argSeparator . $this->prefixId . '[showUid]=' . $product->getUid();
			$typoLinkConf['additionalParams'] .= $this->argSeparator . $this->prefixId . '[catUid]=' . $cat;

			if ($this->basketHashValue) {
				$typoLinkConf['additionalParams'] .= $this->argSeparator . $this->prefixId . '[basketHashValue]=' . $this->basketHashValue;
			}
			$markerArray['LINKTOPRODUCT'] = $this->cObj->typoLink($this->pi_getLL('lang_toproduct'), $typoLinkConf);
			$content = $this->cObj->substituteMarkerArray($localContent, $markerArray, '###|###', 1);

			$markerArray = array();
			foreach ($hookObjectsArr as $hookObj) {
				if (method_exists($hookObj, 'additionalMarkerMakeArticleView')) {
					$markerArray = (array) $hookObj->additionalMarkerMakeArticleView($markerArray, $product, $this);
				}
			}
		}
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);

		return $content;
	}
}

class_alias('Tx_Commerce_Controller_ListController', 'tx_commerce_pi1');

if (defined('TYPO3_MODE') && $GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/commerce/Classes/Controller/ListController.php']) {
	/** @noinspection PhpIncludeInspection */
	require_once($GLOBALS['TYPO3_CONF_VARS'][TYPO3_MODE]['XCLASS']['ext/commerce/Classes/Controller/ListController.php']);
}

?>