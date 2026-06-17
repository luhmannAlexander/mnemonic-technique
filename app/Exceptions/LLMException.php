<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised on any LLM transport, timeout, or response-parsing failure.
 * Jobs catch this to record a user-facing error and let Horizon mark the
 * attempt as failed (ImplementationPlan §2.1).
 */
class LLMException extends RuntimeException {}
