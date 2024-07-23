<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "Util.php";

class WordDictionaryItem extends \Monash\Helix\CustomCodebookModule\DictionaryItem
{
    protected string $unknown_code;
    protected bool $is_record_id_field;
    protected const UNKNOWN_ALLOWED_TEXT = "(unknown allowed)";
    protected const NO_UNKNOWN_ALLOWED_TEXT = "(no unknown)";

    public function __construct($item, $unknown_code, $is_record_id_field) {
        parent::__construct($item);
        $this->unknown_code = !empty($unknown_code) ? $unknown_code : "";

        $this->is_record_id_field = $is_record_id_field;
    }

    /**
     * This function will extract from the field_annotation and get from tags @DD-PURPOSE
     * @return string
     */
    public function getPurpose(): string
    {
        return Util::formatTextForDisplay($this->extractActionTagText(self::TAG_PURPOSE));
    }

    /**
     * This function will extract from the field_annotation and get from tags @DD-FIELDDESC
     * @return string
     */
    public function getDescription(): string
    {
        return $this->extractActionTagText(self::TAG_DEFINITION);
    }

    public function getDataObligation(): string
    {
        if ($this->required_field || $this->is_record_id_field) {
            return "Mandatory " . $this->getUnknownText();
        }
        else
        {
            return "Optional";
        }
    }

    public function getBranchingLogic(): string
    {
        return (empty($this->branching_logic) ? "" : htmlspecialchars($this->branching_logic));
    }

    /**
     * Ways to find if the field has unknown, when either one is true
     * 1. The user provided the 'Generic Unknown Overarching Code' for the whole project at the External Module settings.  This code will be compared with the dropdown/checkboxes values.
     * 2. The user provided the @DD-UNKNOWN at each field, this will overwrite the "Generic Unknown Overarching Code" specified above
     * 3. It uses the 'Missing Values' with Unknown code as specified above
     */
    private function getUnknownText(): string
    {
        if ($this->is_record_id_field || stripos($this->field_annotation, "@HIDDEN") || stripos($this->field_annotation, "@HIDDEN-SURVEY") ) return "";

        if (stripos($this->field_annotation, self::TAG_UNKNOWN)) return $this::UNKNOWN_ALLOWED_TEXT;

        global $missingDataCodes;
        if (count($missingDataCodes) !== 0) {
            if (stripos($this->field_annotation, "@NOMISSING") !== false) {
                return $this::NO_UNKNOWN_ALLOWED_TEXT;
            }
            else {
                return $this::UNKNOWN_ALLOWED_TEXT;
            }
        }

        if (!empty($this->unknown_code))
        {
            if ($this->field_type == 'radio' || $this->field_type == 'dropdown' || $this->field_type == 'checkbox')
            {
                // $this->select_choices_or_calculations has the unknown code?
                $choices = explode("|", $this->select_choices_or_calculations);
                foreach ($choices as $choice) {
                    // Split each choice into code and desc
                    list($code, $desc) = explode(',', $choice, 2);
                    if ($code == $this->unknown_code) {
                        return $this::UNKNOWN_ALLOWED_TEXT;
                    }
                }
                return $this::NO_UNKNOWN_ALLOWED_TEXT;
            }
        }
        return "";
    }

    /**
     * Possible Values:
     * - Derived/Calculated Values
     * - System Generated
     * - Always Collected
     * - Conditional Collection
     *
     * This function will append the values extracted from tag @DD-DATACOLLECT
     * @return string
     */
    public function getDataCollection(): string
    {
        $data_collection = array();

        /*
         * TODO: find if the record id is auto generated
        if ($this->is_record_id_field) {
            $data_collection[] = "System Generated";
        }
        */

        // Always Collected (no branching logic)
        if (empty($this->branching_logic)) {
            $data_collection[] = "Always Collected";
        }
        else {
            // Conditional Collection
            $data_collection[] = "Conditional Collection";
        }

        $data_collection[] = $this->extractActionTagText(self::TAG_DATACOLLECTION);

        if ($this->isCalculatedField()) {
            $data_collection[] = "Derived/Calculated Value";
        } else {
            $data_collection[] = $this->extractDefaultTagValue();
        }

        return implode("</w:t><w:br/><w:t>", array_filter($data_collection));
    }

    private function getValidationText(): string
    {
        return Util::formatTextForDisplay($this->getCodeDescription($this->text_validation_type_or_show_slider_number));
    }

    /**
     * This function will extract from the field_annotation and get from tags @DD-STANDARDS
     * @return string
     */
    public function getStandards(): string
    {
        return Util::formatTextForDisplay($this->extractActionTagText(self::TAG_STANDARDS));
    }

    public function getPermittedValues(): string
    {
        // For 'text' and 'slider' field types
        if ($this->field_type == "text" || $this->field_type == "slider") {
            return $this->handleTextAndSliderFields();
        }

        // Default handling for other field types
        return $this->isCalculatedField() ? $this->getCalculationValues() : ucfirst($this->field_type) . " format";
    }

