function template_form_load_diff(file, repository) {
    modal_open('?r=deploy/diff&popup=1&file=' + file + '&repository=' + repository);
}

function modal_open(url) {
    $("#site-content").append('<div id="modal1"></div><div id="overlay"></div>');
    $("#overlay").click(modal_close);
    if (url) {
        $("#modal1").load(url, initScroll);
    }
}

function modal_close() {
    $("#modal1").remove();
    $("#overlay").remove();
}

function scrollDiv(e)
{
  let container = $('#modal1');

  if ($scrollList.length == 0) return;

  let i = $scrollList.index;


  switch (e.keyCode) {
    //up arrow
    case 38:
      $scrollList.index--;
      if ($scrollList.index < 0) $scrollList.index = $scrollList.length - 1;
      e.preventDefault();
    break;
   
    //dn arrow
    case 40:
      $scrollList.index++;
      if ($scrollList.index >= $scrollList.length) $scrollList.index = 0;
      e.preventDefault();
    break;
  }

  let scrollTo = $scrollList.members.eq(i);

  //container.scrollTop(scrollTo.offset().top - container.offset().top + container.scrollTop() ) ;

  container.animate({
    scrollTop: scrollTo.offset().top - container.offset().top + container.scrollTop()
  }, 150);

}



function initScroll()
{
  let members = $('.diffInserted,.diffDeleted');

  $scrollList = {
    members: members,
    length: members.size(),
    index: 0,
  }
}