<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'cases' => [
        // Casos simples.
        [
            'path' => 'name',
            'filter' => '=john',
            'expected' => [
                'sql' => 'name = :param_{id}',
                'parameters' => ['param_{id}' => 'john'],
            ],
        ],
        [
            'path' => 'authors__name',
            'filter' => '=john',
            'expected' => [
                'sql' => 'authors.name = :param_{id}',
                'parameters' => ['param_{id}' => 'john'],
            ],
        ],
        [
            'path' => 'authors__books__title',
            'filter' => 'like:SQL book',
            'expected' => [
                'sql' => 'books.title LIKE :param_{id}',
                'parameters' => ['param_{id}' => 'SQL book'],
            ],
        ],
        [
            'path' => 'authors__books__price',
            'filter' => 'between:10,20',
            'expected' => [
                'sql' => 'books.price BETWEEN :param_{id}_1 AND :param_{id}_2',
                'parameters' => [
                    'param_{id}_1' => '10',
                    'param_{id}_2' => '20',
                ],
            ],
        ],
        [
            'path' => 'authors__books__title',
            'filter' => '~~*adventure',
            'expected' => [
                'sql' => 'books.title ILIKE :param_{id}',
                'parameters' => ['param_{id}' => '%adventure%'],
            ],
        ],

        // Casos con diferentes engines.
        [
            'path' => 'posts__title',
            'filter' => '~*hello',
            'engine' => 'mysql',
            'expected' => [
                'sql' => 'posts.title REGEXP :param_{id}',
                'parameters' => ['param_{id}' => 'hello'],
            ],
        ],

        // Casos especiales de operadores.
        [
            'path' => 'status',
            'filter' => 'in:active,pending,review',
            'expected' => [
                'sql' => 'status IN (:param_{id}_1, :param_{id}_2, :param_{id}_3)',
                'parameters' => [
                    'param_{id}_1' => 'active',
                    'param_{id}_2' => 'pending',
                    'param_{id}_3' => 'review',
                ],
            ],
        ],
        [
            'path' => 'created_at',
            'filter' => 'date:20240101',
            'expected' => [
                'sql' => 'DATE(created_at) = :param_{id}',
                'parameters' => ['param_{id}' => '2024-01-01'],
            ],
        ],

        // Más casos de operadores de fecha.
        [
            'path' => 'created_at',
            'filter' => 'month:08',
            'expected' => [
                'sql' => 'MONTH(created_at) = :param_{id}',
                'parameters' => ['param_{id}' => '08'],
            ],
        ],
        [
            'path' => 'created_at',
            'filter' => 'year:24',
            'expected' => [
                'sql' => 'YEAR(created_at) = :param_{id}',
                'parameters' => ['param_{id}' => '2024'],
            ],
        ],
        [
            'path' => 'created_at',
            'filter' => 'period:202408',
            'expected' => [
                'sql' => 'TO_CHAR(created_at, "YYYYMM") = :param_{id}',
                'parameters' => ['param_{id}' => '202408'],
            ],
            'engine' => 'pgsql',
        ],

        // Casos de operadores LIKE.
        [
            'path' => 'title',
            'filter' => '^hello',  // starts with (case sensitive).
            'expected' => [
                'sql' => 'title LIKE :param_{id}',
                'parameters' => ['param_{id}' => 'hello%'],
            ],
        ],
        [
            'path' => 'title',
            'filter' => '$world',  // ends with (case sensitive).
            'expected' => [
                'sql' => 'title LIKE :param_{id}',
                'parameters' => ['param_{id}' => '%world'],
            ],
        ],

        // Casos de operadores NULL.
        [
            'path' => 'deleted_at',
            'filter' => 'is:null',
            'expected' => [
                'sql' => 'deleted_at IS NULL',
                'parameters' => [],
            ],
        ],
        [
            'path' => 'deleted_at',
            'filter' => 'isnot:null',
            'expected' => [
                'sql' => 'deleted_at IS NOT NULL',
                'parameters' => [],
            ],
        ],

        // Casos de operadores numéricos.
        [
            'path' => 'products__price',
            'filter' => '>=100',
            'expected' => [
                'sql' => 'products.price >= :param_{id}',
                'parameters' => ['param_{id}' => '100'],
            ],
        ],
        [
            'path' => 'products__stock',
            'filter' => '<10',
            'expected' => [
                'sql' => 'products.stock < :param_{id}',
                'parameters' => ['param_{id}' => '10'],
            ],
        ],

        // Casos con diferentes engines para LIKE.
        [
            'path' => 'name',
            'filter' => 'ilike:john',
            'engine' => 'mysql',
            'expected' => [
                'sql' => 'name LIKE :param_{id}',
                'parameters' => ['param_{id}' => 'john'],
            ],
        ],
        [
            'path' => 'name',
            'filter' => 'ilike:john',
            'engine' => 'pgsql',
            'expected' => [
                'sql' => 'name ILIKE :param_{id}',
                'parameters' => ['param_{id}' => 'john'],
            ],
        ],

        // Casos de operadores de lista con negación.
        [
            'path' => 'status',
            'filter' => 'notin:draft,deleted',
            'expected' => [
                'sql' => 'status NOT IN (:param_{id}_1, :param_{id}_2)',
                'parameters' => [
                    'param_{id}_1' => 'draft',
                    'param_{id}_2' => 'deleted',
                ],
            ],
        ],

        // Casos de operadores de rango con negación.
        [
            'path' => 'price',
            'filter' => 'notbetween:10,20',
            'expected' => [
                'sql' => 'price NOT BETWEEN :param_{id}_1 AND :param_{id}_2',
                'parameters' => [
                    'param_{id}_1' => '10',
                    'param_{id}_2' => '20',
                ],
            ],
        ],

        // LIKE con diferentes motores.
        [
            'path' => 'name',
            'filter' => 'like:john%',
            'engine' => 'pgsql',
            'expected' => [
                'sql' => 'name LIKE :param_{id}',
                'parameters' => ['param_{id}' => 'john%'],
            ],
        ],
        [
            'path' => 'name',
            'filter' => 'like:john%',
            'engine' => 'mysql',
            'expected' => [
                'sql' => 'name LIKE BINARY :param_{id}',
                'parameters' => ['param_{id}' => 'john%'],
            ],
        ],

        // Auto-LIKE (con transformaciones).
        [
            'path' => 'title',
            'filter' => '^start',
            'expected' => [
                'sql' => 'title LIKE :param_{id}',
                'parameters' => ['param_{id}' => 'start%'],
            ],
        ],
        [
            'path' => 'title',
            'filter' => '$end',
            'expected' => [
                'sql' => 'title LIKE :param_{id}',
                'parameters' => ['param_{id}' => '%end'],
            ],
        ],
        [
            'path' => 'title',
            'filter' => '~~contains',
            'expected' => [
                'sql' => 'title LIKE :param_{id}',
                'parameters' => ['param_{id}' => '%contains%'],
            ],
        ],

        // Regexp por motor.
        [
            'path' => 'description',
            'filter' => '~^test',
            'engine' => 'pgsql',
            'expected' => [
                'sql' => 'description ~ :param_{id}',
                'parameters' => ['param_{id}' => '^test'],
            ],
        ],
        [
            'path' => 'description',
            'filter' => '~^test',
            'engine' => 'mysql',
            'expected' => [
                'sql' => 'description REGEXP BINARY :param_{id}',
                'parameters' => ['param_{id}' => '^test'],
            ],
        ],

        // PostgreSQL específico.
        [
            'path' => 'code',
            'filter' => 'similarto:[0-9]+',
            'engine' => 'pgsql',
            'expected' => [
                'sql' => 'code SIMILAR TO :param_{id}',
                'parameters' => ['param_{id}' => '[0-9]+'],
            ],
        ],

        // Operadores Lista/Rango.
        [
            'path' => 'status',
            'filter' => 'in:active,pending',
            'expected' => [
                'sql' => 'status IN (:param_{id}_1, :param_{id}_2)',
                'parameters' => [
                    'param_{id}_1' => 'active',
                    'param_{id}_2' => 'pending',
                ],
            ],
        ],
        [
            'path' => 'amount',
            'filter' => 'between:10,20',
            'expected' => [
                'sql' => 'amount BETWEEN :param_{id}_1 AND :param_{id}_2',
                'parameters' => [
                    'param_{id}_1' => '10',
                    'param_{id}_2' => '20',
                ],
            ],
        ],

        // NULL operators.
        [
            'path' => 'deleted_at',
            'filter' => 'is:null',
            'expected' => [
                'sql' => 'deleted_at IS NULL',
                'parameters' => [],
            ],
        ],
        [
            'path' => 'deleted_at',
            'filter' => '<=>',  // alias test.
            'expected' => [
                'sql' => 'deleted_at IS NULL',
                'parameters' => [],
            ],
        ],

        // Bitwise por motor.
        [
            'path' => 'flags',
            'filter' => 'b&4',
            'engine' => 'pgsql',
            'expected' => [
                'sql' => 'flags & :param_{id}',
                'parameters' => ['param_{id}' => '4'],
            ],
        ],
        [
            'path' => 'flags',
            'filter' => 'b&4',
            'engine' => 'mysql',
            'expected' => [
                'sql' => 'BIT_AND(flags, :param_{id})',
                'parameters' => ['param_{id}' => '4'],
            ],
        ],
        [
            'path' => 'flags',
            'filter' => 'b^3',
            'engine' => 'sqlite',
            'expected' => [
                'sql' => '(flags | :param_{id}) & ~(flags & :param_{id})',
                'parameters' => ['param_{id}' => '3'],
            ],
        ],
    ],
];
