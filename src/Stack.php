<?php

declare(strict_types=1);

namespace Atk4\Audit;

use RuntimeException;
use Atk4\Audit\Model\AuditLog;

/**
 * Simple stack implementation for AuditLog entities.
 */
class Stack
{
    /** @var list<AuditLog> */
    protected array $stack = [];

    protected int $limit;

    public function __construct(int $limit = 1_000)
    {
        $this->stack = [];
        $this->limit = $limit;
    }

    /**
     * Add element in stack.
     */
    public function push(AuditLog $item): void
    {
        if (count($this->stack) >= $this->limit) {
            throw new RuntimeException('Stack is full!');
        }
        array_unshift($this->stack, $item);
    }

    /**
     * Remove element from stack.
     */
    public function pop(): AuditLog
    {
        if ($this->isEmpty()) {
            throw new RuntimeException('Stack is empty!');
        }
        return array_shift($this->stack);
    }

    /**
     * Return element, but do not remove it from stack.
     */
    public function top(): AuditLog
    {
        return current($this->stack);
    }

    /**
     * Is stack empty?
     */
    public function isEmpty(): bool
    {
        return $this->stack === [];
    }
}
