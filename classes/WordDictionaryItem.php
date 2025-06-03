<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "Util.php";

class WordDictionaryItem extends \Monash\Helix\CustomCodebookModule\DictionaryItem
{

    public function __construct($item, $unknown_code, $is_record_id_field) {
        parent::__construct($item);
        $this->unknown_code = !empty($unknown_code) ? $unknown_code : "";

        $this->is_record_id_field = $is_record_id_field;
    }

    public function getDataCollection(): array|string
    {
        $sanitizedArray = array_map('htmlspecialchars', parent::getDataCollectionDetails());
        return implode("</w:t><w:br/><w:t>", array_filter($sanitizedArray));
    }

    public function getCollectionGuide(): array|string
    {
        $sanitizedArray = array_map('htmlspecialchars', parent::getCollectionGuide());
        return implode("</w:t><w:br/><w:t>", array_filter($sanitizedArray));
    }

}
