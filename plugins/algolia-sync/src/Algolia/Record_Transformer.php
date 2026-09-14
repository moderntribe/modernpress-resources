<?php declare(strict_types=1);

namespace Tribe\Algolia_Sync\Algolia;

/**
 * Builds an agnostic Algolia record for any post type.
 *
 * Records are tagged as external so the site consuming the shared index can tell
 * these apart from its own content.
 */
class Record_Transformer {

	private const CATEGORY_TAXONOMY = 'category';
	private const TAG_TAXONOMY      = 'post_tag';

	/**
	 * @return array<string, mixed>
	 */
	public function to_record( \WP_Post $post ): array {
		$taxonomy_terms = $this->taxonomy_terms( $post );
		$categories     = $taxonomy_terms[ self::CATEGORY_TAXONOMY ] ?? [];
		$tags           = $taxonomy_terms[ self::TAG_TAXONOMY ] ?? [];
		$excerpt        = $this->clean_text( get_the_excerpt( $post ) );

		$record = [
			'objectID'         => $this->build_object_id( $post->ID ),
			'title'            => $this->clean_text( get_the_title( $post ) ),
			'author'           => $this->author_name( (int) $post->post_author ),
			'slug'             => $post->post_name,
			'post_type'        => $post->post_type,
			'post_type_label'  => $this->post_type_label( $post->post_type ),
			'content'          => $excerpt,
			'excerpt'          => $excerpt,
			'categories'       => $categories,
			'tags'             => $tags,
			'topics'           => $categories,
			'primary_term'     => $this->primary_term( $post, self::CATEGORY_TAXONOMY, $categories ),
			'thumbnail'        => (string) get_the_post_thumbnail_url( $post ),
			'timestamp'        => (int) strtotime( $post->post_date_gmt ?: $post->post_date ),
			'url'              => (string) get_permalink( $post ),
			'is_external_item' => true,
			'external_site'    => $this->clean_text( (string) get_bloginfo( 'name' ) ),
		];

		// Add every registered taxonomy by slug. Core compatibility fields above
		// take precedence if a taxonomy happens to use the same record key.
		$record = array_merge( $taxonomy_terms, $record );

		/**
		 * Filters the Algolia record built for a post before it is sent.
		 *
		 * @param array<string, mixed> $record The record data.
		 * @param \WP_Post             $post   The source post.
		 */
		return (array) apply_filters( 'tribe/algolia_sync/post_to_record', $record, $post );
	}

	public function build_object_id( int $post_id ): string {
		return sprintf( '%s_%d', $this->site_slug(), $post_id );
	}

	/**
	 * Strips markup and resolves entities so records hold plain readable text.
	 *
	 * Excerpts and titles arrive already escaped (`&nbsp;`, `&hellip;`, `&amp;`),
	 * and decoded non-breaking spaces still need collapsing to normal spaces.
	 */
	private function clean_text( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = str_replace( "\xC2\xA0", ' ', $text );

		$collapsed = preg_replace( '/\s+/u', ' ', $text );

		return trim( is_string( $collapsed ) ? $collapsed : $text );
	}

	private function site_slug(): string {
		$slug = sanitize_title( get_bloginfo( 'name' ) );

		return $slug !== '' ? $slug : 'site';
	}

	private function author_name( int $author_id ): string {
		$display_name = get_the_author_meta( 'display_name', $author_id );

		if ( $display_name !== '' ) {
			return $display_name;
		}

		$full_name = trim( sprintf(
			'%s %s',
			get_the_author_meta( 'first_name', $author_id ),
			get_the_author_meta( 'last_name', $author_id )
		) );

		if ( $full_name !== '' ) {
			return $full_name;
		}

		return get_the_author_meta( 'nickname', $author_id );
	}

	private function post_type_label( string $post_type ): string {
		$object = get_post_type_object( $post_type );

		return $object instanceof \WP_Post_Type
			? $this->clean_text( (string) $object->labels->singular_name )
			: $post_type;
	}