    // Return only if:
    // 1. Field note when it is not empty
    // 2. Field Label when Element Name is specified
    public function getCollectionGuide(): string
    {
        $data_collection = array();

        $data_collection[] = Util::formatTextForDisplay($this->field_note);
        if (str_contains($this->field_annotation, self::TAG_ELEMENT_NAME))
        {
            $data_collection[] = $this->removeColonAtEnd(Util::formatTextForDisplayWithPiping($this->field_label));
        }
        return implode("</w:t><w:br/><w:t>", array_filter($data_collection));
    }

    private function removeColonAtEnd($string) {
        // Check if the last character is a colon
        if (substr($string, -1) === ':') {
            // Remove the last character
            return substr($string, 0, -1);
        }
        return $string;
    }

    protected function handleTextAndSliderFields(): string
    {
        $validation_text = $this->getValidationText();

        if (empty($this->text_validation_type_or_show_slider_number)) {
            return $this->isCalculatedField() ? $this->getCalculationValues() : "Text format";
        }

        // Handling validation based on min and max values
        if (!empty($this->text_validation_min) && !empty($this->text_validation_max)) {
            return ucfirst("$validation_text between $this->text_validation_min and $this->text_validation_max");
        }

        if (!empty($this->text_validation_min)) {
            return ucfirst("$validation_text from $this->text_validation_min");
        }

        if (!empty($this->text_validation_max)) {
            return ucfirst("$validation_text with maximum value of $this->text_validation_max");
        }

        // Fallback to calculated or generic validation text
        return $this->isCalculatedField() ? $this->getCalculationValues() : $validation_text;
    }

    /*
     * If the overwrite tag @DD-CALCDESC is provided for calculated field, it will be displayed here, otherwise it will be empty
    */
    protected function getCalculationValues(): string
    {
        // get the overwrite value if exist
        $calc_desc = $this->extractActionTagText(self::TAG_CALCDESC);
        if (!empty($calc_desc)) {
            return $calc_desc;
        } else {
            if ($this->field_type == 'calc') {
                // we need to sanitize the calculated fields when the value contains < it will crash the word doc
                return Util::formatTextForDisplayWithPiping($this->select_choices_or_calculations);
            } else {
                return "";
            }
        }
    }


    private function getCodeDescription($code)
    {
        $descriptions = [
            'postalcode_french' => 'French Postal Code',
            'date_dmy' => 'Date (DD/MM/YYYY)',
            'date_mdy' => 'Date (MM/DD/YYYY)',
            'date_ymd' => 'Date (YYYY/MM/DD)',
            'datetime_dmy' => 'Date and Time (DD/MM/YYYY HH:MM)',
            'datetime_mdy' => 'Date and Time (MM/DD/YYYY HH:MM)',
            'datetime_ymd' => 'Date and Time (YYYY/MM/DD HH:MM)',
            'datetime_seconds_dmy' => 'Date and Time with Seconds (DD/MM/YYYY HH:MM:SS)',
            'datetime_seconds_mdy' => 'Date and Time with Seconds (MM/DD/YYYY HH:MM:SS)',
            'datetime_seconds_ymd' => 'Date and Time with Seconds (YYYY/MM/DD HH:MM:SS)',
            'email' => 'Email Address',
            'integer' => 'Integer Number',
            'alpha_only' => 'Alphabetic Characters Only',
            'mrn_10d' => 'Medical Record Number (10 Digits)',
            'mrn_generic' => 'Generic Medical Record Number',
            'number' => 'Number',
            'number_1dp_comma_decimal' => 'Number with 1 Decimal Place (Comma as Decimal Separator)',
            'number_1dp' => 'Number with 1 Decimal Place',
            'number_2dp_comma_decimal' => 'Number with 2 Decimal Places (Comma as Decimal Separator)',
            'number_2dp' => 'Number with 2 Decimal Places',
            'number_3dp_comma_decimal' => 'Number with 3 Decimal Places (Comma as Decimal Separator)',
            'number_3dp' => 'Number with 3 Decimal Places',
            'number_4dp_comma_decimal' => 'Number with 4 Decimal Places (Comma as Decimal Separator)',
            'number_4dp' => 'Number with 4 Decimal Places',
            'number_comma_decimal' => 'Number (Comma as Decimal Separator)',
            'phone_australia' => 'Australian Phone Number',
            'phone' => 'Phone Number',
            'phone_uk' => 'UK Phone Number',
            'postalcode_australia' => 'Australian Postal Code',
            'postalcode_canada' => 'Canadian Postal Code',
            'postalcode_germany' => 'German Postal Code',
            'postalcode_uk' => 'UK Postal Code',
            'ssn' => 'Social Security Number',
            'time_hh_mm_ss' => 'Time (HH:MM:SS)',
            'time' => 'Time (HH:MM)',
            'time_mm_ss' => 'Time (MM:SS)',
            'vmrn' => 'Vehicle Registration Number',
            'zipcode' => 'Zip Code',
        ];

        return $descriptions[$code] ?? "Unknown Code";
    }
}
