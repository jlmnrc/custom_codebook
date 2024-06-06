<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "Util.php";
class HtmlDictionaryItem extends \Monash\Helix\CustomCodebookModule\DictionaryItem
{
    public function __construct($item) {
        parent::__construct($item);
    }

    public function getBranchingLogic(): string
    {
        return (empty($this->branching_logic) ? "" : "<div style='color:#777;margin-right:5px;margin-top:10px'>Show the field ONLY if:</div><div>$this->branching_logic</div>");
    }

    public function getFieldName(): string
    {
        return "<code class='fs12'><span style='color:#aaa;margin-right:1px;'>[</span><span class='text-dangerrc'>$this->field_name</span><span style='color:#aaa;margin-right:1px;'>]</span></code>" . $this->getBranchingLogic();
    }

    public function getRequiredField(): string
    {
        return ($this->required_field ? "<div class='requiredlabel'>* must provide value</div>" : "");
    }

    public function getFieldNote(): string
    {
        return (!empty($this->field_note) ? "<div><i><span style='color:#666;font-size:11px'>$this->field_note</span></i></div>" : "");

    }

    public function getFieldLabel(): string
    {
        return parent::getFieldLabel() . $this->getFieldNote() . $this->getRequiredField();
    }

    private function getValidationType(): string
    {

        $validation_texts = [];

        $validation_texts[] = $this->text_validation_type_or_show_slider_number;

        if (!empty($this->text_validation_min)) {
            $validation_texts[] = "Min: $this->text_validation_min";
        }
        if (!empty($this->text_validation_max)) {
            $validation_texts[] = "Max: $this->text_validation_max";
        }

        return implode(', ', $validation_texts);
    }

    private function getCalculationValues(): string
    {
        // get the overwrite value if exist
        $calc_desc = Util::formatTextForDisplay($this->extractActionTagText("@DD-CALCDESC"));
        if (!empty($calc_desc)) {
            return $calc_desc;
        } else {
            return $this->field_type;
        }
    }

    private function getFieldTypeHML(): string
    {
        // if it is calculated values, get the calculation description if provided at @DD-CALCDESC
        $fld_type = $this->isCalculatedField() ? $this->getCalculationValues() : $this->field_type;

        return $fld_type . ' ' . (empty($this->text_validation_type_or_show_slider_number) ? "" : "(". $this->getValidationType() . ")");
    }

    public function getFieldAttr(): string
    {
        return $this->getFieldTypeHML() . $this->getChoices() . "<br>" . $this->extractDefaultTagValue();
    }

    public function getChoices(): string
    {
        $choiceFieldTypes = ['radio', 'dropdown', 'checkbox', 'yesno', 'truefalse'];
        if (in_array($this->getFieldType(), $choiceFieldTypes)) {

            // format the choices if exist
            if ($this->select_choices_or_calculations !== "") {
                $choices = explode("|", Util::formatTextForDisplay($this->select_choices_or_calculations));

                // Start the table
                $output = '<table border="1" class="ReportTableWithBorder">';
                $output .= '<tr><th>Code</th><th>Description</th></tr>';

                // Loop through each element in the array
                foreach ($choices as $choice) {
                    // Split each choice into code and desc
                    list($code, $desc) = explode(', ', $choice, 2);
                    // Output the HTML table row
                    $output .= "<tr><td>$code</td><td>$desc</td></tr>";
                }

                // End the table
                $output .= '</table>';

                return $output;
            }
        }
        return "";
    }

}
