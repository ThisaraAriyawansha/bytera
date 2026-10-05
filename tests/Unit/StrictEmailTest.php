<?php

namespace Tests\Unit;

use App\Rules\StrictEmail;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StrictEmailTest extends TestCase
{
    /**
     * @return array<string, array{mixed}>
     */
    public static function rejectedRecipients(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'two addresses with a comma' => ['a@example.com,b@example.com'],
            'two addresses with a semicolon' => ['a@example.com;b@example.com'],
            'whitespace inside' => ['kasun perera@example.com'],
            'trailing newline' => ["kasun@example.com\n"],
            'header injection' => ["kasun@example.com\r\nBcc: x@evil.test"],
            'display name' => ['Kasun <kasun@example.com>'],
            'no top-level domain' => ['kasun@localhost'],
            'over 254 characters' => [str_repeat('a', 250).'@example.com'],
        ];
    }

    #[DataProvider('rejectedRecipients')]
    public function test_rejects_anything_but_one_plain_address(mixed $recipient): void
    {
        $this->assertFalse(StrictEmail::isValid($recipient));
    }

    public function test_accepts_plain_addresses(): void
    {
        $this->assertTrue(StrictEmail::isValid('kasun.perera+pos@mail.example.lk'));
        $this->assertTrue(StrictEmail::isValid('SALES@ACME.LK'));
    }
}
