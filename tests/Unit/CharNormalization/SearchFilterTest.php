<?php

namespace PersianKit\Tests\Unit\CharNormalization;

use PersianKit\Dependencies\Eram\Abzar\Text\CharNormalizer;
use PersianKit\Modules\CharNormalization\SearchFilter;
use PHPUnit\Framework\TestCase;

require_once dirname(__DIR__, 3) . '/src/functions.php';

class SearchFilterTest extends TestCase
{
    private function variants(string $term): array
    {
        $variants = (new SearchFilter(new CharNormalizer()))->variants($term);
        sort($variants);

        return $variants;
    }

    public function test_a_word_is_matched_in_persian_and_arabic_letters(): void
    {
        $this->assertSame($this->sorted(['کتاب', 'كتاب']), $this->variants('کتاب'));
        $this->assertSame($this->sorted(['کتاب', 'كتاب']), $this->variants('كتاب'));
    }

    public function test_a_number_is_matched_in_persian_and_english_digits(): void
    {
        $this->assertSame($this->sorted(['1405', '۱۴۰۵']), $this->variants('۱۴۰۵'));
        $this->assertSame($this->sorted(['1405', '۱۴۰۵']), $this->variants('1405'));
    }

    public function test_arabic_indic_digits_also_match_persian_and_english_digits(): void
    {
        $this->assertSame($this->sorted(['١٤٠٥', '1405', '۱۴۰۵']), $this->variants('١٤٠٥'));
    }

    public function test_letters_and_digits_together_give_each_combination_once(): void
    {
        $this->assertSame(
            $this->sorted(['کتاب1405', 'کتاب۱۴۰۵', 'كتاب1405', 'كتاب۱۴۰۵']),
            $this->variants('كتاب۱۴۰۵')
        );
    }

    public function test_a_latin_word_has_no_other_form(): void
    {
        $this->assertSame(['hello'], $this->variants('hello'));
    }

    /**
     * @param list<string> $values
     * @return list<string>
     */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
