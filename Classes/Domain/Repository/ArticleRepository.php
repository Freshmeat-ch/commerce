<?php
namespace CommerceTeam\Commerce\Domain\Repository;

/*
 * This file is part of the TYPO3 Commerce project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

/**
 * Database Class for tx_commerce_articles. All database calle should
 * be made by this class. In most cases you should use the methodes
 * provided by tx_commerce_article to get informations for articles.
 *
 * Class \CommerceTeam\Commerce\Domain\Repository\ArticleRepository
 */
class ArticleRepository extends AbstractRepository
{
    /**
     * Database table.
     *
     * @var string
     */
    public $databaseTable = 'tx_commerce_articles';

    /**
     * Database relation table.
     *
     * @var string
     */
    public $databaseAttributeRelationTable = 'tx_commerce_articles_attributes_mm';

    /**
     * Returns the parent Product uid.
     *
     * @param int $uid Article uid
     * @param bool $translationMode Translation mode
     *
     * @return int product uid
     */
    public function getParentProductUid($uid, $translationMode = false)
    {
        $data = parent::getData($uid, $translationMode);
        $result = false;

        if ($data) {
            // Backwards Compatibility
            if ($data['uid_product']) {
                $result = $data['uid_product'];
            } elseif ($data['products_uid']) {
                $result = $data['products_uid'];
            }
        }

        return $result;
    }

