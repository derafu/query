<?php

declare(strict_types=1);

/**
 * Derafu: Query - Expressive Path-Based Query Builder for PHP.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

namespace Derafu\TestsQuery\Fixture\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A real Eloquent model for the billing database fixture.
 */
final class IlluminateCustomer extends Model
{
    protected $table = 'customers';
}
