<?php

namespace App\Cms;

final class CmsUnavailable extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Content publishing is temporarily unavailable. Booking remains available.');
    }
}