    /**
     * Get the highest sorting of all articles belonging to a product
     *
     * @param int $productUid
     * @return int
     */
    public function getHighestSortingByProductUid($productUid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->databaseTable);
        $result = $queryBuilder
            ->select('uid', 'sorting')
            ->from($this->databaseTable)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($productUid, \PDO::PARAM_INT)
                )
            )
            ->orderBy('sorting', 'DESC')
            ->execute()
            ->fetch();

        return is_array($result) && isset($result['sorting']) ? $result['sorting'] : 0;
    }

    /**
     * Gets all prices form database related to this product.
     *
     * @param int $uid Article uid
     * @param int $count Number of Articles for price_scale_amount, default 1
     * @param string $orderField Order field
     *
     * @return array of Price UID
     */
    public function getPrices($uid, $count = 1, $orderField = 'price_net')
    {
        $uid = (int) $uid;
        $count = (int) $count;
        $additionalWhere = '';

        $hookObject = \CommerceTeam\Commerce\Factory\HookFactory::getHook(
            'Domain/Repository/ArticleRepository',
            'getPrices'
        );
        if (is_object($hookObject) && method_exists($hookObject, 'priceOrder')) {
            $orderField = $hookObject->priceOrder($orderField);
        }
        if (is_object($hookObject) && method_exists($hookObject, 'additionalPriceWhere')) {
            $additionalWhere = $hookObject->additionalPriceWhere($this, $uid);
        }

        if ($uid > 0) {
            $priceUidList = [];

            $queryBuilder = $this->getQueryBuilderForTable('tx_commerce_article_prices');
            $queryBuilder
                ->select('uid', 'fe_group')
                ->from('tx_commerce_article_prices')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid_article',
                        $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->lte(
                        'price_scale_amount_start',
                        $queryBuilder->createNamedParameter($count, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->gte(
                        'price_scale_amount_end',
                        $queryBuilder->createNamedParameter($count, \PDO::PARAM_INT)
                    )
                )
                ->orderBy($orderField);

            if ($additionalWhere) {
                $queryBuilder->andWhere($additionalWhere);
            }

            $rows = $queryBuilder
                ->execute()
                ->fetchAll();

            if (!empty($rows)) {
                foreach ($rows as $data) {
                    $feGroups = \TYPO3\CMS\Core\Utility\GeneralUtility::intExplode(',', $data['fe_group'], true);
                    if (!empty($feGroups)) {
                        foreach ($feGroups as $feGroup) {
                            $priceUidList[(string) $feGroup][] = $data['uid'];
                        }
                    } else {
                        $priceUidList[(string) $data['fe_group']][] = $data['uid'];
                    }
                }

                return $priceUidList;
            } else {
                $this->error(
                    'exec_SELECTquery(\'uid\', \'tx_commerce_article_prices\', \'uid_article = \' . ' . $uid .
                    '); returns no Result'
                );

                return [];
            }
        }

        return [];
    }

    /**
     * Returns an array of all scale price amounts.
     *
     * @param int $uid Article uid
     * @param int $count Count
     *
     * @return array of Price UID
     */
    public function getPriceScales($uid, $count = 1)
    {
        $uid = (int) $uid;
        if ($uid > 0) {
            $priceUidList = [];

            $queryBuilder = $this->getQueryBuilderForTable('tx_commerce_article_prices');
            $rows = $queryBuilder
                ->select('uid', 'price_scale_amount_start', 'price_scale_amount_end')
                ->from('tx_commerce_article_prices')
                ->where(
                    $queryBuilder->expr()->eq(
                        'uid_article',
                        $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->gte(
                        'price_scale_amount_start',
                        $queryBuilder->createNamedParameter($count, \PDO::PARAM_INT)
                    )
                )
                ->execute()
                ->fetchAll();

            if (!empty($rows)) {
                foreach ($rows as $data) {
                    $priceUidList[$data['price_scale_amount_start']][$data['price_scale_amount_end']] = $data['uid'];
                }

                return $priceUidList;
            } else {
                $this->error(
                    'SELECT uid FROM tx_commerce_article_prices WHERE uid_article = ' . $uid .
                    ' AND price_scale_amount_start >= ' . $count . '; # returns no Result'
                );

                return [];
            }
        }

        return [];
    }

    /**
     * Gets all attributes from this article.
     *
     * @param int $uid Attribute uid
     *
     * @return array of attributes
     */
    public function getAttributes($uid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->databaseTable);
        $result = $queryBuilder
            ->select('at.*')
            ->from($this->databaseTable, 'ar')
            ->innerJoin('ar', $this->databaseAttributeRelationTable, 'mm', 'ar.uid = mm.uid_local')
            ->innerJoin('mm', 'tx_commerce_attributes', 'at', 'mm.uid_foreign = at.uid')
            ->where(
                $queryBuilder->expr()->eq(
                    'ar.uid',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            )
            ->orderBy('mm.sorting')
            ->execute()
            ->fetchAll();

        return is_array($result) ? $result : [];
    }

    /**
     * @param int $uid
     * @return array
     */
    public function getAttributeRelationsByArticleUid($uid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->databaseAttributeRelationTable);
        $result = $queryBuilder
            ->select('*')
            ->from($this->databaseAttributeRelationTable)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid_local',
                    $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetchAll();
        return is_array($result) ? $result : [];
    }

    /**
     * @param int $articleUid
     * @param int $attributeUid
     * @return array
     */
    public function findAttributeRelationsByArticleAndAttribute($articleUid, $attributeUid)
    {
        $relations = (array) $this->getDatabaseConnection()->exec_SELECTgetRows(
            '*',
            $this->databaseAttributeRelationTable,
            'uid_local = ' . (int) $articleUid . ' AND uid_foreign = ' . (int) $attributeUid
        );

        return $relations;
    }

    /**
     * Returns the attribute Value from the given Article attribute pair.
     *
     * @param int $uid Article UID
     * @param int $attributeUid Attribute UID
     * @param bool $valueListAsUid If true, returns not the value from
     *      the valuelist, instead the uid
     *
     * @return string
     */
    public function getAttributeValue($uid, $attributeUid, $valueListAsUid = false)
    {
        $uid = (int) $uid;
        $attributeUid = (int) $attributeUid;

        if ($uid > 0) {
            // First select attribute, to detecxt if is valuelist
            $database = $this->getDatabaseConnection();

            $returnData = $database->exec_SELECTgetSingleRow(
                'DISTINCT uid, has_valuelist',
                'tx_commerce_attributes',
                'uid = ' . (int) $attributeUid . $this->enableFields('tx_commerce_attributes')
            );
            if (!empty($returnData)) {
                if ($returnData['has_valuelist'] == 1) {
                    // Attribute has a valuelist, so do separate query
                    $valueData = $database->exec_SELECTgetSingleRow(
                        'DISTINCT tx_commerce_attribute_values.value, tx_commerce_attribute_values.uid',
                        $this->databaseAttributeRelationTable . ', tx_commerce_attribute_values',
                        $this->databaseAttributeRelationTable . '.uid_valuelist = tx_commerce_attribute_values.uid'.
                        ' AND uid_local = ' . $uid .
                        ' AND uid_foreign = ' . $attributeUid
                    );
                    if (!empty($valueData)) {
                        if ($valueListAsUid == true) {
                            return $valueData['uid'];
                        } else {
                            return $valueData['value'];
                        }
                    }
                } else {
                    // attribute has no valuelist, so do normal query
                    $valueData = $database->exec_SELECTgetSingleRow(
                        'DISTINCT value_char, default_value',
                        $this->databaseAttributeRelationTable,
                        'uid_local = ' . $uid . ' AND uid_foreign = ' . $attributeUid
                    );
                    if (!empty($valueData)) {
                        if ($valueData['value_char']) {
                            return $valueData['value_char'];
                        } else {
                            return $valueData['default_value'];
                        }
                    } else {
                        $this->error('More than one Value for thsi attribute');
                    }
                }
            } else {
                $this->error('Could not get Attribute for call');
            }
        } else {
            $this->error('no Uid');
        }

        return '';
    }

    /**
     * No return value as the relation table has no primary key to use as identifier of the new record
     *
     * @param int $articleUid
     * @param int $attributeUid
     * @param int $productUid
     * @param int $sorting
     * @param int $valueList
     * @param string $characterValue
     * @param float $defaultValue
     * @return void
     */
    public function addAttributeRelation(
        $articleUid,
        $attributeUid,
        $productUid = 0,
        $sorting = 0,
        $valueList = 0,
        $characterValue = '',
        $defaultValue = 0.00
    ) {
        $data['uid_local'] = $articleUid;
        $data['uid_foreign'] = $attributeUid;
        $data['uid_product'] = $productUid;
        $data['sorting'] = $sorting;
        $data['uid_valuelist'] = $valueList;
        $data['value_char'] = $characterValue;
        $data['default_value'] = $defaultValue;

        $this->getDatabaseConnection()->exec_INSERTquery(
            $this->databaseAttributeRelationTable,
            $data
        );
    }

    /**
     * Rreturns the supplier name to a given UID, selected from tx_commerce_supplier.
     *
     * @param int $supplierUid Supplier uid
     *
     * @return string Supplier name
     */
    public function getSupplierName($supplierUid)
    {
        $database = $this->getDatabaseConnection();

        if ($supplierUid > 0) {
            $returnData = $database->exec_SELECTgetSingleRow(
                'title',
                'tx_commerce_supplier',
                'uid = ' . (int) $supplierUid
            );
            if (!empty($returnData)) {
                return $returnData['title'];
            }
        }

        return '';
    }

    /**
     * Find article by classname.
     *
     * @param string $classname Classname
     *
     * @return array
     */
    public function findByClassname($classname)
    {
        $row = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            '*',
            'tx_commerce_articles',
            'classname = ' . $this->getDatabaseConnection()->fullQuoteStr($classname, $this->databaseTable)
            . $this->enableFields()
        );

        return is_array($row) ? $row : [];
    }

    /**
     * @param int $productUid
     * @param string $orderBy
     * @return array
     */
    public function findByProductUid($productUid, $orderBy = 'sorting')
    {
        $articles = (array) $this->getDatabaseConnection()->exec_SELECTgetRows(
            '*',
            $this->databaseTable,
            'uid_product = ' . $productUid . $this->enableFields(),
            '',
            $orderBy
        );

        return $articles;
    }

    /**
     * Finds articles by product uid and returns only the uids as flat array
     *
     * @param int $productUid
     * @param string $orderBy
     *
     * @return array
     */
    public function findUidsByProductUid($productUid, $orderBy = 'sorting')
    {
        $articles = (array) $this->getDatabaseConnection()->exec_SELECTgetRows(
            'uid',
            $this->databaseTable,
            'uid_product = ' . $productUid . $this->enableFields(),
            '',
            $orderBy,
            '',
            'uid'
        );

        return array_keys($articles);
    }

    /**
     * @param int $articleUid
     * @param int $attributeUid
     * @param array $data
     */
    public function updateRelation($articleUid, $attributeUid, array $data)
    {
        $this->getDatabaseConnection()->exec_UPDATEquery(
            'tx_commerce_articles_attributes_mm',
            'uid_local = ' . (int) $articleUid . ' AND uid_foreign = ' . (int) $attributeUid,
            $data
        );
    }

    /**
     * @param int $productUidFrom
     * @param int $productUidTo
     * @return string
     */
    public function updateProductUid($productUidFrom, $productUidTo)
    {
        $this->getDatabaseConnection()->exec_UPDATEquery(
            $this->databaseTable,
            'uid_product = ' . (int) $productUidFrom,
            ['uid_product' => (int) $productUidTo]
        );

        return $this->getDatabaseConnection()->sql_error();
    }

    /**
     * @param int $articleUid
     * @param int $attributeId
     */
    public function removeAttributeRelation($articleUid, $attributeId)
    {
        $this->getDatabaseConnection()->exec_DELETEquery(
            $this->databaseAttributeRelationTable,
            'uid_local = ' . (int) $articleUid . ' AND uid_foreign = ' . (int) $attributeId
        );
    }

    /**
     * Set delete flag and timestamp to current date for given articles
     *
     * @param array $articleUids
     */
    public function deleteByUids(array $articleUids)
    {
        $updateValues = [
            'tstamp' => $GLOBALS['EXEC_TIME'],
            'deleted' => 1,
        ];

        $this->getDatabaseConnection()->exec_UPDATEquery(
            $this->databaseTable,
            'uid IN (' . implode(',', $articleUids) . ') OR l18n_parent IN (' . implode(',', $articleUids) . ')',
            $updateValues
        );
    }
}
