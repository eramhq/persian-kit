(function () {
    'use strict';

    var ZWNJ = '\u200C';

    tinymce.PluginManager.add('persian_kit_zwnj', function (editor) {
        function insertZwnj() {
            editor.insertContent(ZWNJ);
        }

        // Ctrl+Shift+2
        editor.addShortcut('ctrl+shift+50', 'Insert ZWNJ', insertZwnj);

        // Shift+Space
        editor.on('keydown', function (e) {
            if (e.shiftKey && e.keyCode === 32 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                e.preventDefault();
                insertZwnj();
            }
        });

        // Toolbar button (TinyMCE 4 API)
        editor.addButton('persian_kit_zwnj', {
            text: 'ZWNJ',
            tooltip: 'Insert Zero-Width Non-Joiner (Ctrl+Shift+2)',
            onclick: insertZwnj
        });
    });
})();
