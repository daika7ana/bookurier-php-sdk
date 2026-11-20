<?php

declare(strict_types=1);

namespace Daika7ana\Bookurier\Support;

final class Xml
{
    private function __construct() {}

    /**
     * @param array<string, string|int|float|bool|null> $fields Null values are omitted; booleans become 1/0.
     */
    public static function cmd(array $fields): string
    {
        $xml = '';

        foreach ($fields as $name => $value) {
            if ($value === null) {
                continue;
            }

            if (is_bool($value)) {
                $value = $value ? 1 : 0;
            }

            $xml .= sprintf('<%s>%s</%s>', $name, self::escape((string) $value), $name);
        }

        return '<cmd>' . $xml . '</cmd>';
    }

    public static function message(string $inner): string
    {
        return '<msg>' . $inner . '</msg>';
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }
}
