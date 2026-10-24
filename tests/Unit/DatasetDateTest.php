<?php

namespace Tests\Unit;

use App\Support\DatasetDate;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DatasetDateTest extends TestCase
{
    /**
     * One example of each format found in the dataset CSVs.
     */
    public static function datasetFormats(): array
    {
        return [
            'ISO' => ['2026-09-18', '2026-09-18'],
            'month name' => ['Aug 25, 2026', '2026-08-25'],
            'month name, zero-padded day' => ['Mar 05, 2026', '2026-03-05'],
            'slashes, year first' => ['2026/09/02', '2026-09-02'],
            'day-month-year' => ['30-Aug-2026', '2026-08-30'],
            'US month/day/year' => ['09/16/2026', '2026-09-16'],
        ];
    }

    #[DataProvider('datasetFormats')]
    public function test_reads_every_dataset_format(string $value, string $expected): void
    {
        $this->assertSame($expected, DatasetDate::parse($value)->toDateString());
    }

    public function test_blank_and_na_mean_never_contacted(): void
    {
        $this->assertNull(DatasetDate::parse(''));
        $this->assertNull(DatasetDate::parse('N/A'));
    }

    public function test_rejects_an_unknown_format(): void
    {
        $this->expectException(InvalidArgumentException::class);

        DatasetDate::parse('Sept 9th');
    }
}
