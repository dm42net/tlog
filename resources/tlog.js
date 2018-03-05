function hiddenSearchAppendSubmit(searchstring) {
    $( "input#HiddenSearchString" ).val( $( "input#Search" ).val() + " " + searchstring);
    $( "form#HiddenSearchForm" ).submit();
}

function hiddenSearchSubmit(searchstring) {
    $( "input#HiddenSearchString" ).val(searchstring);
    $( "form#HiddenSearchForm" ).submit();
}

history.pushState(null, null, document.URL);
window.addEventListener('popstate', function () {
    history.pushState(null, null, document.URL);
});
