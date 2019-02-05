<?php

/**
 * MIT License. This file is part of the Propel package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Propel\Runtime\ActiveQuery;

use Propel\Runtime\Map\RelationMap;
use Propel\Runtime\Map\TableMap;
use Propel\Runtime\Propel;

/**
 * A ModelJoin is a Join object tied to a RelationMap object
 *
 * @author Francois Zaninotto (Propel)
 */
class ModelJoin extends Join
{
    /**
     * @var \Propel\Runtime\Map\RelationMap
     */
    protected $relationMap;

    /**
     * @var \Propel\Runtime\Map\TableMap|null
     */
    protected $tableMap;

    /**
     * @var \Propel\Runtime\ActiveQuery\ModelJoin|null
     */
    protected $previousJoin;

    /**
     * @param \Propel\Runtime\Map\RelationMap $relationMap
     * @param string|null $leftTableAlias
     * @param string|null $relationAlias
     *
     * @return $this
     */
    public function setRelationMap(RelationMap $relationMap, $leftTableAlias = null, $relationAlias = null)
    {
        $leftCols = $relationMap->getLeftColumns();
        $rightCols = $relationMap->getRightColumns();
        $leftValues = $relationMap->getLocalValues();
        $nbColumns = $relationMap->countColumnMappings();

        for ($i = 0; $i < $nbColumns; $i++) {
            if ($leftValues[$i] !== null) {
                if ($relationMap->getType() === RelationMap::ONE_TO_MANY) {
                    //one-to-many
                    $this->addForeignValueCondition(
                        $rightCols[$i]->getTableName(),
                        $rightCols[$i]->getName(),
                        $relationAlias,
                        $leftValues[$i],
                        Criteria::EQUAL
                    );
                } else {
                    //many-to-one
                    $this->addLocalValueCondition(
                        $leftCols[$i]->getTableName(),
                        $leftCols[$i]->getName(),
                        $leftTableAlias,
                        $leftValues[$i],
                        Criteria::EQUAL
                    );
                }
            } else {
                $this->addExplicitCondition(
                    $leftCols[$i]->getTableName(),
                    $leftCols[$i]->getName(),
                    $leftTableAlias,
                    $rightCols[$i]->getTableName(),
                    $rightCols[$i]->getName(),
                    $relationAlias,
                    Criteria::EQUAL
                );
            }
        }
        $this->relationMap = $relationMap;

        return $this;
    }

    /**
     * @return \Propel\Runtime\Map\RelationMap
     */
    public function getRelationMap()
    {
        return $this->relationMap;
    }

    /**
     * Sets the right tableMap for this join
     *
     * @param \Propel\Runtime\Map\TableMap $tableMap The table map to use
     *
     * @return $this The current join object, for fluid interface
     */
    public function setTableMap(TableMap $tableMap)
    {
        $this->tableMap = $tableMap;

        return $this;
    }

    /**
     * Gets the right tableMap for this join
     *
     * @return \Propel\Runtime\Map\TableMap The table map
     */
    public function getTableMap()
    {
        if ($this->tableMap === null && $this->relationMap !== null) {
            $this->tableMap = $this->relationMap->getRightTable();
        }

        return $this->tableMap;
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\ModelJoin $join
     *
     * @return $this
     */
    public function setPreviousJoin(ModelJoin $join)
    {
        $this->previousJoin = $join;

        return $this;
    }

    /**
     * @return self
     */
    public function getPreviousJoin()
    {
        return $this->previousJoin;
    }

    /**
     * @return bool
     */
    public function isPrimary()
    {
        return $this->previousJoin === null;
    }

    /**
     * @param string $relationAlias
     *
     * @return $this
     */
    public function setRelationAlias($relationAlias)
    {
        $this->setRightTableAlias($relationAlias);

        return $this;
    }

    /**
     * @return string|null
     */
    public function getRelationAlias()
    {
        return $this->getRightTableAlias();
    }

    /**
     * @return bool
     */
    public function hasRelationAlias()
    {
        return $this->hasRightTableAlias();
    }

    /**
     * @return bool
     */
    public function isIdentifierQuotingEnabled()
    {
        return $this->getTableMap()->isIdentifierQuotingEnabled();
    }

    /**
     * This method returns the last related, but already hydrated object up until this join
     * Starting from $startObject and continuously calling the getters to get
     * to the base object for the current join.
     *
     * This method only works if PreviousJoin has been defined,
     * which only happens when you provide dotted relations when calling join
     *
     * @param object $startObject the start object all joins originate from and which has already hydrated
     *
     * @return object The base Object of this join
     */
    public function getObjectToRelate($startObject)
    {
        if ($this->isPrimary()) {
            return $startObject;
        }

        $previousJoin = $this->getPreviousJoin();
        $previousObject = $previousJoin->getObjectToRelate($startObject);
        $method = 'get' . $previousJoin->getRelationMap()->getName();

        return $previousObject->$method();
    }

    /**
     * @param \Propel\Runtime\ActiveQuery\Join|null $join
     *
     * @return bool
     */
    public function equals($join)
    {
        /** @var ModelJoin $join */

        return parent::equals($join)
            && $this->relationMap == $join->getRelationMap()
            && $this->previousJoin == $join->getPreviousJoin()
            && $this->rightTableAlias == $join->getRightTableAlias();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return parent::toString()
            . ' tableMap: ' . ($this->tableMap ? \get_class($this->tableMap) : 'null')
            . ' relationMap: ' . $this->relationMap->getName()
            . ' previousJoin: ' . ($this->previousJoin ? '(' . $this->previousJoin . ')' : 'null')
            . ' relationAlias: ' . $this->rightTableAlias;
    }

    /**
     * Build from a Join.
     * @param Join $join Join to build from.
     * @deprecated Compatibility hack. Do not use.
     */
    public function buildFromJoin(Join $join)
    {
        // copy all join properties
        $reflectedJoin = new \ReflectionClass($join);

        foreach ($reflectedJoin->getProperties() as $reflectedProperty) {
            $reflectedProperty->setAccessible(true);
            $reflectedProperty->setValue($this, $reflectedProperty->getValue($join));
        }

        // set the TableMap for the right side table
        // this works only if all table maps in the application were built at boot
        $rightTableMap = Propel::getDatabaseMap()->getTable($join->getRightTableName());
        $this->setTableMap($rightTableMap);
    }
}
