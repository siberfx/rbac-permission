<?php

namespace Siberfx\RbacPermission\Exceptions;

use InvalidArgumentException;

/**
 * A permission string, scope or role definition is malformed or unknown.
 * Raised at write time (grants, sync) and on checks, so typos fail loudly.
 */
class InvalidPermission extends InvalidArgumentException {}
