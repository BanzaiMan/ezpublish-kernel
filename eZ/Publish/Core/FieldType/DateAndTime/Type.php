<?php
/**
 * File containing the DateTime class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\Core\FieldType\DateAndTime;

use eZ\Publish\Core\FieldType\FieldType;
use eZ\Publish\Core\Base\Exceptions\InvalidArgumentType;
use DateTime;
use eZ\Publish\Core\FieldType\ValidationError;
use eZ\Publish\SPI\FieldType\Value as SPIValue;
use eZ\Publish\Core\FieldType\Value as BaseValue;

class Type extends FieldType
{
    /**
     * Default value types
     */
    const DEFAULT_EMPTY = 0,
          DEFAULT_CURRENT_DATE = 1,
          DEFAULT_CURRENT_DATE_ADJUSTED = 2;

    protected $settingsSchema = array(
        "useSeconds" => array(
            "type" => "bool",
            "default" => false
        ),
        // One of the DEFAULT_* class constants
        "defaultType" => array(
            "type" => "choice",
            "default" => self::DEFAULT_EMPTY
        ),
        /*
         * @var \DateInterval
         * Used only if defaultValueType is set to DEFAULT_CURRENT_DATE_ADJUSTED
         */
        "dateInterval" => array(
            "type" => "dateInterval",
            "default" => null
        )
    );

    /**
     * Returns the field type identifier for this field type
     *
     * @return string
     */
    public function getFieldTypeIdentifier()
    {
        return "ezdatetime";
    }

    /**
     * Returns the name of the given field value.
     *
     * It will be used to generate content name and url alias if current field is designated
     * to be used in the content name/urlAlias pattern.
     *
     * @param \eZ\Publish\Core\FieldType\DateAndTime\Value $value
     *
     * @return string
     */
    public function getName( SPIValue $value )
    {
        if ( $this->isEmptyValue( $value ) )
        {
            return "";
        }

        return $value->value->format( 'D Y-d-m H:i:s' );
    }

    /**
     * Returns the fallback default value of field type when no such default
     * value is provided in the field definition in content types.
     *
     * @return \eZ\Publish\Core\FieldType\DateAndTime\Value
     */
    public function getEmptyValue()
    {
        return new Value;
    }

    /**
     * Inspects given $inputValue and potentially converts it into a dedicated value object.
     *
     * @param string|int|\DateTime|\eZ\Publish\Core\FieldType\DateAndTime\Value $inputValue
     *
     * @return \eZ\Publish\Core\FieldType\DateAndTime\Value The potentially converted and structurally plausible value.
     */
    protected function createValueFromInput( $inputValue )
    {
        if ( is_string( $inputValue ) )
        {
            $inputValue = Value::fromString( $inputValue );
        }

        if ( is_int( $inputValue ) )
        {
            $inputValue = Value::fromTimestamp( $inputValue );
        }

        if ( $inputValue instanceof \DateTime )
        {
            $inputValue = new Value( $inputValue );
        }

        return $inputValue;
    }

    /**
     * Throws an exception if value structure is not of expected format.
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException If the value does not match the expected structure.
     *
     * @param \eZ\Publish\Core\FieldType\DateAndTime\Value $value
     *
     * @return void
     */
    protected function checkValueStructure( BaseValue $value )
    {
        if ( !$value->value instanceof DateTime )
        {
            throw new InvalidArgumentType(
                '$value->value',
                'DateTime',
                $value->value
            );
        }
    }

    /**
     * Returns information for FieldValue->$sortKey relevant to the field type.
     *
     * @param \eZ\Publish\Core\FieldType\DateAndTime\Value $value
     *
     * @return array
     */
    protected function getSortInfo( BaseValue $value )
    {
        if ( $value->value === null )
        {
            return null;
        }
        return $value->value->getTimestamp();
    }

    /**
     * Converts an $hash to the Value defined by the field type
     *
     * @param mixed $hash Null or associative array containing timestamp and optionally date in RFC850 format.
     *
     * @return \eZ\Publish\Core\FieldType\DateAndTime\Value $value
     */
    public function fromHash( $hash )
    {
        if ( $hash === null )
        {
            return $this->getEmptyValue();
        }

        if ( isset( $hash['rfc850'] ) && $hash['rfc850'] )
        {
            return Value::fromString( $hash['rfc850'] );
        }

        return Value::fromTimestamp( (int)$hash['timestamp'] );
    }

    /**
     * Converts a $Value to a hash
     *
     * @param \eZ\Publish\Core\FieldType\DateAndTime\Value $value
     *
     * @return mixed
     */
    public function toHash( SPIValue $value )
    {
        if ( $this->isEmptyValue( $value ) )
        {
            return null;
        }

        if ( $value->value instanceof DateTime )
        {
            return array(
                'timestamp' => $value->value->getTimestamp(),
                'rfc850'    => $value->value->format( \DateTime::RFC850  ),
            );
        }

        return array(
            'timestamp' => 0,
            'rfc850'    => null,
        );
    }

    /**
     * Returns whether the field type is searchable
     *
     * @return boolean
     */
    public function isSearchable()
    {
        return true;
    }

    /**
     * Validates the fieldSettings of a FieldDefinitionCreateStruct or FieldDefinitionUpdateStruct
     *
     * @param mixed $fieldSettings
     *
     * @return \eZ\Publish\SPI\FieldType\ValidationError[]
     */
    public function validateFieldSettings( $fieldSettings )
    {
        $validationErrors = array();

        foreach ( $fieldSettings as $name => $value )
        {
            if ( isset( $this->settingsSchema[$name] ) )
            {
                switch ( $name )
                {
                    case "useSeconds":
                        if ( !is_bool( $value ) )
                        {
                            $validationErrors[] = new ValidationError(
                                "Setting '%setting%' value must be of boolean type",
                                null,
                                array(
                                    "setting" => $name
                                )
                            );
                        }
                        break;
                    case "defaultType":
                        $definedTypes = array(
                            self::DEFAULT_EMPTY,
                            self::DEFAULT_CURRENT_DATE,
                            self::DEFAULT_CURRENT_DATE_ADJUSTED
                        );
                        if ( !in_array( $value, $definedTypes, true ) )
                        {
                            $validationErrors[] = new ValidationError(
                                "Setting '%setting%' is of unknown type",
                                null,
                                array(
                                    "setting" => $name
                                )
                            );
                        }
                        break;
                    case "dateInterval":
                        if ( isset( $value ) )
                        {
                            if ( isset( $fieldSettings["defaultType"] ) &&
                                $fieldSettings["defaultType"] !== self::DEFAULT_CURRENT_DATE_ADJUSTED )
                            {
                                $validationErrors[] = new ValidationError(
                                    "Setting '%setting%' can be used only when setting '%defaultType%' is set to '%DEFAULT_CURRENT_DATE_ADJUSTED%'",
                                    null,
                                    array(
                                        "setting" => $name,
                                        "defaultType" => "defaultType",
                                        "DEFAULT_CURRENT_DATE_ADJUSTED" => "DEFAULT_CURRENT_DATE_ADJUSTED"
                                    )
                                );
                            }
                            else if ( !( $value instanceof \DateInterval ) )
                            {
                                $validationErrors[] = new ValidationError(
                                    "Setting '%setting%' value must be an instance of 'DateInterval' class",
                                    null,
                                    array(
                                        "setting" => $name
                                    )
                                );
                            }
                        }
                        break;
                }
            }
            else
            {
                $validationErrors[] = new ValidationError(
                    "Setting '%setting%' is unknown",
                    null,
                    array(
                        "setting" => $name
                    )
                );
            }
        }

        return $validationErrors;
    }

    /**
     * Converts the given $fieldSettings to a simple hash format
     *
     * This is the default implementation, which just returns the given
     * $fieldSettings, assuming they are already in a hash format. Overwrite
     * this in your specific implementation, if necessary.
     *
     * @param mixed $fieldSettings
     *
     * @return array|hash|scalar|null
     */
    public function fieldSettingsToHash( $fieldSettings )
    {
        $fieldSettingsHash = parent::fieldSettingsToHash( $fieldSettings );

        if ( isset( $fieldSettingsHash['dateInterval'] ) )
        {
            $fieldSettingsHash['dateInterval'] = $fieldSettingsHash['dateInterval']->format(
                'P%r%yY%r%mM%r%dDT%r%hH%iM%r%sS'
            );
        }

        return $fieldSettingsHash;
    }

    /**
     * Converts the given $fieldSettingsHash to field settings of the type
     *
     * This is the reverse operation of {@link fieldSettingsToHash()}.
     *
     * This is the default implementation, which just returns the given
     * $fieldSettingsHash, assuming the supported field settings are already in
     * a hash format. Overwrite this in your specific implementation, if
     * necessary.
     *
     * @param array|hash|scalar|null $fieldSettingsHash
     *
     * @return mixed
     */
    public function fieldSettingsFromHash( $fieldSettingsHash )
    {
        $fieldSettings = parent::fieldSettingsFromHash( $fieldSettingsHash );

        if ( isset( $fieldSettings['dateInterval'] ) )
        {
            $fieldSettings['dateInterval'] = new \DateInterval( $fieldSettings['dateInterval'] );
        }

        return $fieldSettings;
    }
}
