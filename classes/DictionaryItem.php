<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "Util.php";
use REDCap;

class DictionaryItem
{
    protected string $field_name;
    protected string $form_name;
    protected string $section_header;
    protected string $field_type;
    protected string $field_label;
    protected string $select_choices_or_calculations;
    protected string $field_note;
    protected string $text_validation_type_or_show_slider_number;
    protected string $text_validation_min;
    protected string $text_validation_max;
    protected string $identifier;
    protected string $branching_logic;
    protected string $required_field;
    protected string $custom_alignment;
    protected string $question_number;
    protected string $matrix_group_name;
    protected string $matrix_ranking;
    protected string $field_annotation;

    protected const TAG_ELEMENT_NAME = "@DD-ELEMENTNAME";
    protected const TAG_HIDDEN = "DD-HIDDEN";
    protected const TAG_UNHIDE = "DD";
    protected const TAG_STANDARDS = "@DD-STANDARDS";
    protected const TAG_DATACOLLECTION = "@DD-DATACOLLECT";
    protected const TAG_UNKNOWN = "@DD-UNKNOWN";
    protected const TAG_DEFINITION = "@DD-FIELDDEF";
    protected const TAG_PURPOSE = "@DD-PURPOSE";
    protected const TAG_CALCDESC = "@DD-CALCDESC";

    public const CHOICE_FIELD_TYPES = ['radio', 'dropdown', 'checkbox', 'yesno', 'truefalse'];

    protected bool $is_record_id_field;
    protected string $unknown_code;
    protected const UNKNOWN_ALLOWED_TEXT = "(unknown allowed)";
    protected const NO_UNKNOWN_ALLOWED_TEXT = "(no unknown)";

    public function __construct($item)
    {  // class constructor

        if (!is_array($item) || empty($item)) {
            throw new InvalidArgumentException("Error: array output from REDCap::getDataDictionary required.");
        }  // end elseif

        $this->field_name = $item['field_name'];
        $this->form_name = $item['form_name'];
        $this->section_header = $item['section_header'];
        $this->field_type = $item['field_type'];
        $this->field_label = $item['field_label'];
        $this->select_choices_or_calculations = $item['select_choices_or_calculations'];
        $this->field_note = $item['field_note'];
        $this->text_validation_type_or_show_slider_number = $item['text_validation_type_or_show_slider_number'];
        $this->text_validation_min = $item['text_validation_min'];
        $this->text_validation_max = $item['text_validation_max'];
        $this->identifier = $item['identifier'];
        $this->branching_logic = $item['branching_logic'];
        $this->required_field = $item['required_field'];
        $this->custom_alignment = $item['custom_alignment'];
        $this->question_number = $item['question_number'];
        $this->matrix_group_name = $item['matrix_group_name'];
        $this->matrix_ranking = $item['matrix_ranking'];
        $this->field_annotation = $item['field_annotation'];

        if ($this->field_type == "yesno") $this->select_choices_or_calculations = "1, Yes| 0, No";
        if ($this->field_type == "truefalse") $this->select_choices_or_calculations = "1, True| 0, False";

    }  // end ___construct()

    public function getFieldLabel(): string
    {
        return Util::formatTextForDisplay($this->field_label);
    }

    /*
     * There will a scenario when we want to overwrite the label, e.g. when the label contains other characters that are only applicable for data entry but not data dictionary.
     * Therefore, if you use @DD_ELEMENTNAME="Your label here", this will overwrite the default field_label
     */
    public function getElementName(): string
    {
        // get the overwrite value if exist
        $element_name = $this->extractActionTagText(self::TAG_ELEMENT_NAME);
        if (empty($element_name)) {
            return Util::formatTextForDisplay($this->field_label);
        } else {
            return $element_name;
        }
    }

    public function isHidden(): bool
    {
        $isDescriptive = $this->getFieldType() === "descriptive";
        $hasHiddenTag = str_contains($this->field_annotation, self::TAG_HIDDEN);
        $hasUnhideTag = str_contains($this->field_annotation, self::TAG_UNHIDE);

        return ($isDescriptive && !$hasUnhideTag) || $hasHiddenTag;
    }

    public function getFormName(): string
    {
        return $this->form_name;
    }

    public function getFieldName(): string
    {
        return $this->field_name;
    }

    public function getFieldType(): string
    {
        return $this->field_type;
    }

    private function extractStandaloneHideChoice() {
        // if the @HIDECHOICE is part of a bigger logic, e.g. @IF([record-dag-name] = 'king_edward_memori', @HIDECHOICE='2,3', @HIDECHOICE='4') ignore it
        // Regular expression to match standalone @HIDECHOICE occurrences
        preg_match_all('/\@HIDECHOICE="([^"]+)"/', $this->field_annotation, $matches);

        // Extract matched values
        return $matches[1] ?? [];
    }

