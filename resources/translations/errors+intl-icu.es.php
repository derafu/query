<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    'Failed to compile Illuminate parameter placeholders: {error}' =>
        'No se pudieron compilar los marcadores de parámetros de Illuminate: {error}',
    // Configuration.
    'Query configuration root keys must be strings.' =>
        'Las claves de la raíz de la configuración de la consulta deben ser strings.',
    'Query configuration cannot be empty.' =>
        'La configuración de la consulta no puede estar vacía.',
    'Unsupported file extension: {extension}' =>
        'Extensión de archivo no soportada: {extension}',
    'Cannot read configuration file: {path}' =>
        'No se puede leer el archivo de configuración: {path}',
    'Failed to read file: {path}' =>
        'No se pudo leer el archivo: {path}',
    'Invalid JSON configuration format.' =>
        'Formato de configuración JSON inválido.',
    'Error parsing JSON: {error}' =>
        'Error al interpretar el JSON: {error}',
    'Invalid YAML configuration format.' =>
        'Formato de configuración YAML inválido.',
    'Error parsing YAML file: {error}' =>
        'Error al interpretar el archivo YAML: {error}',

    'Alias "{alias}" is already used in this query scope.' =>
        'El alias "{alias}" ya está utilizado en este ámbito de la consulta.',

    // Filters and paths.
    'No valid operator found in expression: {expression}' =>
        'No se encontró un operador válido en la expresión: {expression}',
    'Value "{value}" is not valid for operator "{operator}".' =>
        'El valor "{value}" no es válido para el operador "{operator}".',
    'Invalid composite type: "{type}". Must be one of: {types}.' =>
        'Tipo de composición inválido: "{type}". Debe ser uno de: {types}.',
    'Path must have at least one segment.' =>
        'La ruta debe tener al menos un segmento.',
    'Segment name cannot be empty.' =>
        'El nombre del segmento no puede estar vacío.',
    'Path expression cannot be empty.' =>
        'La expresión de la ruta no puede estar vacía.',
    'Exists path body cannot be empty after ___.' =>
        'El cuerpo de la ruta de existencia no puede estar vacío después de ___.',
    'Field name cannot be empty.' =>
        'El nombre del campo no puede estar vacío.',
    'Invalid characters found in segment name {name}. Allowed at the beginning: letters, numbers, underscore. Allowed at the end: letters, numbers, underscore, closing parenthesis.' =>
        'Caracteres inválidos en el nombre del segmento {name}. Permitidos al comienzo: letras, números y guion bajo. Permitidos al final: letras, números, guion bajo y paréntesis de cierre.',
    'Invalid option format: {option}.' =>
        'Formato de opción inválido: {option}.',
    'Option key and value cannot be empty.' =>
        'La clave y el valor de la opción no pueden estar vacíos.',
    'Option parts around equals sign cannot be empty.' =>
        'Las partes de la opción alrededor del signo igual no pueden estar vacías.',

    'Option "{option}" cannot mix scalar and mapping values.' =>
        'La opción "{option}" no puede mezclar valores simples y correspondencias.',
    'Column "{column}" is defined more than once in option "{option}".' =>
        'La columna "{column}" está definida más de una vez en la opción "{option}".',
    'Option "{option}" is defined more than once.' =>
        'La opción "{option}" está definida más de una vez.',

    // Operators.
    'Operator already registered: {symbol}.' =>
        'El operador ya está registrado: {symbol}.',
    'Operator {symbol} requires unregistered operator: {required}.' =>
        'El operador {symbol} requiere un operador que no está registrado: {required}.',
    'Operator not found: {symbol}' =>
        'No se encontró el operador: {symbol}',
    'Configuration file not found: {path}' =>
        'No se encontró el archivo de configuración: {path}',
    'Failed to parse YAML file: {error}' =>
        'No se pudo interpretar el archivo YAML: {error}',
    'Configuration must contain "types" and "operators" sections.' =>
        'La configuración debe contener las secciones "types" y "operators".',
    'Undefined operator type: {type}.' =>
        'Tipo de operador no definido: {type}.',
    'Operator {symbol} requires unloaded operator: {required}.' =>
        'El operador {symbol} requiere un operador que no está cargado: {required}.',
    'Symbol mismatch: Expected "{expected}" but got "{actual}" in configuration.' =>
        'El símbolo no coincide: se esperaba "{expected}" pero la configuración tiene "{actual}".',
    'Missing required field "{field}" for operator "{operator}".' =>
        'Falta el campo obligatorio "{field}" del operador "{operator}".',
    'Invalid type for field "{field}" in operator "{operator}": Expected "{expected}" but got "{actual}".' =>
        'Tipo inválido para el campo "{field}" del operador "{operator}": se esperaba "{expected}" pero se obtuvo "{actual}".',
    'Operator "{symbol}" is not supported in DQL context{reason}.' =>
        'El operador "{symbol}" no está soportado en el contexto DQL{reason}.',

    // SQL builders.
    'A correlated DQL subquery requires a root entity in the FROM clause.' =>
        'Una subconsulta DQL correlacionada requiere una entidad raíz en la cláusula FROM.',
    'Unexpected Doctrine DBAL QueryBuilder state for "{part}".' =>
        'Estado inesperado del QueryBuilder de Doctrine DBAL para "{part}".',
    'Unexpected Doctrine ORM QueryBuilder join state.' =>
        'Estado inesperado de los joins del QueryBuilder de Doctrine ORM.',
    'Failed to sanitize SQL identifier: {error}' =>
        'No se pudo sanitizar el identificador SQL: {error}',
    'Failed to substitute aggregate DQL subquery: {error}' =>
        'No se pudo sustituir la subconsulta DQL agregada: {error}',
    'List delimiter cannot be empty.' =>
        'El delimitador de listas no puede estar vacío.',
    'Casting rule "{rule}" requires a string, but a previous rule produced an array.' =>
        'La regla de conversión "{rule}" requiere un string, pero una regla anterior produjo un arreglo.',
    'No SQL template for operator {operator} on engine {engine}.' =>
        'No hay plantilla SQL para el operador {operator} en el motor {engine}.',
    'Invalid value format for operator {operator}: {value}.' =>
        'Formato de valor inválido para el operador {operator}: {value}.',
    'Missing parameter for {placeholder}.' =>
        'Falta el parámetro para {placeholder}.',
    'Missing parameters for {placeholder_1} or {placeholder_2}.' =>
        'Faltan los parámetros para {placeholder_1} o {placeholder_2}.',
    'Missing parameters for {placeholder}.' =>
        'Faltan los parámetros para {placeholder}.',
    'Key {key} does not exists.' =>
        'La clave {key} no existe.',
    'SQL Query data is immutable.' =>
        'Los datos de la consulta SQL son inmutables.',
    'Invalid join type {type}.' =>
        'Tipo de join inválido {type}.',
    'No table specified for query.' =>
        'No se especificó una tabla para la consulta.',

    // Engines.
    'PDO returned an unexpected driver name type: {type}.' =>
        'PDO devolvió un tipo inesperado para el nombre del driver: {type}.',
    'Unsupported database platform' =>
        'Plataforma de base de datos no soportada',
    'Doctrine DBAL native connection is not a PDO instance.' =>
        'La conexión nativa de Doctrine DBAL no es una instancia de PDO.',
];
