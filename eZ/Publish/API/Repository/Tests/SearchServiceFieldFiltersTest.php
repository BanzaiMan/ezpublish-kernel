<?php
/**
 * File containing the SearchServiceFieldFiltersTest class
 *
 * @copyright Copyright (C) eZ Systems AS. All rights reserved.
 * @license For full copyright and license information view LICENSE file distributed with this source code.
 * @version //autogentag//
 */

namespace eZ\Publish\API\Repository\Tests;

use eZ\Publish\API\Repository\Tests\SetupFactory\LegacyElasticsearch;
use eZ\Publish\API\Repository\Values\Content\Query;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion;
use eZ\Publish\API\Repository\Values\Content\Query\Criterion\Operator;
use eZ\Publish\API\Repository\Values\Content\Query\SortClause;

/**
 * Test case for field filtering operations in the SearchService.
 *
 * @see eZ\Publish\API\Repository\SearchService
 * @group integration
 * @group search
 * @group field_filter
 */
class SearchServiceFieldFiltersTest extends BaseTest
{
    public function setUp()
    {
        $setupFactory = $this->getSetupFactory();

        if ( !$setupFactory instanceof LegacyElasticsearch )
        {
            $this->markTestIncomplete( "ATM implemented only for Elasticsearch storage" );
        }

        parent::setUp();
    }

    protected function addMapLocationToFolderType()
    {
        $repository = $this->getRepository();
        $contentTypeService = $repository->getContentTypeService();

        $contentTypeDraft = $contentTypeService->createContentTypeDraft(
            $contentTypeService->loadContentTypeByIdentifier( 'folder' )
        );

        $fieldDefinitionCreateStruct = $contentTypeService->newFieldDefinitionCreateStruct(
            "map_location",
            "ezgmaplocation"
        );
        $fieldDefinitionCreateStruct->names = array( "eng-GB" => "Map location field" );
        $fieldDefinitionCreateStruct->fieldGroup = "main";
        $fieldDefinitionCreateStruct->position = 1;
        $fieldDefinitionCreateStruct->isTranslatable = true;
        $fieldDefinitionCreateStruct->isSearchable = true;

        $contentTypeService->addFieldDefinition( $contentTypeDraft, $fieldDefinitionCreateStruct );

        $contentTypeService->publishContentTypeDraft( $contentTypeDraft );
    }

