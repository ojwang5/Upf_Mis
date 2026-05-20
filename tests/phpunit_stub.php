<?php
declare(strict_types=1);

namespace PHPUnit\Framework {
    abstract class TestCase
    {
        // Minimal stub for editor/test scaffolding when PHPUnit isn't installed.

        public function setUp(): void {}
        public function tearDown(): void {}

        public function assertIsInt(mixed $actual, string $message = ''): void {}
        public function assertNotNull(mixed $actual, string $message = ''): void {}
        public function assertSame(mixed $expected, mixed $actual, string $message = ''): void {}
        public function assertTrue(bool $condition, string $message = ''): void {}
        public function assertFalse(bool $condition, string $message = ''): void {}
        public function expectException(string $exception): void {}
    }
}
