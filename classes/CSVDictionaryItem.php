<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "Util.php";

class CSVDictionaryItem extends \Monash\Helix\CustomCodebookModule\DictionaryItem
{
    public function __construct($item, $unknown_code, $is_record_id_field) {
        parent::__construct($item);
        $this->unknown_code = !empty($unknown_code) ? $unknown_code : "";

        $this->is_record_id_field = $is_record_id_field;
        $this->strip_html = false;
    }

    public function getPermittedValues(): string
    {
        if (in_array($this->field_type, DictionaryItem::CHOICE_FIELD_TYPES)) {
            return $this->getSelectChoices();
        } else {
            return parent::getPermittedValues();
        }
    }

    public function getCollectionGuide(): array|string
    {
        return implode("; ", array_filter(parent::getCollectionGuide()));
    }

    public function getDataCollection(): array|string
    {
        return implode("; ", array_filter(parent::getDataCollectionDetails(false)));
    }

    public function getDefaultValue(): string
    {
        return $this->extractDefaultTagValue("", false);
    }
}