<?php
/**
 * File containing the Content Search handler class
 *
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 */

namespace eZ\Publish\Core\Persistence\Solr\Content\Search;

use eZ\Publish\SPI\Persistence\Content,
    eZ\Publish\SPI\Persistence\Content\Search\Handler as BaseSearchHandler,
    eZ\Publish\SPI\Persistence\Content\Search\Field,
    eZ\Publish\SPI\Persistence\Content\Search\FieldType,
    eZ\Publish\Core\Persistence\Solr\Exception,
    eZ\Publish\Core\Persistence\Legacy\Content\Mapper as ContentMapper,
    eZ\Publish\Core\Persistence\Legacy\Content\FieldHandler,
    eZ\Publish\API\Repository\Exceptions\NotImplementedException,
    eZ\Publish\API\Repository\Values\Content\Search\SearchResult,
    eZ\Publish\API\Repository\Values\Content\Search\SearchHit,
    eZ\Publish\API\Repository\Values\Content\Query\Criterion,
    eZ\Publish\API\Repository\Values\Content\Query;

/**
 * The Content Search handler retrieves sets of of Content objects, based on a
 * set of criteria.
 *
 * The basic idea of this class is to do the following:
 *
 * 1) The find methods retrieve a recursive set of filters, which define which
 * content objects to retrieve from the database. Those may be combined using
 * boolean opeartors.
 *
 * 2) This recursive criterion definition is visited into a query, which limits
 * the content retrieved from the database. We might not be able to create
 * sensible queries from all criterion definitions.
 *
 * 3) The query might be possible to optimize (remove empty statements),
 * reduce singular and and or constructs…
 *
 * 4) Additionally we might need a post-query filtering step, which filters
 * content objects based on criteria, which could not be convertedd in to
 * database statements.
 */
class Handler extends BaseSearchHandler
{
    /**
     * Content locator gateway.
     *
     * @var \eZ\Publish\Core\Persistence\Solr\Content\Search\Gateway
     */
    protected $gateway;

    /**
     * Field registry
     *
     * @var \eZ\Publish\Core\Persistence\Solr\Content\FieldRegistry
     */
    protected $fieldRegistry;

    /**
     * Creates a new content handler.
     *
     * @param \eZ\Publish\Core\Persistence\Solr\Content\Search\Gateway $gateway
     * @param \eZ\Publish\Core\Persistence\Legacy\Content\FieldHandler $fieldHandler
     */
    public function __construct( Gateway $gateway, FieldRegistry $fieldRegistry )
    {
        $this->gateway       = $gateway;
        $this->fieldRegistry = $fieldRegistry;
    }

     /**
     * finds content objects for the given query.
     *
     * @TODO define structs for the field filters
     *
     * @param \eZ\Publish\API\Repository\Values\Content\Query $query
     * @param array  $fieldFilters - a map of filters for the returned fields.
     *        Currently supported: <code>array("languages" => array(<language1>,..))</code>.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Search\SearchResult
     */
    public function findContent( Query $query, array $fieldFilters = array() )
    {
        return $this->gateway->findContent( $query, $fieldFilters );
    }

    /**
     * Performs a query for a single content object
     *
     * @throws \eZ\Publish\API\Repository\Exceptions\NotFoundException if the object was not found by the query or due to permissions
     * @throws \eZ\Publish\API\Repository\Exceptions\InvalidArgumentException if there is more than than one result matching the criterions
     *
     * @TODO define structs for the field filters
     * @param \eZ\Publish\API\Repository\Values\Content\Query\Criterion $criterion
     * @param array  $fieldFilters - a map of filters for the returned fields.
     *        Currently supported: <code>array("languages" => array(<language1>,..))</code>.
     *
     * @return \eZ\Publish\API\Repository\Values\Content\Content
     */
    public function findSingle( Criterion $criterion, array $fieldFilters = array() )
    {
        $query = new Query();
        $query->criterion = $criterion;
        $query->offset    = 0;
        $query->limit     = 1;
        $result = $this->findContent( $query, $fieldFilters );

        if ( $result->totalCount !== 1 )
        {
            throw new Exception\InvalidObjectCount(
                'Expected exactly one object to be found -- found ' . $result->totalCount . '.'
            );
        }

        $first = reset( $result->searchHits );
        return $first->valueObject;
    }

    /**
     * Suggests a list of values for the given prefix
     *
     * @param string $prefix
     * @param string[] $fieldpath
     * @param int $limit
     * @param \eZ\Publish\API\Repository\Values\Content\Query\Criterion $filter
     */
    public function suggest( $prefix, $fieldPaths = array(), $limit = 10, Criterion $filter = null )
    {
        throw new \Exception( "@TODO: Not implemented yet." );
    }

    /**
     * Indexes a content object
     *
     * @param \eZ\Publish\SPI\Persistence\Content $content
     * @return void
     */
    public function indexContent( Content $content )
    {
        $document = $this->mapContent( $content );
        $this->gateway->indexContent( $document );
    }

    /**
     * Map content to document.
     *
     * A document is an array of fields
     *
     * @param Content $content
     * @return array
     */
    protected function mapContent( Content $content )
    {
        return array(
            new Field(
                'id',
                $content->contentInfo->id,
                new FieldType\StringField()
            ),
            new Field(
                'type',
                $content->contentInfo->contentTypeId,
                new FieldType\StringField()
            ),
            new Field(
                'version',
                $content->versionInfo->versionNo,
                new FieldType\StringField()
            ),
            new Field(
                'status',
                $content->versionInfo->status,
                new FieldType\StringField()
            ),
            new Field(
                'name',
                $content->contentInfo->name,
                new FieldType\StringField()
            ),
            new Field(
                'creator',
                $content->versionInfo->creatorId,
                new FieldType\StringField()
            ),
            new Field(
                'section',
                $content->contentInfo->sectionId,
                new FieldType\StringField()
            ),
            new Field(
                'remote_id',
                $content->contentInfo->remoteId,
                new FieldType\StringField()
            ),
            new Field(
                'modified',
                $content->contentInfo->modificationDate,
                new FieldType\DateField()
            ),
            new Field(
                'published',
                $content->contentInfo->publicationDate,
                new FieldType\DateField()
            ),
            new Field(
                'path',
                array_map(
                    function ( $location )
                    {
                        return $location->pathString;
                    },
                    $content->locations
                ),
                new FieldType\StringField()
            ),
            new Field(
                'location',
                array_map(
                    function ( $location )
                    {
                        return $location->id;
                    },
                    $content->locations
                ),
                new FieldType\StringField()
            ),
            new Field(
                'depth',
                array_map(
                    function ( $location )
                    {
                        return $location->depth;
                    },
                    $content->locations
                ),
                new FieldType\IntegerField()
            ),
            new Field(
                'location_parent',
                array_map(
                    function ( $location )
                    {
                        return $location->parentId;
                    },
                    $content->locations
                ),
                new FieldType\StringField()
            ),
            new Field(
                'location_remote_id',
                array_map(
                    function ( $location )
                    {
                        return $location->remoteId;
                    },
                    $content->locations
                ),
                new FieldType\StringField()
            ),
        );

        // @TODO: Handle fields
    }


    /**
     * Purges all contents from the index
     *
     * @TODO: Make this public API?
     *
     * @return void
     */
    public function purgeIndex()
    {
        $this->gateway->purgeIndex();
    }
}

