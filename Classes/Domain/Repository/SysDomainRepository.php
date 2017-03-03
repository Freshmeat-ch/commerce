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
 * Class SupplierRepository
 *
 * @package CommerceTeam\Commerce\Domain\Repository
 */
class SysDomainRepository extends AbstractRepository
{
    /**
     * @var string
     */
    protected $databaseTable = 'sys_domain';

    /**
     * @param int $pid
     *
     * @return string
     */
    public function findFirstByPid($pid)
    {
        $queryBuilder = $this->getQueryBuilderForTable($this->databaseTable);
        $result = $queryBuilder
            ->select('domainName')
            ->from($this->databaseTable)
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($pid, \PDO::PARAM_INT)
                )
            )
            ->orderBy('sorting')
            ->execute()
            ->fetch();
        return !empty($result) ? htmlspecialchars($result['domainName']) : '';
    }
}
