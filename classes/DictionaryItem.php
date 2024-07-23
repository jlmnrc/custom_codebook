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
    protected const TAG_STANDARDS = "@DD-STANDARDS";
    protected const TAG_DATACOLLECTION = "@DD-DATACOLLECT";
    protected const TAG_UNKNOWN = "@DD-UNKNOWN";
    protected const TAG_DEFINITION = "@DD-FIELDDEF";
    protected const TAG_PURPOSE = "@DD-PURPOSE";
    protected const TAG_CALCDESC = "@DD-CALCDESC";

    public const CHOICE_FIELD_TYPES = ['radio', 'dropdown', 'checkbox', 'yesno', 'truefalse'];


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

        if ($this->field_type == "yesno") $this->select_choices_or_calculations = "1, Yes|0, No";
        if ($this->field_type == "truefalse") $this->select_choices_or_calculations = "1, True|0, False";

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
        return ($this->getFieldType() == "descriptive") || str_contains($this->field_annotation, self::TAG_HIDDEN);
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

    public function getSelectChoices()
    {
        return $this->select_choices_or_calculations;
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
        $quote = $matches[1];

        // Find beginning of the quote
        $begin = strpos($string, $actionTag . '=' . $quote) + strlen($actionTag)+2;

        // find the last of the quote
        $end = strpos($string, $quote, $begin);

        return trim(substr($string, $begin, $end - $begin));
    }

    protected function isCalculatedField()
    {
        return ($this->field_type == 'calc' || str_contains($this->field_annotation, '@CALCTEXT') || str_contains($this->field_annotation, '@CALCDATE'));
    }

    protected function extractDefaultTagValue(): string
    {
        $default_value = Util::formatTextForDisplay($this->extractActionTagText("@DEFAULT"));
        $appendText = "";
        if ($default_value !== '') {
            if ($default_value == "@NOW") {
                $appendText = "Default Value is current date/time";
            } else {
                $appendText = "Default Value is \"$default_value\"";
            }
        }
        return $appendText;
    }
}
