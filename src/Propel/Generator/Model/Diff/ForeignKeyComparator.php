<?php

/**
 * This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Generator\Model\Diff;

use Propel\Generator\Model\ForeignKey;

/**
 * Service class for comparing ForeignKey objects
 * Heavily inspired by Doctrine2's Migrations
 * (see http://github.com/doctrine/dbal/tree/master/lib/Doctrine/DBAL/Schema/)
 *
 */
class ForeignKeyComparator
{
    /**
     * Compute the difference between two Foreign key objects
     *
     * @param ForeignKey $fromFk
     * @param ForeignKey $toFk
     *
     * @param boolean $caseInsensitive Whether the comparison is case insensitive.
     *                                 False by default.
     *
     * @return boolean false if the two fks are similar, true if they have differences
     */
    public static function computeDiff(ForeignKey $fromFk, ForeignKey $toFk, $caseInsensitive = false)
    {
        // Check for differences in local and remote table
        $test = $caseInsensitive ?
            strtolower($fromFk->getTableName()) !== strtolower($toFk->getTableName()) :
            $fromFk->getTableName() !== $toFk->getTableName()
        ;

        if ($test) {
            return true;
        }

        $test = $caseInsensitive ?
            strtolower($fromFk->getForeignTableName()) !== strtolower($toFk->getForeignTableName()) :
            $fromFk->getForeignTableName() !== $toFk->getForeignTableName()
        ;

        if ($test) {
            return true;
        }

        // compare columns
        $fromFkLocalColumns = $fromFk->getLocalColumns();
        sort($fromFkLocalColumns);
        $toFkLocalColumns = $toFk->getLocalColumns();
        sort($toFkLocalColumns);
        if (array_map('strtolower', $fromFkLocalColumns) !== array_map('strtolower', $toFkLocalColumns)) {
            return true;
        }
        $fromFkForeignColumns = $fromFk->getForeignColumns();
        sort($fromFkForeignColumns);
        $toFkForeignColumns = $toFk->getForeignColumns();
        sort($toFkForeignColumns);
        if (array_map('strtolower', $fromFkForeignColumns) !== array_map('strtolower', $toFkForeignColumns)) {
            return true;
        }

        $fromOnUpdate = $fromFk->getOnUpdate();

        if ($fromOnUpdate === ForeignKey::NOACTION || empty($fromOnUpdate)) {
            $fromOnUpdate = ForeignKey::RESTRICT;
        }

        $toOnUpdate = $toFk->getOnUpdate();

        if ($toOnUpdate === ForeignKey::NOACTION || empty($toOnUpdate)) {
            $toOnUpdate = ForeignKey::RESTRICT;
        }

        $fromOnDelete = $fromFk->getOnDelete();

        if ($fromOnDelete === ForeignKey::NOACTION || empty($fromOnDelete)) {
            $fromOnDelete = ForeignKey::RESTRICT;
        }

        $toOnDelete = $toFk->getOnDelete();

        if ($toOnDelete === ForeignKey::NOACTION || empty($toOnDelete)) {
            $toOnDelete = ForeignKey::RESTRICT;
        }

        // compare on
        if ($fromFk->normalizeFKey($fromOnUpdate) !== $toFk->normalizeFKey($toOnUpdate)) {
            return true;
        }
        if ($fromFk->normalizeFKey($fromOnDelete) !== $toFk->normalizeFKey($toOnDelete)) {
            return true;
        }

        // compare skipSql
        return $fromFk->isSkipSql() !== $toFk->isSkipSql();
    }

}
