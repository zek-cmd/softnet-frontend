(function () {
  var editorEl = document.getElementById('quill-editor');
  if (!editorEl || typeof Quill === 'undefined') return;

  var quill = new Quill(editorEl, {
    theme: 'snow',
    placeholder: 'Write the news content here...',
    modules: {
      toolbar: [
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'header': 1 }, { 'header': 2 }, { 'header': 3 }],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        ['link'],
        ['clean']
      ]
    }
  });

  var initialContent = editorEl.getAttribute('data-content');
  if (initialContent) {
    quill.root.innerHTML = initialContent;
  }

  var form = document.getElementById('newsForm') || document.getElementById('postForm');
  if (form) {
    form.addEventListener('submit', function () {
      var field = document.getElementById('content');
      if (field) field.value = quill.root.innerHTML;
    });
  }
})();
