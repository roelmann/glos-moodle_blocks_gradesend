$('#send-grades-checkbox').click(function () {
    if (this.checked) {
        $('#send-grades-button').prop('disabled', false);
    }
    else {
        $('#send-grades-button').prop('disabled', true);
    }
}
);