	/**
	 * @param list<string> $fallback_names
	 */
	private function primary_term( \WP_Post $post, string $taxonomy, array $fallback_names ): string {
		$term_name = '';
		$term_id   = $this->primary_term_id( $post->ID, $taxonomy );

		if ( $term_id > 0 ) {
			$term = get_term( $term_id, $taxonomy );

			if ( $term instanceof \WP_Term ) {
				$term_name = $this->clean_text( $term->name );
			}
		}

		if ( $term_name === '' ) {
			$term_name = $fallback_names[0] ?? '';
		}

		/**
		 * Filters the primary term name added to an Algolia record.
		 *
		 * @param string   $term_name Primary term name.
		 * @param \WP_Post $post      Source post.
		 * @param string   $taxonomy  Taxonomy slug.
		 * @param int      $term_id   Primary term ID reported by an SEO plugin, or zero.
		 */
		$term_name = apply_filters(
			'tribe/algolia_sync/primary_term',
			$term_name,
			$post,
			$taxonomy,
			$term_id
		);

		return is_string( $term_name ) ? $term_name : '';
	}

	private function primary_term_id( int $post_id, string $taxonomy ): int {
		if ( class_exists( 'RankMath\Common' ) ) {
			return (int) get_post_meta( $post_id, "rank_math_primary_{$taxonomy}", true );
		}

		if ( function_exists( 'yoast_get_primary_term_id' ) ) {
			return (int) yoast_get_primary_term_id( $taxonomy, $post_id );
		}

		return 0;
	}

	/**
	 * @return array<string, list<string>>
	 */
	private function taxonomy_terms( \WP_Post $post ): array {
		$taxonomies = get_object_taxonomies( $post->post_type, 'names' );

		/**
		 * Filters taxonomies collected for an Algolia record.
		 *
		 * @param list<string> $taxonomies Taxonomy slugs registered to the post type.
		 * @param \WP_Post     $post       Source post.
		 */
		$taxonomies = apply_filters( 'tribe/algolia_sync/taxonomies', $taxonomies, $post );

		if ( ! is_array( $taxonomies ) ) {
			$taxonomies = [];
		}

		$taxonomy_terms = [];

		foreach ( array_filter( $taxonomies, 'is_string' ) as $taxonomy ) {
			if ( $taxonomy === '' ) {
				continue;
			}

			$taxonomy_terms[ $taxonomy ] = $this->term_names( $post->ID, $taxonomy );
		}

		/**
		 * Filters the complete taxonomy-to-term-names map for an Algolia record.
		 *
		 * @param array<string, list<string>> $taxonomy_terms Terms keyed by taxonomy slug.
		 * @param \WP_Post                    $post           Source post.
		 */
		$taxonomy_terms = apply_filters( 'tribe/algolia_sync/taxonomy_terms', $taxonomy_terms, $post );

		return $this->normalize_taxonomy_terms( $taxonomy_terms );
	}

	/**
	 * @return list<string>
	 */
	private function term_names( int $post_id, string $taxonomy ): array {
		$names = [];

		if ( taxonomy_exists( $taxonomy ) ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy );

			if ( ! is_wp_error( $terms ) ) {
				$names = array_map( fn( \WP_Term $term ): string => $this->clean_text( $term->name ), $terms );
			}
		}

		/**
		 * Filters term names before they are added to an Algolia record.
		 *
		 * Runs for all results, including missing taxonomies and term-query errors,
		 * so integrations can provide values from another source when needed.
		 *
		 * @param list<string> $names    Term names.
		 * @param int          $post_id  Source post ID.
		 * @param string       $taxonomy Taxonomy slug.
		 */
		$names = apply_filters( 'tribe/algolia_sync/term_names', $names, $post_id, $taxonomy );

		if ( ! is_array( $names ) ) {
			return [];
		}

		return array_values( array_filter( $names, 'is_string' ) );
	}

	/**
	 * @param mixed $taxonomy_terms
	 *
	 * @return array<string, list<string>>
	 */
	private function normalize_taxonomy_terms( mixed $taxonomy_terms ): array {
		if ( ! is_array( $taxonomy_terms ) ) {
			return [];
		}

		$normalized = [];

		foreach ( $taxonomy_terms as $taxonomy => $names ) {
			if ( ! is_string( $taxonomy ) || ! is_array( $names ) ) {
				continue;
			}

			$normalized[ $taxonomy ] = array_values( array_filter( $names, 'is_string' ) );
		}

		return $normalized;
	}

}
