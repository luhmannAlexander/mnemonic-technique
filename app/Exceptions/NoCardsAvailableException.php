<?php

namespace App\Exceptions;

use App\Services\SessionService;
use RuntimeException;

/**
 * Thrown by {@see SessionService::start()} when there are no
 * eligible cards (or none with questions yet) to build a practice session from.
 */
class NoCardsAvailableException extends RuntimeException {}
