<?php

namespace FlyCubePHP\Core\Controllers\Helpers\Extensions;

trait TagBuilder
{
    /**
     * Подготовить массив атрибутов для формирования тэга
     * @param array $required - Обязательные
     * @param array $additional - Дополнительные
     * @return array
     *
     * NOTE: Если в массиве дополнительных атрибутов есть ключи,
     *       совпадающие с ключами из массива обязательных атрибутов,
     *       то их значения будут проигнорированы.
     */
    protected function prepareTagAttributes(array $required,
                                            array $additional): array {

        $requiredKeys = array_keys($required);
        foreach ($requiredKeys as $key) {
            if (isset($additional[$key]))
                unset($additional[$key]);
        }
        return array_unique(array_merge($required, $additional));
    }

    /**
     * "Собрать" тэг
     * @param string $tagName - Название тэга
     * @param string $tagValue - Значение тэга
     * @param array $attributes - Атрибуты тэга
     * @param bool $addTail - Добавлять "хвост" с закрывающим тегом
     * @return string
     */
    protected function makeTag(string $tagName,
                               string $tagValue = "",
                               array $attributes = [],
                               bool $addTail = false): string {
        $tagName = trim($tagName);
        $tmpTag = "";
        foreach ($attributes as $key => $value) {
            $tmpAttr = $value;
            if (is_string($key)) {
                if (strcmp($key, 'checked') === 0 && is_bool($value)) {
                    if ($value === true)
                        $value = "checked";
                    else
                        continue;
                }

                if (strcmp($key, 'required') === 0
                    || strcmp($key, 'disabled') === 0)
                    $tmpAttr = $key;
                else
                    $tmpAttr = "$key=\"$value\"";
            }

            if (empty($tmpTag))
                $tmpTag = $tmpAttr;
            else
                $tmpTag .= " $tmpAttr";
        }
        if ($addTail === true || !empty($tagValue))
            return "<$tagName $tmpTag>$tagValue</$tagName>";

        return "<$tagName $tmpTag />";
    }
}