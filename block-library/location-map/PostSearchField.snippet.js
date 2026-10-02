/**
 * Merge the changes below into
 * `wp-content/themes/core/assets/js/components/PostSearchField.js`.
 *
 * Adds an optional `includedTypes` allowlist so the location map block can limit
 * the post search to the `location` post type. Existing callers are unaffected
 * because the default is an empty array.
 */

// 1. Add to the JSDoc block above the component:
/**
 * @param {Array} props.includedTypes - Optional allowlist of post type slugs.
 */

// 2. Add to the destructured props, after `excludedTypes`:
includedTypes = [],

// 3. Add after the `excludedSet` useMemo:
const includedSet = useMemo(
	() => ( includedTypes.length > 0 ? new Set( includedTypes ) : null ),
	[ includedTypes ]
);

// 4. In the `selectableTypes` useSelect filter, replace:
//        ! excludedSet.has( type.slug )
//    with:
! excludedSet.has( type.slug ) && ( ! includedSet || includedSet.has( type.slug ) )

// 5. Change the useSelect dependency array from `[ excludedSet ]` to:
[ excludedSet, includedSet ]
