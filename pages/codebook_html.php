<?php
/**
 * Include REDCap header.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/header.php";

$module = new \Monash\Helix\CustomCodebookModule\CustomCodebookModule();
$module->renderDataDictionaryPage();

/**
 * Include REDCap footer.
 */
require_once APP_PATH_DOCROOT . "ProjectGeneral/footer.php";