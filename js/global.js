function template_form_load_diff(file, repository) {
    modal_open('?r=deploy/diff&popup=1&file=' + file + '&repository=' + repository);
}

function modal_open(url) {
    $("#site-content").append('<div id="modal1"></div><div id="overlay"></div>');
    $("#overlay").click(modal_close);
    if (url) {
        $("#modal1").load(url);
    }
}

function modal_close() {
    $("#modal1").remove();
    $("#overlay").remove();
}