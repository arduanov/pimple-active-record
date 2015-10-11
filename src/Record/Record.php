<?php

namespace Record;

use Pimple\Container;
use Doctrine\DBAL;
use Doctrine\Common\Inflector\Inflector;

class Record
{
    protected static $app;

    public function setApp(Container $app)
    {
        self::$app = $app;
    }

    public function app()
    {
        return self::$app;
    }

    public function loadModels(array $modelsList)
    {
        foreach ($modelsList as $name => $class) {
            if (is_string($class)) {
                $closure = function () use ($class) {
                    return new $class();
                };
                $this->app()[$name] = $closure;
            } elseif (is_callable($class)) {
                $this->app()[$name] = $class;
            }
        }
    }
}