<?php
/**
 * File containing FieldTypeService class.
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 * @package eZ\Publish\API\Repository
 */

namespace eZ\Publish\Core\Repository;

use eZ\Publish\API\Repository\FieldTypeService as FieldTypeServiceInterface;
use eZ\Publish\SPI\FieldType\FieldType as SPIFieldType;
use eZ\Publish\Core\Base\Exceptions\NotFound\FieldTypeNotFoundException;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentException;
use eZ\Publish\Core\Repository\Values\ContentType\FieldType;

/**
 * An implementation of this class provides access to FieldTypes
 *
 * @package eZ\Publish\API\Repository
 * @see eZ\Publish\API\Repository\FieldType
 */
class FieldTypeService implements FieldTypeServiceInterface
{
    /**
     * @var Helper\FieldTypeRegistry
     */
    protected $fieldTypeRegistry;

    /**
     * Holds an array of FieldType objects to avoid re creating them all the time from SPI variants
     *
     * @var \eZ\Publish\API\Repository\FieldType[]
     */
    protected $fieldTypes = array();

    /**
     * Setups service with reference to repository object that created it & corresponding handler
     *
     * @param Helper\FieldTypeRegistry $fieldTypeRegistry Registry for SPI FieldTypes
     */
    public function __construct( Helper\FieldTypeRegistry $fieldTypeRegistry )
    {
        $this->fieldTypeRegistry = $fieldTypeRegistry;
    }

    /**
     * Returns a list of all field types.
     *
     * @return \eZ\Publish\API\Repository\FieldType[]
     */
    public function getFieldTypes()
    {
        foreach ( $this->fieldTypeRegistry->getFieldTypes() as $identifier => $spiFieldType )
        {
            if ( isset( $this->fieldTypes[$identifier] ) )
                continue;

            $this->fieldTypes[$identifier] = new FieldType( $spiFieldType );
        }

        return $this->fieldTypes;
    }

    /**
     * Returns the FieldType registered with the given identifier
     *
     * @param string $identifier
     *
     * @return \eZ\Publish\API\Repository\FieldType
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException
     *         if there is no FieldType registered with $identifier
     */
    public function getFieldType( $identifier )
    {
        if ( isset( $this->fieldTypes[$identifier] ) )
        {
            return $this->fieldTypes[$identifier];
        }

        return ( $this->fieldTypes[$identifier] = new FieldType( $this->fieldTypeRegistry->getFieldType( $identifier ) ) );
    }

    /**
     * Returns if there is a FieldType registered under $identifier
     *
     * @param string $identifier
     *
     * @return boolean
     */
    public function hasFieldType( $identifier )
    {
        return $this->fieldTypeRegistry->hasFieldType( $identifier );
    }
}
