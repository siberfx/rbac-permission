<?php

namespace Siberfx\RbacPermission\Scopes;

/**
 * Marks a scope that reaches every record, like the built-in "global".
 *
 * Its allow adds no row filter, and its deny applies even without a subject
 * (it hides list pages and menus). Implement it on a custom top scope that
 * goes by another name.
 */
interface Unrestricted {}
