<?php

namespace App\Tests\Unit;

use App\Util\TimeInputParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TimeInputParserTest extends TestCase
{
    #[DataProvider('optionalEmptyValueProvider')]
    public function testOptionalEmptyValuesAreConvertedToNull(mixed $value): void
    {
        self::assertNull(TimeInputParser::optional($value, 'lunchStart'));
    }

    public static function optionalEmptyValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'whitespace' => ['   '];
        yield 'empty time placeholder' => ['--:--'];
        yield 'trimmed empty time placeholder' => ['  --:--  '];
    }

    public function testValidTimeIsParsedStrictly(): void
    {
        $time = TimeInputParser::required('08:30', 'workStart');

        self::assertSame('08:30', $time->format('H:i'));
    }

    #[DataProvider('invalidValueProvider')]
    public function testInvalidValuesAreRejected(mixed $value): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Use o formato HH:mm');

        TimeInputParser::optional($value, 'lunchStart');
    }

    public static function invalidValueProvider(): iterable
    {
        yield 'hour out of range' => ['24:00'];
        yield 'minute out of range' => ['12:60'];
        yield 'missing leading zero' => ['8:30'];
        yield 'arbitrary text' => ['amanhã'];
        yield 'non-string value' => [830];
    }
}