    public function getSelectChoices()
    {
        // check if there is any choices hidden, if yes, do not put them in data dictionary
        $hideChoiceText = $this->extractStandaloneHideChoice()[0];

        $hideChoice = array_filter(array_map('trim', explode(",", $hideChoiceText)));
        // Check if $hideChoice contains valid values before filtering
        if (!empty(array_filter($hideChoice))) {
            $choices = explode("|", $this->select_choices_or_calculations);

            // Filter out choices where the first part (before ",") matches any value in $hideChoice
            $filteredChoices = array_filter($choices, function($choice) use ($hideChoice) {
                $parts = array_map('trim', explode(",", $choice, 2)); // Split into key and label
                return !in_array($parts[0], $hideChoice, true); // Check if key exists in hideChoice
            });
            // Ensure $choices is an array before using implode()
            $choicesString = is_array($choices) ? implode("|", $filteredChoices ) : "";
            return $choicesString;
        }
        else {
            return $this->select_choices_or_calculations;
        }

    }

    /**
     * General method to extract information based on a given pattern
     * @param string $tag The pattern to search for.
     * @return string The extracted value or an empty string if not found.
     */
    protected function extractActionTagText(string $actionTag): string
    {
        $string = $this->field_annotation;
        if (strpos($string, $actionTag) === false) return "";
        // Remove space between action tag and first quote
        $pattern = '/(' . $actionTag. ')\s*=\s*([\'"])/';
        $string = preg_replace($pattern, "$1=$2", $string);
        // to cater scenario such as
        // @TAG='value"test"'
        // @TAG="value's"
        // Find the quote character
        $pattern = '/' . $actionTag. '\s*=\s*(["\'])/';
        preg_match($pattern, $string, $matches);
        if ($matches)
        {
            $quote = $matches[1];
            // Find beginning of the quote
            $begin = strpos($string, $actionTag . '=' . $quote) + strlen($actionTag)+2;
            // find the last of the quote
            $end = strpos($string, $quote, $begin);
            //return Util::formatTextForDisplayWithPiping(trim(substr($string, $begin, $end - $begin)));
            $returnValue = trim(substr($string, $begin, $end - $begin));
        }
        else
        {
            preg_match('/(' . $actionTag. ')\s*=\s*(\S+)/', $string, $matches1);
            if ($matches1) {
                $returnValue = $matches1[2];
            }
            else {
                $returnValue = '';
            }
        }
        return $returnValue;
    }

    protected function isCalculatedField()
    {
        return ($this->field_type == 'calc' || str_contains($this->field_annotation, '@CALCTEXT') || str_contains($this->field_annotation, '@CALCDATE'));
    }

    protected function extractDefaultTagValue(string $prefix = "Default Value is ", bool $quote = true): string
    {
        $default_value = Util::formatTextForDisplayWithPiping($this->extractActionTagText("@DEFAULT"));

        if ($default_value === '') {
            return '';
        }

        $formatted_value = ($default_value === "@NOW")
            ? "current date/time"
            : ($quote ? "\"$default_value\"" : $default_value);

        return $prefix . $formatted_value;
    }


    // Return only if:
    // 1. Field note when it is not empty
    // 2. Field Label when Element Name is specified
    public function getCollectionGuide(): array|string
    {
        $data_collection = array();

        $data_collection[] = Util::formatTextForDisplay($this->field_note);
        if (str_contains($this->field_annotation, self::TAG_ELEMENT_NAME))
        {
            $data_collection[] = $this->removeColonAtEnd(Util::formatTextForDisplayWithPiping($this->field_label));
        }
        return $data_collection;
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
    public function getDataCollectionDetails(bool $get_default_flag = true): array|string
    {
        $data_collection = array();

        /*
         * TODO: find if the record id is auto generated or can be entered?
         * this is based on the project setup.  As there is no API to know, we will leave it as it is now.
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
        } else if ($get_default_flag) {
            $data_collection[] = $this->extractDefaultTagValue();
        }

        return $data_collection;
    }

    protected function removeColonAtEnd($string) {
        // Check if the last character is a colon
        if (substr($string, -1) === ':') {
            // Remove the last character
            return substr($string, 0, -1);
        }
        return $string;
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

    /**
     * This function will extract from the field_annotation and get from tags @DD-FIELDDESC
     * @return string
     */
    public function getDescription(): string
    {
        return $this->extractActionTagText(self::TAG_DEFINITION);
    }

    /**
     * This function will extract from the field_annotation and get from tags @DD-PURPOSE
     * @return string
     */
    public function getPurpose(): string
    {
        return Util::formatTextForDisplay($this->extractActionTagText(self::TAG_PURPOSE));
    }

    public function getBranchingLogic(): string
    {
        return (empty($this->branching_logic) ? "" : $this->branching_logic);
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

    /**
     * Ways to find if the field has unknown, when either one is true
     * 1. The user provided the 'Generic Unknown Overarching Code' for the whole project at the External Module settings.  This code will be compared with the dropdown/checkboxes values.
     * 2. The user provided the @DD-UNKNOWN at each field, this will overwrite the "Generic Unknown Overarching Code" specified above
     * 3. It uses the 'Missing Values' with Unknown code as specified above
     */
    protected function getUnknownText(): string
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

    private function getValidationText(): string
    {
        return Util::formatTextForDisplay($this->getCodeDescription($this->text_validation_type_or_show_slider_number));
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
