<?php

namespace EsSmartSearch;

final class SearchIndex {

    /**
     * Register hooks that can make the cached search index stale.
     *
     * @return void
     */
    public function register(): void {
        add_action( 'save_post_batch', [ $this, 'invalidate' ] );
        add_action( 'acf/save_post', [ $this, 'invalidate_acf' ], 9999 );
        add_action( 'updated_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'added_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'deleted_post_meta', [ $this, 'invalidate_meta' ], 10, 4 );
        add_action( 'set_object_terms', [ $this, 'invalidate_terms' ], 10, 6 );
        add_action( 'woocommerce_product_set_stock_status', [ $this, 'invalidate_product' ] );
        add_action( 'deleted_post', [ $this, 'invalidate_deleted' ] );
    }

    /**
     * Delete the cached search index.
     *
     * @return void
     */
    public function invalidate(): void {
        delete_transient( ESSS_INDEX_TRANSIENT );
    }

    /**
     * Invalidate the cached index when ACF fields are saved.
     *
     * @param int|string $post_id Post whose ACF fields were saved.
     * @return void
     */
    public function invalidate_acf( $post_id ): void {
        if ( 'batch' === get_post_type( $post_id ) ) {
            $this->invalidate();
        }
    }

    /**
        * Invalidate the cached index when post metadata changes.
     *
        * @param int $meta_id Metadata record ID.
        * @param int $object_id Post whose metadata changed.
        * @return void
     */
        public function invalidate_meta( $meta_id, $object_id ): void {
        if ( 'batch' === get_post_type( $object_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when post taxonomy terms change.
     *
     * @param int $object_id Post whose taxonomy relationships changed.
     * @param array $terms Term IDs or term data supplied by WordPress.
     * @param array $tt_ids Term taxonomy IDs supplied by WordPress.
     * @param string $taxonomy Taxonomy whose relationships changed.
     * @param bool $append Whether terms were appended instead of replaced.
     * @param array $old_tt_ids Previously assigned term taxonomy IDs.
     * @return void
     */
    public function invalidate_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ): void {
        if ( 'batch' === get_post_type( $object_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when a product stock status changes.
     *
     * @param int $product_id Product whose stock status changed.
     * @return void
     */
    public function invalidate_product( $product_id ): void {
        if ( 'batch' === get_post_type( $product_id ) ) {
            $this->invalidate();
        }
    }

    /**
     * Invalidate the cached index when a post is deleted.
     *
     * @param int $post_id Deleted post ID.
     * @return void
     */
    public function invalidate_deleted( $post_id ): void {
        if ( 'batch' === get_post_type( $post_id ) ) {
            $this->invalidate();
        }
    }
}