    /**
     * @param string $languageCode1
     * @param string $name1
     * @param string $languageCode2
     * @param string $name2
     * @param string $mainLanguageCode
     * @param boolean $alwaysAvailable
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    protected function createTestFolderWithName(
        $languageCode1,
        $name1,
        $languageCode2,
        $name2,
        $mainLanguageCode,
        $alwaysAvailable = false
    )
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        $locationService = $repository->getLocationService();

        $contentCreateStruct = $contentService->newContentCreateStruct(
            $contentTypeService->loadContentTypeByIdentifier( 'folder' ),
            $mainLanguageCode
        );
        $contentCreateStruct->alwaysAvailable = $alwaysAvailable;

        $contentCreateStruct->setField( "name", $name1, $languageCode1 );
        $contentCreateStruct->setField( "name", $name2, $languageCode2 );

        $content = $contentService->publishVersion(
            $contentService->createContent(
                $contentCreateStruct,
                array( $locationService->newLocationCreateStruct( 2 ) )
            )->versionInfo
        );

        return $content;
    }

    /**
     * @param string $languageCode1
     * @param string $location1
     * @param string $languageCode2
     * @param string $location2
     * @param string $mainLanguageCode
     * @param boolean $alwaysAvailable
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    protected function createTestFolderWithLocation(
        $languageCode1,
        $location1,
        $languageCode2,
        $location2,
        $mainLanguageCode,
        $alwaysAvailable = false
    )
    {
        $repository = $this->getRepository();
        $contentService = $repository->getContentService();
        $contentTypeService = $repository->getContentTypeService();
        $locationService = $repository->getLocationService();

        $contentCreateStruct = $contentService->newContentCreateStruct(
            $contentTypeService->loadContentTypeByIdentifier( 'folder' ),
            $mainLanguageCode
        );
        $contentCreateStruct->alwaysAvailable = $alwaysAvailable;

        $contentCreateStruct->setField(
            "map_location",
            array(
                "latitude" => $location1[0],
                "longitude" => $location1[1],
                "address" => "",
            ),
            $languageCode1
        );
        $contentCreateStruct->setField(
            "map_location",
            array(
                "latitude" => $location2[0],
                "longitude" => $location2[1],
                "address" => "",
            ),
            $languageCode2
        );

        $content = $contentService->publishVersion(
            $contentService->createContent(
                $contentCreateStruct,
                array( $locationService->newLocationCreateStruct( 2 ) )
            )->versionInfo
        );

        return $content;
    }

    /**
     * Test for the findContent() method.
     *
     * Demonstrating how mismatch between field filters and language filtering criteria
     * when using non-field filtering criteria can cause NotFound exception.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     * @expectedException \eZ\Publish\API\Repository\Exceptions\NotFoundException
     */
    public function testFieldFiltersCauseNotFoundException()
    {
        // Content with id=54 exists only in eng-US language!
        $query = new Query(
            array(
                "filter" => new Criterion\ContentId( 54 ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        // The content will be found, but field filtering in the service will cause the exception.
        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchService->findContent( $query, $fieldFilters );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguageAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguage( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguageComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguageEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", false );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "one" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\FullText( "two" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguageAll()
    {
        $this->testFullTextQueryLanguageAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguage()
    {
        $this->testFullTextQueryLanguage( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguageComplement()
    {
        $this->testFullTextQueryLanguageComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguageEmpty()
    {
        $this->testFullTextQueryLanguageEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguageAlwaysAvailable()
    {
        $this->testFullTextQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterLanguageAlwaysAvailableComplement()
    {
        $this->testFullTextQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterAlwaysAvailable()
    {
        $this->testFullTextQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterAlwaysAvailableComplement()
    {
        $this->testFullTextQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFullTextFilterAlwaysAvailableEmpty()
    {
        $this->testFullTextQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "two" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "two" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "two" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "two" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", false );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "one" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::EQ, "two" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", false );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\Field( "name", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }


    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterAll()
    {
        $this->testFieldQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilter()
    {
        $this->testFieldQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterComplement()
    {
        $this->testFieldQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterEmpty()
    {
        $this->testFieldQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterLanguageAlwaysAvailable()
    {
        $this->testFieldQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterLanguageAlwaysAvailableComplement()
    {
        $this->testFieldQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterAlwaysAvailable()
    {
        $this->testFieldQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterAlwaysAvailableComplement()
    {
        $this->testFieldQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldFilterAlwaysAvailableEmpty()
    {
        $this->testFieldQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterAll()
    {
        $this->testFieldRangeQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilter()
    {
        $this->testFieldRangeQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterComplement()
    {
        $this->testFieldRangeQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterEmpty()
    {
        $this->testFieldRangeQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterLanguageAlwaysAvailable()
    {
        $this->testFieldRangeQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterLanguageAlwaysAvailableComplement()
    {
        $this->testFieldRangeQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterAlwaysAvailable()
    {
        $this->testFieldRangeQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterAlwaysAvailableComplement()
    {
        $this->testFieldRangeQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testFieldRangeFilterAlwaysAvailableEmpty()
    {
        $this->testFieldRangeQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field(
                    "short_description",
                    Operator::EQ,
                    "two"
                ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field(
                    "short_description",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field(
                    "short_description",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field(
                    "short_description",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", false );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::EQ, "one" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::EQ, "one" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::EQ, "two" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", false );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-US", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );

        $query = new Query(
            array(
                $type => $criterion = new Criterion\Field( "short_description", Operator::GTE, "z" ),
            )
        );

        $criterion->setCustomField( "folder", "short_description", "folder_name_value_ms" );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterAll()
    {
        $this->testModifiedFieldQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilter()
    {
        $this->testModifiedFieldQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterComplement()
    {
        $this->testModifiedFieldQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterEmpty()
    {
        $this->testModifiedFieldQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterLanguageAlwaysAvailable()
    {
        $this->testModifiedFieldQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterLanguageAlwaysAvailableComplement()
    {
        $this->testModifiedFieldQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterAlwaysAvailable()
    {
        $this->testModifiedFieldQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterAlwaysAvailableComplement()
    {
        $this->testModifiedFieldQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldFilterAlwaysAvailableEmpty()
    {
        $this->testModifiedFieldQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterAll()
    {
        $this->testModifiedFieldRangeQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilter()
    {
        $this->testModifiedFieldRangeQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterComplement()
    {
        $this->testModifiedFieldRangeQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterEmpty()
    {
        $this->testModifiedFieldRangeQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterLanguageAlwaysAvailable()
    {
        $this->testModifiedFieldRangeQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterLanguageAlwaysAvailableComplement()
    {
        $this->testModifiedFieldRangeQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterAlwaysAvailable()
    {
        $this->testModifiedFieldRangeQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterAlwaysAvailableComplement()
    {
        $this->testModifiedFieldRangeQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testModifiedFieldRangeFilterAlwaysAvailableEmpty()
    {
        $this->testModifiedFieldRangeQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB"
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB"
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
                'sortClauses' => array(
                    new SortClause\MapLocationDistance(
                        "folder",
                        "map_location",
                        10,
                        10,
                        Query::SORT_ASC,
                        "eng-GB"
                    ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB"
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB"
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB"
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB"
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB"
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB"
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB",
            false
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-US",
            true
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
                'sortClauses' => array(
                    new SortClause\MapLocationDistance(
                        "folder",
                        "map_location",
                        10,
                        10,
                        Query::SORT_ASC,
                        "eng-GB"
                    ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB",
            true
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB",
            false
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
                'sortClauses' => array(
                    new SortClause\MapLocationDistance(
                        "folder",
                        "map_location",
                        10,
                        10,
                        Query::SORT_ASC,
                        "eng-GB"
                    ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB",
            true
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-US",
            true
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
                'sortClauses' => array(
                    new SortClause\MapLocationDistance(
                        "folder",
                        "map_location",
                        10,
                        10,
                        Query::SORT_ASC,
                        "eng-GB"
                    ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-GB",
            true
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB",
            true
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $this->addMapLocationToFolderType();

        $content1 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 20, 20 ),
            "eng-US",
            array( 30, 30 ),
            "eng-US",
            true
        );
        $content2 = $this->createTestFolderWithLocation(
            "eng-GB",
            array( 30, 30 ),
            "eng-US",
            array( 20, 20 ),
            "eng-GB",
            true
        );

        $query = new Query(
            array(
                $type => new Criterion\MapLocationDistance(
                    "map_location",
                    Criterion\Operator::LTE,
                    2000,
                    10,
                    10
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterAll()
    {
        $this->testMapLocationDistanceQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilter()
    {
        $this->testMapLocationDistanceQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterComplement()
    {
        $this->testMapLocationDistanceQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterEmpty()
    {
        $this->testMapLocationDistanceQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterLanguageAlwaysAvailable()
    {
        $this->testMapLocationDistanceQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterLanguageAlwaysAvailableComplement()
    {
        $this->testMapLocationDistanceQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterAlwaysAvailable()
    {
        $this->testMapLocationDistanceQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterAlwaysAvailableComplement()
    {
        $this->testMapLocationDistanceQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testMapLocationDistanceFilterAlwaysAvailableEmpty()
    {
        $this->testMapLocationDistanceQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "two"
                ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "two"
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "one"
                ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", false );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "one"
                ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "one"
                ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "one"
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "one", "eng-US", "two", "eng-US", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "two", "eng-US", "one", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField(
                    "folder_name_value_ms",
                    Operator::EQ,
                    "one"
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryAll( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_ASC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQuery( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content2->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB" );
        $content2 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB" );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryLanguageAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", false );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-GB",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryLanguageAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", false );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "eng-US",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryAlwaysAvailable( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-US", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
                'sortClauses' => array(
                    new SortClause\Field( "folder", "name", Query::SORT_DESC, "eng-GB" ),
                ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 2, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
        $this->assertEquals( $content2->id, $searchResult->searchHits[1]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryAlwaysAvailableComplement( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-GB", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 1, $searchResult->totalCount );
        $this->assertEquals( $content1->id, $searchResult->searchHits[0]->valueObject->id );
    }

    /**
     * Test for the findContent() method.
     *
     * @param string $type
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeQueryAlwaysAvailableEmpty( $type = null )
    {
        if ( $type === null )
        {
            $type = "query";
        }

        $content1 = $this->createTestFolderWithName( "eng-GB", "z", "eng-US", "e", "eng-US", true );
        $content2 = $this->createTestFolderWithName( "eng-GB", "e", "eng-US", "z", "eng-GB", true );

        $query = new Query(
            array(
                $type => new Criterion\CustomField( "folder_name_value_ms", Operator::GTE, "z" ),
            )
        );

        $repository = $this->getRepository();
        $searchService = $repository->getSearchService();

        $fieldFilters = array(
            "languages" => array(
                "ger-DE",
            ),
            "useAlwaysAvailable" => true,
        );

        $searchResult = $searchService->findContent( $query, $fieldFilters );

        $this->assertEquals( 0, $searchResult->totalCount );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterAll()
    {
        $this->testCustomFieldQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilter()
    {
        $this->testCustomFieldQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterComplement()
    {
        $this->testCustomFieldQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterEmpty()
    {
        $this->testCustomFieldQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterLanguageAlwaysAvailable()
    {
        $this->testCustomFieldQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterLanguageAlwaysAvailableComplement()
    {
        $this->testCustomFieldQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterAlwaysAvailable()
    {
        $this->testCustomFieldQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterAlwaysAvailableComplement()
    {
        $this->testCustomFieldQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldFilterAlwaysAvailableEmpty()
    {
        $this->testCustomFieldQueryAlwaysAvailableEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterAll()
    {
        $this->testCustomFieldRangeQueryAll( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilter()
    {
        $this->testCustomFieldRangeQuery( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterComplement()
    {
        $this->testCustomFieldRangeQueryComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterEmpty()
    {
        $this->testCustomFieldRangeQueryEmpty( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterLanguageAlwaysAvailable()
    {
        $this->testCustomFieldRangeQueryLanguageAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterLanguageAlwaysAvailableComplement()
    {
        $this->testCustomFieldRangeQueryLanguageAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterAlwaysAvailable()
    {
        $this->testCustomFieldRangeQueryAlwaysAvailable( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterAlwaysAvailableComplement()
    {
        $this->testCustomFieldRangeQueryAlwaysAvailableComplement( "filter" );
    }

    /**
     * Test for the findContent() method.
     *
     * @see \eZ\Publish\API\Repository\SearchService::findContent()
     */
    public function testCustomFieldRangeFilterAlwaysAvailableEmpty()
    {
        $this->testCustomFieldRangeQueryAlwaysAvailableEmpty( "filter" );
    }
}
