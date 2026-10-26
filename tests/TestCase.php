<?php

namespace Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function actingAs(Authenticatable $user, $guard = null)
    {
        parent::actingAs($user, $guard);
        if ($guard !== 'platform') {
            $this->withSession(['auth_stamp' => hash('sha256', (string) $user->getRememberToken())]);
        }

        return $this;
    }
}
