<?php
/**
 * @package eZ\Publish\API\Interfaces
 */
namespace eZ\Publish\API\Interfaces;

use eZ\Publish\API\Values\Content\SectionCreateStruct;

use eZ\Publish\API\Values\Content\Content;
use eZ\Publish\API\Values\Content\ContentInfo;
use eZ\Publish\API\Values\Content\Section;
use eZ\Publish\API\Values\Content\Location;
use eZ\Publish\API\Values\Content\SectionUpdateStruct;

/**
 * Section service, used for section operations
 *
 * @package eZ\Publish\API\Interfaces
 */
interface SectionService
{
    /**
     * Creates the a new Section in the content repository
     *
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to create a section
     * @throws \eZ\Publish\API\Exceptions\IllegalArgumentException If the new identifier in $sectionCreateStruct already exists
     *
     * @param SectionCreateStruct $sectionCreateStruct
     *
     * @return Section The newly create section
     */
    public function createSection(SectionCreateStruct $sectionCreateStruct );

    /**
     * Updates the given in the content repository
     *
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to create a section
     * @throws \eZ\Publish\API\Exceptions\IllegalArgumentException If the new identifier already exists (if set in the update struct)
     *
     * @param \eZ\Publish\API\Values\Content\Section $section
     * @param \eZ\Publish\API\Values\Content\SectionUpdateStruct $sectionUpdateStruct
     *
     * @return \eZ\Publish\API\Values\Content\Section
     */
    public function updateSection( Section $section, SectionUpdateStruct $sectionUpdateStruct );

    /**
     * Loads a Section from its id ($sectionId)
     *
     * @throws \eZ\Publish\API\Exceptions\NotFoundException if section could not be found
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to read a section
     *
     * @param int $sectionId
     *
     * @return \eZ\Publish\API\Values\Content\Section
     */
    public function loadSection( $sectionId );

    /**
     * Loads all sections
     *
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to read a section
     *
     * @return array of {@link \eZ\Publish\API\Values\Content\Section}
     */
    public function loadSections();

    /**
     * Loads a Section from its identifier ($sectionIdentifier)
     *
     * @throws \eZ\Publish\API\Exceptions\NotFoundException if section could not be found
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to read a section
     *
     * @param string $sectionIdentifier
     *
     * @return \eZ\Publish\API\Values\Content\Section
     */
    public function loadSectionByIdentifier( $sectionIdentifier );

    /**
     * Counts the contents which $section is assigned to
     *
     * @param \eZ\Publish\API\Values\Content\Section $section
     *
     * @return int
     */
    public function countAssignedContents( Section $section );

    /**
     * assigns the content to the given section
     * this method overrides the current assigned section
     *
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If user does not have access to view provided object
     *
     * @param \eZ\Publish\API\Values\Content\ContentInfo $contentInfo
     * @param \eZ\Publish\API\Values\Content\Section $section
     */
    public function assignSection( ContentInfo $contentInfo, Section $section );


    /**
     * Deletes $section from content repository
     *
     * @throws \eZ\Publish\API\Exceptions\NotFoundException If the specified section is not found
     * @throws \eZ\Publish\API\Exceptions\UnauthorizedException If the current user user is not allowed to delete a section
     * @throws \eZ\Publish\API\Exceptions\BadStateException  if section can not be deleted
     *         because it is still assigned to some contents.
     *
     * @param \eZ\Publish\API\Values\Content\Section $section
     */
    public function deleteSection( Section $section );

    /**
     * instanciates a new SectionCreateStruct
     * 
     * @return \eZ\Publish\API\Values\Content\SectionCreateStruct
     */
    public function newSectionCreateStruct();
    
    /**
     * instanciates a new SectionUpdateStruct
     * 
     * @return \eZ\Publish\API\Values\Content\SectionUpdateStruct
     */
    public function newSectionUpdateStruct();
    
}
