<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

/**
 * Provides test cases for path parsing.
 *
 * This file contains an extensive list of valid and invalid path
 * expressions for testing.
 */
return [
    // Valid path expressions (OK).
    'OK' => [
        // Basic paths.
        'author__books__title',        // Simple chain.
        'profile__address__street',    // Simple chain.
        'user__settings__value',       // Simple chain.

        // Paths with joins.
        'author+left__books__title',   // Left join.
        'author+inner__books__title',  // Inner join (explicit).
        'orders+cross__items__price',  // Cross join.
        'profile+right__photos__url',  // Right join.

        // Paths with aliases.
        'author:a__books:b__title',    // Multiple aliases.
        'users:u__profile:p__name',    // Multiple aliases.
        'orders:o__items__price',      // Single alias.

        // Paths with options.
        'author__books[alias:b]__title',                   // Single option.
        'users[alias:u,join:left]__posts__content',        // Multiple options.
        'orders[status:pending]__items__price',            // Single option.
        'posts[order:created_at,status:published]__title', // Multiple options.

        // Complex combinations.
        'author:a+left__books[order:created_at]__title',   // Join + alias + options.
        'attachments:p+left__comments[alias:c]__content',  // All features.
        'author:a+left__books',        // Alias with join.

        // Multiple segments with same feature.
        'author+left__books+left__chapters__title',        // Multiple joins.
        'users:u__posts:p__comments:c__content',           // Multiple aliases.
        'posts[status:draft]__comments[limit:5]__text',    // Multiple options.
    ],

    // Invalid path expressions (FAIL).
    'FAIL' => [
        // Empty or malformed paths.
        '',                            // Empty path.
        '__',                          // Just separator.
        'author__',                    // Trailing separator.
        '__books',                     // Leading separator.

        // Invalid joins.
        'author+invalid__books',       // Invalid join type.
        'author+__books',              // Empty join type.
        '+left__books',                // Join without table.
        'author+left+right__books',    // Multiple joins on same segment.

        // Invalid aliases.
        'author:__books',              // Empty alias.
        'author::a__books',            // Double colon.
        ':a__books',                   // Alias without table.

        // Invalid options.
        'author__[order:asc]__books',  // Empty segment name with options.
        'author__books[]',             // Empty options.
        'author__books[invalid]',      // Invalid option format.
        'author__books[order:]',       // Empty option value.
        'books[:created_at]',          // Empty option key.

        // Invalid combinations.
        'author+left:a__books',        // Join with alias (wrong order).
    ],
];
