(function () {
    'use strict';

    var ZWNJ = '\u200C';

    tinymce.PluginManager.add('persian_kit_zwnj', function (editor) {
        function insertZwnj() {
            editor.insertContent(ZWNJ);
        }

        // Shift+Space
        editor.on('keydown', function (e) {
            var isShiftSpace = e.shiftKey && e.keyCode === 32 && !e.ctrlKey && !e.metaKey && !e.altKey;
            if (isShiftSpace) {
                e.preventDefault();
                insertZwnj();
            }
        });

        // Toolbar button (TinyMCE 4 API)
        editor.addButton('persian_kit_zwnj', {
            text: 'ZWNJ',
            tooltip: 'Insert Zero-Width Non-Joiner (Shift+Space)',
            onclick: insertZwnj
        });
    });
})();
