<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

/**
 * Provides test cases for operator parsing and filter creation.
 *
 * This file contains an extensive list of valid and invalid operator
 * expressions for testing.
 */
return [
    // Casos que deben pasar bien (OK).
    'OK' => [
        // Operadores estándar con números.
        '=12345',             // Igual a un número.
        '!=12345',            // Distinto de un número.
        '<>12345',            // Distinto de un número.
        '>100',               // Mayor que un número.
        '<900',               // Menor que un número.
        '>=100',              // Mayor o igual que un número.
        '<=900',              // Menor o igual que un número.

        // Operadores estándar con strings.
        '=daniel',             // Igual a "daniel".
        '!=daniel',            // Distinto de "daniel".
        '<>daniel',            // Distinto de "daniel".

        // Operadores autolike.
        '^daniel',            // Empieza por "daniel".
        '^*daniel',           // Empieza por "daniel" (insensible).
        '!^daniel',           // No empieza por "daniel".
        '!^*daniel',          // No empieza por "daniel" (insensible).
        '~~daniel',           // Contiene "daniel".
        '~~*daniel',          // Contiene "daniel" (insensible).
        '!~~daniel',          // No contiene "daniel".
        '!~~*daniel',         // No contiene "daniel" (insensible).
        '$daniel',            // Termina con "daniel".
        '$*daniel',           // Termina con "daniel" (insensible).
        '!$daniel',           // No termina con "daniel".
        '!$*daniel',          // No termina con "daniel" (insensible).

        // Operadores LIKE.
        'like:Jo_n',          // Coincidencia de patrón LIKE.
        'notlike:Jo_n',       // No coincide con patrón LIKE.
        'ilike:daniel%',      // Coincidencia insensible ILIKE.
        'notilike:daniel%',   // No coincide insensiblemente.

        // Operadores de lista.
        'in:3,5,7',           // En lista.
        'notin:3,5,7',        // No en lista.
        'in:ab,cd\,ef',       // En lista, escapando el delimitador.

        // Operadores de rango.
        'between:100,900',    // Entre dos valores.
        'notbetween:100,900', // No entre dos valores.

        // Operadores de fecha.
        'date:20240823',      // Fecha específica.
        'month:08',           // Mes específico.
        'year:2024',          // Año específico.
        'period:202408',      // Año y mes específico.

        // Operadores NULL.
        'is:null',            // Igual a NULL.
        '<=>',                // Igual a NULL (alias).
        'isnot:null',         // Distinto de NULL.

        // Operadores de expresiones regulares (PostgreSQL y MySQL).
        '~daniel.*',          // Coincidencia de expresión regular POSIX.
        '~*daniel',           // Coincidencia de expresión regular POSIX (insensible).
        '!~daniel.*',         // No coincide con expresión regular POSIX.
        '!~*daniel',          // No coincide con expresión regular POSIX (insensible).
        'similarto:daniel%',  // Coincidencia similar a LIKE.
        'notsimilarto:daniel%', // No coincide con similar a LIKE.

        // Alias de expresiones regulares (MySQL).
        'regexp:daniel.*',    // Coincidencia de expresión regular (alias de ~).
        'notregexp:daniel.*', // No coincide con expresión regular (alias de !~).
        'rlike:daniel.*',     // Coincidencia de expresión regular (alias de ~).
        'notrlike:daniel.*',  // No coincide con expresión regular (alias de !~).

        // Comodines Básicos.
        'similarto:%daniel%',
        'notsimilarto:_a_el',

        // Rangos de Caracteres.
        'similarto:[a-z]',
        'notsimilarto:[A-Z0-9]',

        // Alternancia.
        'similarto:(daniel|david)',
        'notsimilarto:(dog|cat)',

        // Combinación de Grupos y Comodines.
        'similarto:(d%|c_)',
        'notsimilarto:(_a%|[1-3]%)',

        // Combinaciones Complejas.
        'similarto:([a-z]|[A-Z])%',
        'notsimilarto:%(dan|da_)',

        // Operadores binarios.
        'b&1',     // AND bit a bit con valor 1.
        'b&2',     // AND bit a bit con valor 2.
        'b&4',     // AND bit a bit con valor 4.
        'b|1',     // OR bit a bit con valor 1.
        'b|2',     // OR bit a bit con valor 2.
        'b|4',     // OR bit a bit con valor 4.
        'b^1',     // XOR bit a bit con valor 1.
        'b^2',     // XOR bit a bit con valor 2.
        'b^4',     // XOR bit a bit con valor 4.
        'b<<1',    // Desplazamiento a la izquierda con valor 1.
        'b<<2',    // Desplazamiento a la izquierda con valor 2.
        'b<<3',    // Desplazamiento a la izquierda con valor 3.
        'b>>1',    // Desplazamiento a la derecha con valor 1.
        'b>>2',    // Desplazamiento a la derecha con valor 2.
        'b>>3',    // Desplazamiento a la derecha con valor 3.
        'b&~1',    // AND bit a bit con negación con valor 1.
        'b&~2',    // AND bit a bit con negación con valor 2.
        'b&~4',    // AND bit a bit con negación con valor 4.
    ],

    // Casos que deben fallar (FAIL).
    'FAIL' => [
        // Casos fallidos por sintaxis o valores incorrectos.
        'date:1',             // Fecha con formato incorrecto.
        'between:1,2,3',      // Rango con más de dos valores.
        'like:',              // Patrón LIKE vacío.
        'in:',                // Lista vacía.
        'is:null and notin:100,200',  // Combina operadores en un solo caso (incorrecto).
        'similarto:',         // Patrón similar a LIKE vacío.
        '~[',                 // Expresión regular inválida.
        '!~*$',               // Expresión regular insensible inválida.

        // Paréntesis no balanceados.
        'similarto:(daniel|david',
        'notsimilarto:dog|cat)',

        // Corchetes no balanceados.
        'similarto:[a-z',
        'notsimilarto:A-Z]',

        // Combinaciones Incorrectas.
        'similarto:([a-z]|[A-Z',
        'notsimilarto:(d%|[1-3]%',

        // Operadores SIMILAR TO mal formados.
        'similarto:daniel|david)',
        'notsimilarto:dog]',

        // Uso incorrecto de comodines.
        'similarto:%dan[iel',
        'notsimilarto:_a]el%',

        // Operadores binarios.
        'b&',      // Falta el valor después del operador.
        'b|',      // Falta el valor después del operador.
        'b^',      // Falta el valor después del operador.
        'b<<',     // Falta el valor después del operador.
        'b>>',     // Falta el valor después del operador.
        'b&~',     // Falta el valor después del operador.
        'b&abc',   // Valor no numérico para AND bit a bit.
        'b|abc',   // Valor no numérico para OR bit a bit.
        'b^abc',   // Valor no numérico para XOR bit a bit.
        'b<<abc',  // Valor no numérico para desplazamiento a la izquierda.
        'b>>abc',  // Valor no numérico para desplazamiento a la derecha.
        'b&~abc',  // Valor no numérico para AND bit a bit con negación.
        'b&1.5',   // Valor decimal no permitido para AND bit a bit.
        'b|1.5',   // Valor decimal no permitido para OR bit a bit.
        'b^1.5',   // Valor decimal no permitido para XOR bit a bit.
        'b<<1.5',  // Valor decimal no permitido para desplazamiento a la izquierda.
        'b>>1.5',  // Valor decimal no permitido para desplazamiento a la derecha.
        'b&~1.5',  // Valor decimal no permitido para AND bit a bit con negación.
    ],
];
