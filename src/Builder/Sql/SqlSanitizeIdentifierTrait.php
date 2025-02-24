<?php

declare(strict_types=1);

/**
 * Derafu: Query - Smart Query Builder.
 *
 * Copyright (c) 2025 Esteban De La Fuente Rubio / Derafu <https://www.derafu.org>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\Query\Builder\Sql;

use Closure;

trait SqlSanitizeIdentifierTrait
{
    /**
     * The official function to sanitize/quote SQL identifiers.
     *
     * @var Closure
     */
    private Closure $sanitizeIdentifierCallback;

    /**
     * Sets the official function to sanitize/quote SQL identifiers.
     *
     * @param Closure $sanitizeIdentifierCallback
     * @return self
     */
    public function setSanitizeIdentifierCallback(
        Closure $sanitizeIdentifierCallback
    ): self {
        $this->sanitizeIdentifierCallback = $sanitizeIdentifierCallback;

        return $this;
    }

    /**
     * Sanitizes a SQL identifier.
     *
     * @param string $identifier The identifier to sanitize.
     * @return string The sanitized identifier.
     */
    private function sanitizeIdentifier(string $identifier): string
    {
        $identifier = preg_replace('/[^a-zA-Z0-9_.]/', '', $identifier);

        if (isset($this->sanitizeIdentifierCallback)) {
            return call_user_func($this->sanitizeIdentifierCallback, $identifier);
        }

        return $identifier;
    }
}
