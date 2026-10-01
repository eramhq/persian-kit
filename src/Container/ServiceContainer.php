<?php

namespace PersianKit\Container;

defined('ABSPATH') || exit;

class ServiceContainer
{
    private static ?self $instance = null;

    /** @var array<string, callable> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    private function __construct()
    {
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function register(string $id, callable $factory): self
    {
        $this->factories[$id] = $factory;
        return $this;
    }

    public function singleton(string $id, object $instance): self
    {
        $this->instances[$id] = $instance;
        return $this;
    }

    public function get(string $id): mixed
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (isset($this->factories[$id])) {
            $this->instances[$id] = ($this->factories[$id])($this);
            return $this->instances[$id];
        }

        return null;
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }
}
