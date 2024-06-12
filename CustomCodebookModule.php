<?php

namespace Monash\Helix\CustomCodebookModule;

require_once "vendor/autoload.php";
require_once "classes/Util.php";
require_once "classes/DictionaryItem.php";
require_once "classes/WordDictionaryItem.php";
require_once "classes/HtmlDictionaryItem.php";

use ExternalModules\AbstractExternalModule;
use ExternalModules\ExternalModules;
use REDCap;

class CustomCodebookModule extends AbstractExternalModule
{
    private $friendlyNames;
    private $friendlyValues;


    // insert an icon at Codebook
    function redcap_every_page_top($project_id)
    {
        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);

        //word-enabled
        if (PAGE === "Design/data_dictionary_codebook.php") {
            echo '<script src="' . $this->getUrl('js/codebook_word.js') . '"></script>';
            echo '<script type="text/javascript">function exportToWord() {window.location.href = "' . ExternalModules::getPageUrl($this->PREFIX, 'pages/codebook_word.php?pid=' . $project_id) . '";}</script>';
            echo '<script src="' . $this->getUrl('js/codebook_html.js') . '"></script>';
            echo '<script type="text/javascript">function customDataDictionary() {window.location.href = "' . ExternalModules::getPageUrl($this->PREFIX, 'pages/codebook_html.php?pid=' . $project_id) . '";}</script>';
        }
    }

    /**
     * This function will get the element name or field label of a variable for conditional logic display, so instead
     * of using [comorb] = '3', we want to show [Comorbidity] = 'Diabetes'.
     * @return void
     **/
    protected function initFriendlyVariableNames()
    {
        $dataDictionaryArray = REDCap::getDataDictionary('array');
        foreach ($dataDictionaryArray as $fieldName => $fieldAttributes) {
            $item = new DictionaryItem($fieldAttributes);
            // Assign the fieldElement as the value for the fieldName key in the associative array
            $this->friendlyNames[$item->getFieldName()] = $item->getElementName();

            if ($item->getFieldType() == 'radio' || $item->getFieldType() == 'dropdown' || $item->getFieldType() == 'checkbox') {
                // $this->select_choices_or_calculations has the unknown code?
                $choices = explode("|", $item->getSelectChoices());
                $nestedValues = array();
                foreach ($choices as $choice) {
                    // Split each choice into code and desc
                    list($code, $desc) = explode(',', $choice, 2);
                    $nestedValues[trim($code)] = Util::formatTextForDisplay(trim($desc));
                }
                $this->friendlyValues[$item->getFieldName()] = $nestedValues;
            }
        }
    }

    // ******************************************************************************************
    // ******************************************************************************************
    // HTML
    // ******************************************************************************************
    // ******************************************************************************************
    public function renderDataDictionaryPage()
    {
        $dataDictionaryArray = REDCap::getDataDictionary('array');
        $currentFormName = "";
        $fieldNumberInSection = 0;

        $htmlContent = $this->printCSS();
        $localTime = $this->getCurrentDate();
        $htmlContent .= "<center><span style='font-size:18px;font-weight:bold;'><i class='fas fa-book' style='font-size:16px;'></i> Data Dictionary Codebook</span><div>$localTime</div></center><p>&nbsp;</p>";

        // <i class='fas fa-book' style='font-size:16px;></i> $htmlContent .= "Data Dictionary Codebook";
        $htmlContent .= "<table class='ReportTableWithBorder'>";

        foreach ($dataDictionaryArray as $fieldName => $fieldAttributes) {
            $dictionaryItem = new HtmlDictionaryItem($fieldAttributes);
            if (!$dictionaryItem->isHidden()) {
                if ($currentFormName !== $dictionaryItem->getFormName()) {
                    $currentFormName = $dictionaryItem->getFormName();
                    $instrumentLabel = REDCap::getInstrumentNames($currentFormName);
                    $htmlContent .= "<tr valign='top' style='text-align:center;background-color:#ddd;width:28px;'><th class='codebook-form-header' colspan='4'>Instrument:<span style='font-size:120%;font-weight:bold;margin-left:7px;color:#000;'>$instrumentLabel</span> ($currentFormName)</th></tr>";
                    $htmlContent .= "<tr valign='top' style='text-align:center;background-color:#ddd;width:28px;'><th style='text-align:center !important;'>#</th><th>Variable / Field Name</th><th>Field Label<div><i>
<span style='color:#666;font-size:11px'>Field Note</span></i></div></th><th>Field Attributes (Field Type, Validation, Choices, Calculations, etc.)</th></tr>";
                }
                $fieldName = $dictionaryItem->getFieldName();
                $fieldLabel = $dictionaryItem->getFieldLabel();
                $fieldAttr = $dictionaryItem->getFieldAttr();

                $fieldNumberInSection++;
                $htmlContent .= "<tr valign='top'><td style='text-align:center !important;'>$fieldNumberInSection</td><td class='vwrap'>$fieldName</td><td>$fieldLabel</td><td>$fieldAttr</td></tr>";
            }
        }
        $htmlContent .= "</table>";

        // pop-up the print dialogue
        $htmlContent .= '<script type="text/javascript">$(document).ready(function() { window.print();});</script>';

        echo $htmlContent;
    }

    private function getCurrentDate(): string
    {
        setlocale(LC_TIME, "");

        // Get the current timestamp
        $current_timestamp = time();

        // Format the current timestamp using strftime
        return strftime("%B %d, %Y", $current_timestamp);

    }
    private function printCSS(): string
    {
        $styleURL = $this->getUrl("css/style.css");
        return "<link rel='stylesheet' href='$styleURL' type='text/css'>";
    }

    // ******************************************************************************************
    // ******************************************************************************************
    // WORD
    // ******************************************************************************************
    // ******************************************************************************************
    public function generateWordDoc(): void
    {
        // $this->initFriendlyVariableNames();
        $project_id = REDCap::escapeHtml($_GET["pid"]);
        $settings = ExternalModules::getProjectSettingsAsArray($this->PREFIX, $project_id);
        $instrumentsToBeDisplayed = $settings['data_dictionary_instruments']['value'];

        $genericUnknownCode = $settings['unknown_code']['value'];

        // init the Php Word for our project
        $phpWord = $this->initPhpWord();

        // Add the title page to the document
        $this->createTitlePage($phpWord, Util::formatTextForDisplay(REDCap::getProjectTitle()));

        // Footer/Header
        $section = $phpWord->addSection();
        $this->addHeaderFooter($section);

        $currentFormName = "";
        $choiceFieldTypes = ['radio', 'dropdown', 'checkbox', 'yesno', 'truefalse'];
        $dataDictionaryArray = REDCap::getDataDictionary('array');
        $recordIdField = REDCap::getRecordIdField();
        // TODO how do you know if this is auto numbering of not auto numbering?

        $tableColumn1Width = 2000;
        $tableColumn2Width = 7000;

        foreach ($dataDictionaryArray as $fieldName => $fieldAttributes) {
            $dictionaryItem = new WordDictionaryItem($fieldAttributes, $genericUnknownCode, $recordIdField === $fieldName);

            // check if there is a form specified in the configuration page
            if ((is_null($instrumentsToBeDisplayed[0]) || in_array($dictionaryItem->getFormName(), $instrumentsToBeDisplayed))) {
                if (!$dictionaryItem->isHidden()) {
                    if ($currentFormName !== $dictionaryItem->getFormName()) {
                        // add only when it is not the first one
                        if ($currentFormName !== "") $section->addPageBreak();

                        $currentFormName = $dictionaryItem->getFormName();
                        $instrumentLabel = Util::formatTextForDisplay(REDCap::getInstrumentNames($currentFormName));

                        $section->addTitle($instrumentLabel, 1);
                        $events = $this->getEventNamesOrRepeatingIndicatorFromInstrument($project_id, $currentFormName);
                    }

                    $fieldLabel = $dictionaryItem->getElementName();
                    $section->addTitle($fieldLabel, 2);

                    $lineStyle = array('weight' => 0, 'width' => 450, 'height' => 0, 'color' => '000000');
                    $section->addLine($lineStyle);

                    // add the below table here
                    /*
                     *  ----------------
                     *  |  A  |   B    |
                     *  |-----|--------|
                     *  |  C  |   D    |
                     *  |-----|--------|
                     *  |  K  |   L    |
                     *  |-----|--------|
                     *  |  M           |
                     *  |-----|--------|
                     *  |     |  N     |
                     *  ----------------
                     */
                    $dataDictionaryStyleName = "DataDictionaryStyle";
                    $dataDictionaryStyle = ['borderSize' => 0, 'borderColor' => '#ffffff', 'cellMargin' => 20, 'width' => '100%', 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::END, 'cellSpacing' => 10];
                    $dataDictionaryFirstRowStyle = ['borderBottomSize' => 18, 'borderBottomColor' => '#000000'];
                    $dataDictionaryHeaderFontStyle = ['bold' => true];
                    $cellColSpan = ['gridSpan' => 2];

                    $phpWord->addTableStyle($dataDictionaryStyleName, $dataDictionaryStyle, $dataDictionaryFirstRowStyle);

                    $table = $section->addTable($dataDictionaryStyleName);
                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("Description:", $dataDictionaryHeaderFontStyle);
                    $table->addCell($tableColumn2Width)->addText($dictionaryItem->getDescription());
                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("Field Name:", $dataDictionaryHeaderFontStyle);
                    $table->addCell($tableColumn2Width)->addText($dictionaryItem->getFieldName(),
                        [
                            "name" => "courier",
                            "size" => 8,
                            "color" => "#C00000"
                        ]);
                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("Purpose:", $dataDictionaryHeaderFontStyle);
                    $table->addCell($tableColumn2Width)->addText($dictionaryItem->getPurpose());

                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("Data Collection:", $dataDictionaryHeaderFontStyle);
                    $cell = $table->addCell($tableColumn2Width);
                    $cell->addText($dictionaryItem->getDataCollection());
                    $this->getCollectionTimepointOrRepeatingIndicator($cell, $events);

                    // Need further testing
                    // $collectionCondition = Util::convertToFriendlyMessage($dictionaryItem->getBranchingLogic(), $this->friendlyNames, $this->friendlyValues);
                    $collectionCondition = $dictionaryItem->getBranchingLogic();
                    if (!empty($collectionCondition)) {
                        $table->addRow();
                        $table->addCell($tableColumn1Width)->addText("Collected When:", $dataDictionaryHeaderFontStyle);
                        $table->addCell($tableColumn2Width)->addText($collectionCondition,
                            [
                                "name" => "courier",
                                "size" => 8,
                                "color" => "#C00000"
                            ]);
                    }

                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("Data Obligation:", $dataDictionaryHeaderFontStyle);
                    $table->addCell($tableColumn2Width)->addText($dictionaryItem->getDataObligation());

                    $table->addRow();
                    $cell = $table->addCell($tableColumn1Width);
                    $textRun = $cell->addTextRun();
                    // Adding "Permitted Values:" text
                    $textRun->addText("Permitted Values:", $dataDictionaryHeaderFontStyle);

                    // Check if it is checkbox to add the red asterisk
                    if ($dictionaryItem->getFieldType() == 'checkbox') {
                        if (sizeof(explode("|", Util::formatTextForDisplay($dictionaryItem->getSelectChoices()))) > 1)
                            $textRun->addText("*</w:t><w:br/><w:t>* multiple select", array('color' => 'C00000'));
                    }

                    if (in_array($dictionaryItem->getFieldType(), $choiceFieldTypes)) {
                        // need to create sub table
                        $choices = explode("|", Util::formatTextForDisplay($dictionaryItem->getSelectChoices()));

                        $codeDescTableStyleName = 'Code Desc Table';
                        $codeDescTableStyle = ['borderSize' => 0, 'borderColor' => '#ffffff', 'cellMargin' => 0, 'alignment' => \PhpOffice\PhpWord\SimpleType\JcTable::START, 'cellSpacing' => 0];
                        $codeDescTableFirstRowStyle = ['borderBottomSize' => 18, 'borderBottomColor' => '#000000'];
                        $codeDescTableHeaderFontStyle = ['bold' => true];

                        $phpWord->addTableStyle($codeDescTableStyleName, $codeDescTableStyle, $codeDescTableFirstRowStyle);

                        $innerTable = $table->addCell($tableColumn1Width)->addTable($codeDescTableStyleName);
                        $innerTable->addRow();
                        $innerTable->addCell(1000)->addText("Code", $codeDescTableHeaderFontStyle);
                        $innerTable->addCell(5000)->addText("Description", $codeDescTableHeaderFontStyle);

                        foreach ($choices as $choice) {
                            // Split each choice into code and desc
                            list($code, $desc) = explode(', ', $choice, 2);
                            // Create the table inside the cell
                            $innerTable->addRow();;
                            $innerTable->addCell(1000)->addText(trim($code));
                            $innerTable->addCell(5000)->addText(trim($desc));
                        }
                    } else {
                        if ($dictionaryItem->getFieldType() === 'calc') {
                            $table->addCell($tableColumn2Width)->addText($dictionaryItem->getPermittedValues(),
                                [
                                    "name" => "courier",
                                    "size" => 8,
                                    "color" => "#C00000"
                                ]);
                        } else {
                            $table->addCell($tableColumn2Width)->addText($dictionaryItem->getPermittedValues());
                        }
                    }

                    // add 'Collection Guide'
                    $collectionGuide = $dictionaryItem->getCollectionGuide();
                    if ($collectionGuide !== '')
                    {
                        $table->addRow();
                        $table->addCell($tableColumn1Width)->addText("Collection Guide:", $dataDictionaryHeaderFontStyle);
                        $table->addCell($tableColumn2Width)->addText($collectionGuide);
                    }

                    $table->addRow();
                    $table->addCell($tableColumn1Width + $tableColumn2Width, $cellColSpan)->addText("Data Source, Standard/ Terminology:", $dataDictionaryHeaderFontStyle);
                    $table->addRow();
                    $table->addCell($tableColumn1Width)->addText("");
                    $table->addCell($tableColumn2Width)->addText($dictionaryItem->getStandards());
                    if ($dictionaryItem->getStandards() !== "") {
                        $table->addRow();
                        $table->addCell($tableColumn1Width + $tableColumn2Width, $cellColSpan)->addText("");
                    }
                }
            }
        }

        // Create filename
        $tempFile = "DataDictionary_".$project_id."_".date("Y-m-d") . ".docx";
        $objWriter = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
        $objWriter->save($tempFile);

        \REDCap::logEvent("Downloaded word data dictionary");

        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $tempFile . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tempFile));

        // Send the file to the browser
        readfile($tempFile);

        // Delete temporary file
        unlink($tempFile);
    }

    private function initPhpWord()
    {
        $phpWord = new \PhpOffice\PhpWord\PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        // Numbered heading
        $headingNumberingStyleName = 'headingNumbering';
        $phpWord->addNumberingStyle(
            $headingNumberingStyleName,
            ['type' => 'multilevel',
                'levels' => [
                    ['pStyle' => 'Heading1', 'format' => 'decimal', 'text' => '%1'],
                    ['pStyle' => 'Heading2', 'format' => 'decimal', 'text' => '%1.%2']
                ],
            ]
        );
        $phpWord->addTitleStyle(1, ['size' => 16], ['numStyle' => $headingNumberingStyleName, 'numLevel' => 0]);
        $phpWord->addTitleStyle(2, ['size' => 14], ['numStyle' => $headingNumberingStyleName, 'numLevel' => 1]);

        return $phpWord;
    }

    private function createTitlePage($phpWord, $title): void
    {
        $section = $phpWord->addSection();

        // Add a title page
        $section->addTextBreak(7); // Add some space
        $section->addText(Util::formatTextForDisplay($title), array('bold' => true, 'size' => 40), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $section->addTextBreak(2); // Add some space
        $section->addText('Data Dictionary', array('italic' => true, 'size' => 32), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
        $section->addTextBreak(1); // Add some space
        $section->addText($this->getCurrentDate(), array('size' => 16), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::CENTER));
    }

    private function addHeaderFooter($section): void
    {
        $footer = $section->addFooter();
        $footer->addPreserveText('Page {PAGE}', array('size' => 10), array('alignment' => \PhpOffice\PhpWord\SimpleType\Jc::END));
    }

    private function getEventNamesOrRepeatingIndicatorFromInstrument($project_id, $instrumentName)
    {
        $result = $this->query(
            '
    select f.form_name, m.descrip event_name, a.arm_name, 
    (select count(*) from redcap_events_repeat r where r.form_name = f.form_name and r.event_id = m.event_id) is_repeat 
    from redcap_events_forms f 
    inner join redcap_events_metadata m on m.event_id = f.event_id 
    inner join redcap_events_arms a on a.arm_id = m.arm_id 
    where
      a.project_id = ?
      and f.form_name = ?
  ',
            [
                $project_id,
                $instrumentName
            ]
        );

        $fieldArray = array();
        while ($row = $result->fetch_assoc()) {
            $fieldArray[] = $row['event_name'] . ' at ' . $row['arm_name'] .  '|' . $row['is_repeat'];
        }
        return $fieldArray;
    }

    private function getCollectionTimepointOrRepeatingIndicator($cell, $events) {
        if (REDCap::isLongitudinal()) {
            $cell->addText("Collected at the following time point(s):");
            foreach ($events as $event) {
                list($event_name, $is_repeat) = explode("|", $event, 2);
                if ($is_repeat) {
                    $event_name .= ' (can be collected more than once)';
                }
                $cell->addListItem($event_name);
            }
        } else {
            list($event_name, $is_repeat) = explode("|", $events[0], 2);
            if ($is_repeat) {
                $cell->addText("Can be collected more than once (repeated)");
            }
        }
    }
}