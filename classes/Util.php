<?php

namespace Monash\Helix\CustomCodebookModule;

class Util
{
    private static function removePiping($inputString)
    {
        return preg_replace('/\s*\[.*?\]\s*/', '', $inputString);
    }

    private static function removeEmbeddedField($inputString)
    {
        return preg_replace('/\s*\{.*?\}\s*/', '', $inputString);
    }

    public static function formatTextForDisplay($text): string
    {
        return strip_tags(Util::removePiping(Util::removeEmbeddedField($text)));
    }

    // TODO: put the piping value in different color
    public static function formatTextForDisplayWithPiping($text): string
    {
        return strip_tags($text);
    }

    // Function to convert coded string to friendly message
    public static function convertToFriendlyMessage($code, $nameMap, $valueMap) {
        $pattern = "/\[(.*?)\]\s*(<>|=|>|>=|<|<=)\s*['\"](.*?)['\"]/";
        // Extract the condition parts using regex for both equality and inequality
        preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

        $result = [];
        foreach ($matches as $match) {
            $field = $match[1]; // e.g., mark_status
            $operator = $match[2]; // e.g., <> or =
            $value = $match[3]; // e.g., 2
            $friendlyFieldName = $nameMap[strtolower($field)] ?? $field;
            $friendlyFieldValue = $valueMap[strtolower($field)][$value] ?? $value;

            switch ($operator) {
                case '=':
                    $message = "is";
                    break;
                case '>':
                    $message = "is greater than";
                    break;
                case '>=':
                    $message = "is greater than or equal";
                    break;
                case '<':
                    $message = "is less than";
                    break;
                case '<=':
                    $message = "is less than or equal";
                    break;
                case '<>':
                    $message = "is not";
                    break;
                default:
                    $message = $operator;
                    break;
            }
            $result[] = "[$friendlyFieldName] $message '$friendlyFieldValue'";
        }

        // Reconstruct the friendly message while preserving other text
        $friendlyMessage = preg_replace_callback($pattern, function ($m) use (&$result) {
            return array_shift($result);
        }, $code);

        return Util::convertCheckbox($friendlyMessage, $nameMap, $valueMap);
    }

    private static function convertCheckbox($code, $nameMap, $valueMap)
    {
        // checkboxes branching logic
        //$text = "[df_comp_indxn(1)] = 1, [po_deceased(1)] = 3";

        // Regular expression to match the desired patterns
        $pattern = '/\[(\w+)\((\d+)\)\] = (\d+)/';

        preg_match_all($pattern, $code, $matches, PREG_SET_ORDER);

        $result = [];
        foreach ($matches as $match) {
            // the field before the parentheses
            $field = $match[1]; // e.g., df_comp_indxn
            // number inside the parentheses
            $value = $match[2]; // e.g., (2) or (1)
            // the number after the equals sign
            $isChecked = $match[3]; // e.g., 1 or 0, where 1 is checked, and 0 is unchecked

            $friendlyFieldName = $nameMap[strtolower($field)] ?? $field;
            $friendlyFieldValue = $valueMap[strtolower($field)][$value] ?? $value;

            $result[] = "[$friendlyFieldName] is '$friendlyFieldValue'";
        }

        // Reconstruct the friendly message while preserving other text
        $friendlyMessage = preg_replace_callback($pattern, function ($m) use (&$result) {
            return array_shift($result);
        }, $code);

        return $friendlyMessage;

    }


}