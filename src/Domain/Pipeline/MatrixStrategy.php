<?php

namespace App\Domain\Pipeline;

final readonly class MatrixStrategy
{
    /** @param array<string, list<string|int|float>> $axes */
    public function __construct(public readonly array $axes) {}

    /** @return list<array<string, string>> */
    public function combinations(): array
    {
        $result = [[]];
        foreach ($this->axes as $key => $values) {
            $expanded = [];
            foreach ($result as $combo) {
                foreach ($values as $value) {
                    $expanded[] = array_merge($combo, [$key => (string) $value]);
                }
            }
            $result = $expanded;
        }
        return $result;
    }
}
