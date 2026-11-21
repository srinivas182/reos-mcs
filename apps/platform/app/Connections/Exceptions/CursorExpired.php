<?php

declare(strict_types=1);

namespace App\Connections\Exceptions;

use RuntimeException;

/** The tool no longer accepts the saved cursor (Google's 410 Gone): start the stream again. */
final class CursorExpired extends RuntimeException {}
