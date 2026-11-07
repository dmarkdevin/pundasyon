<?php

declare(strict_types=1);

namespace App\Support\Auth;

/**
 * A bcrypt hash of a random string nobody knows. Checking a password against it
 * when an email has no account takes as long as a real check, so response times
 * don't reveal which emails exist.
 */
final class DummyHash
{
    public const VALUE = '$2y$12$21NRmuJs1mUwOgJJV9mJzOCm0LSQrC3TJomlLy.QZkwgOK0//07FS';
}
