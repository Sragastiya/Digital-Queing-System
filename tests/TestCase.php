<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();
        $resolved = $app['db']->connection();
        $connection = $resolved->getDriverName();
        $name = $resolved->getDatabaseName();
        if (! ($connection === 'sqlite' && $name === ':memory:') && (! is_string($name) || ! str_ends_with($name, '_testing'))) {
            throw new \RuntimeException('Tests require an isolated *_testing database or SQLite :memory:.');
        }

        return $app;
    }
}
