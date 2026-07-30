<?php

declare(strict_types=1);

namespace Kiosky\Kiosky\Utility;

final class TcaFactory
{
    /**
     * @param array<string, array<string, mixed>> $columns
     * @return array<string, mixed>
     */
    public static function create(string $table, string $titleField, array $columns): array
    {
        $fieldNames = implode(',', array_keys($columns));
        return [
            'ctrl' => [
                'title' => 'Kiosky: ' . ucfirst(str_replace('_', ' ', $titleField)),
                'label' => $titleField,
                'tstamp' => 'tstamp',
                'crdate' => 'crdate',
                'cruser_id' => 'cruser_id',
                'sortby' => 'sorting',
                'delete' => 'deleted',
                'enablecolumns' => [
                    'disabled' => 'hidden',
                    'starttime' => 'starttime',
                    'endtime' => 'endtime',
                ],
                'security' => ['ignorePageTypeRestriction' => true],
                'iconfile' => 'EXT:kiosky/Resources/Public/Icons/Extension.svg',
            ],
            'types' => [
                '1' => ['showitem' => $fieldNames . ',--div--;Access,hidden,starttime,endtime'],
            ],
            'palettes' => [],
            'columns' => [
                ...$columns,
                'hidden' => self::toggle('Disabled'),
                'starttime' => self::dateTime('Start'),
                'endtime' => self::dateTime('End'),
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function input(string $label, bool $required = false): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'input',
                'required' => $required,
                'eval' => $required ? 'trim' : 'trim,null',
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function text(string $label): array
    {
        return ['label' => $label, 'config' => ['type' => 'text', 'rows' => 5]];
    }

    /** @return array<string, mixed> */
    public static function integer(string $label, int $default = 0): array
    {
        return ['label' => $label, 'config' => ['type' => 'number', 'default' => $default]];
    }

    /** @param list<string> $allowed
     *  @return array<string, mixed>
     */
    public static function file(string $label, array $allowed): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'file',
                'allowed' => implode(',', $allowed),
                'maxitems' => 1,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function relation(string $label, string $table): array
    {
        return [
            'label' => $label,
            'config' => [
                'type' => 'group',
                'allowed' => $table,
                'size' => 1,
                'maxitems' => 1,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public static function toggle(string $label): array
    {
        return ['label' => $label, 'config' => ['type' => 'check', 'renderType' => 'checkboxToggle']];
    }

    /** @return array<string, mixed> */
    public static function dateTime(string $label): array
    {
        return ['label' => $label, 'config' => ['type' => 'datetime', 'format' => 'datetime']];
    }
}
