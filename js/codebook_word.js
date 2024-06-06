$(document).ready(function(){
    // Search for button with text "Print page"
    var printButton = $("button:contains('Print page')");
    // Check if button is found
    if (printButton.length > 0) {
        // Create new button
        var newButton = $('<button class="jqbuttonmed hidden-when-popup ui-button ui-corner-all ui-widget" onclick="exportToWord()"><i class="fa-sharp fa-solid fa-file-word"></i> Export to Word</button>');
        // Append new button beside the found button
        //var tdElement = printButton[0].previousSibling.parentNode;
        //if (!isEmpty(tdElement)) {
          //  tdElement.style.width = '230px';
        //}
        printButton.after(newButton);
    }
});
